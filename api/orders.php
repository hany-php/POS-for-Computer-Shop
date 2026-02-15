<?php
/**
 * Orders API
 * واجهة برمجة الطلبات
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'create') {
        $items = $input['items'] ?? [];
        $paymentMethod = $input['payment_method'] ?? 'cash';
        $settlementMode = ($input['settlement_mode'] ?? 'full') === 'debt' ? 'debt' : 'full';
        $paymentReceived = floatval($input['payment_received'] ?? 0);
        $discount = floatval($input['discount'] ?? 0);
        $customerId = intval($input['customer_id'] ?? 0);
        $suspendedOrderId = intval($input['suspended_order_id'] ?? 0);
        
        if (empty($items)) {
            jsonResponse(['success' => false, 'error' => 'لا توجد عناصر في الطلب'], 400);
        }
        
        $pdo = $db->getConnection();
        $pdo->beginTransaction();
        
        try {
            if ($customerId > 0) {
                $customer = $db->fetchOne("SELECT id FROM customers WHERE id = ? AND is_active = 1 LIMIT 1", [$customerId]);
                if (!$customer) {
                    throw new Exception('العميل المحدد غير موجود أو غير نشط');
                }
            } else {
                $customerId = null;
            }

            $subtotal = 0;
            $totalItemDiscount = 0;
            $preparedItems = [];
            $taxRate = getTaxRateDecimal();

            foreach ($items as $item) {
                $productId = intval($item['product_id'] ?? 0);
                $qty = intval($item['quantity'] ?? 0);
                $price = floatval($item['unit_price']);
                $itemDisc = floatval($item['discount'] ?? 0);

                if ($productId <= 0 || $qty <= 0 || $price < 0 || $itemDisc < 0) {
                    throw new Exception('بيانات عناصر الطلب غير صالحة');
                }

                $lineTotal = ($price * $qty);
                if ($itemDisc > $lineTotal) {
                    $itemDisc = $lineTotal;
                }
                $subtotal += $lineTotal;
                $totalItemDiscount += $itemDisc;

                $product = $db->fetchOne("SELECT id, name, quantity, is_active FROM products WHERE id = ?", [$productId]);
                if (!$product || intval($product['is_active']) !== 1) {
                    throw new Exception('منتج غير متاح في المخزون');
                }
                if (intval($product['quantity']) < $qty) {
                    throw new Exception('الكمية غير كافية للمنتج: ' . $product['name']);
                }
                
                $preparedItems[] = [
                    'product_id' => $productId,
                    'product_name' => $product ? $product['name'] : 'منتج محذوف',
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'discount' => $itemDisc,
                    'total_price' => $lineTotal - $itemDisc
                ];
            }
            
            $invoiceDiscount = max(0, $discount); // From input
            $totalDiscount = $totalItemDiscount + $invoiceDiscount;
            $netSubtotal = max(0, $subtotal - $totalDiscount);
            $taxAmount = $netSubtotal * $taxRate;
            $total = $netSubtotal + $taxAmount;

            $paymentReceived = max(0, $paymentReceived);
            $dueAmount = max(0, $total - $paymentReceived);

            if ($settlementMode === 'full' && $dueAmount > 0.00001) {
                throw new Exception('في السداد الكامل يجب تحصيل كامل قيمة الفاتورة');
            }
            if ($dueAmount > 0.00001 && !$customerId) {
                throw new Exception('لا يمكن تسجيل دين بدون اختيار عميل');
            }
            $change = max(0, $paymentReceived - $total);
            
            $orderNumber = $db->generateOrderNumber();
            
            $orderId = $db->insert(
                "INSERT INTO orders (order_number, user_id, customer_id, subtotal, tax_amount, discount_amount, total, payment_method, payment_received, change_amount, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')",
                [$orderNumber, $_SESSION['user_id'], $customerId, $subtotal, $taxAmount, $totalDiscount, $total, $paymentMethod, $paymentReceived, $change]
            );

            if ($dueAmount > 0.00001 && $customerId) {
                $db->query(
                    "UPDATE customers SET balance = balance + ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                    [$dueAmount, $customerId]
                );
            }

            if ($suspendedOrderId > 0) {
                $db->query(
                    "UPDATE suspended_orders SET status = 'closed', updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                    [$suspendedOrderId]
                );
            }
            
            foreach ($preparedItems as $item) {
                $db->insert(
                    "INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, discount, total_price) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$orderId, $item['product_id'], $item['product_name'], $item['quantity'], $item['unit_price'], $item['discount'], $item['total_price']]
                );
                
                // Update stock safely: avoid negative inventory
                $affected = $db->query(
                    "UPDATE products
                     SET quantity = quantity - ?, updated_at = CURRENT_TIMESTAMP
                     WHERE id = ? AND quantity >= ?",
                    [$item['quantity'], $item['product_id'], $item['quantity']]
                )->rowCount();
                if ($affected !== 1) {
                    throw new Exception('فشل تحديث المخزون بسبب عدم كفاية الكمية');
                }
            }

            // Auto accounting posting + cashier cycle update
            postSaleAccountingEntry($orderId);
            
            $pdo->commit();
            logActivity(
                'بيع جديد',
                'order',
                $orderId,
                'رقم الطلب: ' . $orderNumber . ' - المبلغ: ' . $total . ($dueAmount > 0 ? (' - دين: ' . $dueAmount) : '')
            );
            jsonResponse([
                'success' => true,
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'total' => $total,
                'payment_received' => $paymentReceived,
                'change' => $change,
                'due_amount' => $dueAmount,
                'suspended_order_id' => $suspendedOrderId > 0 ? $suspendedOrderId : null
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(['success' => false, 'error' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }
    
    if ($action === 'return') {
        $orderId = intval($input['order_id'] ?? 0);
        if ($orderId <= 0) {
            jsonResponse(['success' => false, 'error' => 'رقم الطلب غير صالح'], 400);
        }
        try {
            $pdo = $db->getConnection();
            $pdo->beginTransaction();
            // Ensure column exists (Migration check)
            try {
                $cols = $db->fetchAll("PRAGMA table_info(orders)");
                $hasCol = false;
                foreach ($cols as $col) {
                    if ($col['name'] === 'return_invoice_number') { $hasCol = true; break; }
                }
                if (!$hasCol) {
                    $db->query("ALTER TABLE orders ADD COLUMN return_invoice_number TEXT DEFAULT NULL");
                }
            } catch (Exception $e) { /* Ignore if fails, might exist */ }

            $order = $db->fetchOne("SELECT id, status, customer_id, total, payment_received FROM orders WHERE id = ?", [$orderId]);
            if (!$order) {
                throw new RuntimeException('الطلب غير موجود', 404);
            }
            if ($order['status'] !== 'completed') {
                throw new RuntimeException('لا يمكن إرجاع طلب غير مكتمل أو مُرجع مسبقًا', 400);
            }

            // Generate Return Invoice Number
            // Format: RET-{OrderId}-{Random} or RET-{Date}-{Seq}
            $returnNumber = 'RET-' . date('Ymd') . '-' . str_pad($orderId, 4, '0', STR_PAD_LEFT);
            
            // Restore stock
            $items = $db->fetchAll("SELECT product_id, quantity FROM order_items WHERE order_id = ?", [$orderId]);
            foreach ($items as $item) {
                $db->query("UPDATE products SET quantity = quantity + ? WHERE id = ?", [$item['quantity'], $item['product_id']]);
            }
            
            $updated = $db->query(
                "UPDATE orders SET status = 'refunded', return_invoice_number = ? WHERE id = ? AND status = 'completed'",
                [$returnNumber, $orderId]
            )->rowCount();
            if ($updated !== 1) {
                throw new RuntimeException('لا يمكن تنفيذ المرتجع لهذه الفاتورة', 400);
            }

            $customerId = intval($order['customer_id'] ?? 0);
            if ($customerId > 0) {
                $total = floatval($order['total'] ?? 0);
                $received = max(0, floatval($order['payment_received'] ?? 0));
                $due = max(0, $total - min($received, $total));
                if ($due > 0.00001) {
                    $db->query(
                        "UPDATE customers SET balance = CASE WHEN balance > ? THEN balance - ? ELSE 0 END, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                        [$due, $due, $customerId]
                    );
                }
            }

            // Auto accounting posting + cycle refund totals
            postRefundAccountingEntry($orderId);

            $pdo->commit();
            
            logActivity('إرجاع طلب', 'order', $orderId, 'رقم الإرجاع: ' . $returnNumber);
            jsonResponse(['success' => true, 'return_number' => $returnNumber]);
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $code = intval($e->getCode());
            if ($code < 400 || $code > 599) $code = 500;
            jsonResponse(['success' => false, 'error' => $e->getMessage()], $code);
        }
    }
}
