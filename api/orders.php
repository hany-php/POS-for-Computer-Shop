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
        $paymentReceived = floatval($input['payment_received'] ?? 0);
        $discount = floatval($input['discount'] ?? 0);
        
        if (empty($items)) {
            jsonResponse(['success' => false, 'error' => 'لا توجد عناصر في الطلب'], 400);
        }
        
        $pdo = $db->getConnection();
        $pdo->beginTransaction();
        
        try {

            $subtotal = 0;
            $totalItemDiscount = 0;
            $preparedItems = [];

            foreach ($items as $item) {
                $qty = floatval($item['quantity']);
                $price = floatval($item['unit_price']);
                $itemDisc = floatval($item['discount'] ?? 0);
                
                $lineTotal = ($price * $qty);
                $subtotal += $lineTotal;
                $totalItemDiscount += $itemDisc;
                
                $product = $db->fetchOne("SELECT name FROM products WHERE id = ?", [$item['product_id']]);
                
                $preparedItems[] = [
                    'product_id' => $item['product_id'],
                    'product_name' => $product ? $product['name'] : 'منتج محذوف',
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'discount' => $itemDisc,
                    'total_price' => $lineTotal - $itemDisc
                ];
            }
            
            $invoiceDiscount = $discount; // From input
            $totalDiscount = $totalItemDiscount + $invoiceDiscount;
            $netSubtotal = max(0, $subtotal - $totalDiscount);
            $taxAmount = $netSubtotal * TAX_RATE;
            $total = $netSubtotal + $taxAmount;
            
            $change = max(0, $paymentReceived - $total);
            
            $orderNumber = $db->generateOrderNumber();
            
            $orderId = $db->insert(
                "INSERT INTO orders (order_number, user_id, subtotal, tax_amount, discount_amount, total, payment_method, payment_received, change_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')",
                [$orderNumber, $_SESSION['user_id'], $subtotal, $taxAmount, $totalDiscount, $total, $paymentMethod, $paymentReceived, $change]
            );
            
            foreach ($preparedItems as $item) {
                $db->insert(
                    "INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, discount, total_price) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$orderId, $item['product_id'], $item['product_name'], $item['quantity'], $item['unit_price'], $item['discount'], $item['total_price']]
                );
                
                // Update stock
                $db->query("UPDATE products SET quantity = quantity - ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?", 
                    [$item['quantity'], $item['product_id']]);
            }
            
            $pdo->commit();
            logActivity('بيع جديد', 'order', $orderId, 'رقم الطلب: ' . $orderNumber . ' - المبلغ: ' . $total);
            jsonResponse(['success' => true, 'order_id' => $orderId, 'order_number' => $orderNumber, 'total' => $total]);
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(['success' => false, 'error' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }
    
    if ($action === 'return') {
        $orderId = intval($input['order_id'] ?? 0);
        try {
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

            // Generate Return Invoice Number
            // Format: RET-{OrderId}-{Random} or RET-{Date}-{Seq}
            $returnNumber = 'RET-' . date('Ymd') . '-' . str_pad($orderId, 4, '0', STR_PAD_LEFT);
            
            // Restore stock
            $items = $db->fetchAll("SELECT product_id, quantity FROM order_items WHERE order_id = ?", [$orderId]);
            foreach ($items as $item) {
                $db->query("UPDATE products SET quantity = quantity + ? WHERE id = ?", [$item['quantity'], $item['product_id']]);
            }
            
            $db->query("UPDATE orders SET status = 'refunded', return_invoice_number = ? WHERE id = ?", [$returnNumber, $orderId]);
            
            logActivity('إرجاع طلب', 'order', $orderId, 'رقم الإرجاع: ' . $returnNumber);
            jsonResponse(['success' => true, 'return_number' => $returnNumber]);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
