<?php
/**
 * Due Invoices API
 * استدعاء فواتير غير مسددة بالكامل
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    if ($action !== 'collect') {
        jsonResponse(['success' => false, 'error' => 'إجراء غير مدعوم'], 400);
    }

    $orderId = intval($input['order_id'] ?? 0);
    $amount = floatval($input['amount'] ?? 0);
    $paymentMethod = strtolower(trim((string)($input['payment_method'] ?? 'cash')));
    if (!in_array($paymentMethod, ['cash', 'card', 'transfer'], true)) {
        $paymentMethod = 'cash';
    }
    $note = trim((string)($input['note'] ?? ''));

    if ($orderId <= 0) {
        jsonResponse(['success' => false, 'error' => 'رقم الفاتورة غير صالح'], 400);
    }
    if ($amount <= 0) {
        jsonResponse(['success' => false, 'error' => 'مبلغ السداد غير صالح'], 400);
    }

    $pdo = $db->getConnection();
    $pdo->beginTransaction();
    try {
        $order = $db->fetchOne(
            "SELECT id, order_number, customer_id, total, payment_received, status
             FROM orders
             WHERE id = ? LIMIT 1",
            [$orderId]
        );
        if (!$order) throw new Exception('الفاتورة غير موجودة');
        if (($order['status'] ?? '') !== 'completed') throw new Exception('لا يمكن سداد فاتورة غير مكتملة');

        $total = floatval($order['total'] ?? 0);
        $received = floatval($order['payment_received'] ?? 0);
        $beforeDue = max(0, $total - min($received, $total));
        if ($beforeDue <= 0.00001) throw new Exception('هذه الفاتورة مسددة بالكامل');

        $collect = min($amount, $beforeDue);
        $newReceived = min($total, $received + $collect);
        $afterDue = max(0, $total - $newReceived);

        $db->query(
            "UPDATE orders
             SET payment_received = ?
             WHERE id = ?",
            [$newReceived, $orderId]
        );

        $customerId = intval($order['customer_id'] ?? 0);
        if ($customerId > 0) {
            $db->query(
                "UPDATE customers
                 SET balance = CASE WHEN balance > ? THEN balance - ? ELSE 0 END,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?",
                [$collect, $collect, $customerId]
            );
        }

        $paymentId = $db->insert(
            "INSERT INTO customer_due_payments
             (order_id, customer_id, amount, payment_method, before_due, after_due, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $orderId,
                ($customerId > 0 ? $customerId : null),
                $collect,
                $paymentMethod,
                $beforeDue,
                $afterDue,
                ($note !== '' ? $note : null),
                $_SESSION['user_id'] ?? null
            ]
        );

        postCustomerDuePaymentAccountingEntry($paymentId);
        $paymentRow = $db->fetchOne(
            "SELECT p.*, u.full_name AS created_by_name
             FROM customer_due_payments p
             LEFT JOIN users u ON u.id = p.created_by
             WHERE p.id = ? LIMIT 1",
            [$paymentId]
        );

        $pdo->commit();
        logActivity(
            'تحصيل دين عميل',
            'order',
            $orderId,
            'فاتورة: ' . ($order['order_number'] ?? $orderId) . ' - سداد: ' . $collect . ' - متبقي: ' . $afterDue
        );
        jsonResponse([
            'success' => true,
            'order_id' => $orderId,
            'payment_id' => $paymentId,
            'order_number' => $order['order_number'] ?? null,
            'collected_amount' => $collect,
            'before_due' => $beforeDue,
            'after_due' => $afterDue,
            'payment_received' => $newReceived,
            'payment_row' => $paymentRow
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

$id = intval($_GET['id'] ?? 0);
if ($id > 0) {
    $order = $db->fetchOne(
        "SELECT o.*,
                c.name AS customer_name,
                c.phone AS customer_phone
         FROM orders o
         LEFT JOIN customers c ON c.id = o.customer_id
         WHERE o.id = ? LIMIT 1",
        [$id]
    );
    if (!$order) {
        jsonResponse(['success' => false, 'error' => 'الفاتورة غير موجودة'], 404);
    }
    $items = $db->fetchAll("SELECT * FROM order_items WHERE order_id = ?", [$id]);
    $order['due_amount'] = max(0, floatval($order['total'] ?? 0) - min(floatval($order['payment_received'] ?? 0), floatval($order['total'] ?? 0)));
    $payments = $db->fetchAll(
        "SELECT p.*, u.full_name AS created_by_name
         FROM customer_due_payments p
         LEFT JOIN users u ON u.id = p.created_by
         WHERE p.order_id = ?
         ORDER BY p.id DESC",
        [$id]
    );
    jsonResponse(['success' => true, 'order' => $order, 'items' => $items, 'payments' => $payments]);
}

$q = trim((string)($_GET['q'] ?? ''));
$params = [];
$sql = "SELECT o.id, o.order_number, o.total, o.payment_received,
               c.name AS customer_name, c.phone AS customer_phone
        FROM orders o
        LEFT JOIN customers c ON c.id = o.customer_id
        WHERE o.status = 'completed'
          AND (o.total > o.payment_received)";
if ($q !== '') {
    $sql .= " AND (o.order_number LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)";
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
$sql .= " ORDER BY o.id DESC LIMIT 30";
$rows = $db->fetchAll($sql, $params);
$rows = array_map(function($r) {
    $total = floatval($r['total'] ?? 0);
    $received = floatval($r['payment_received'] ?? 0);
    $r['due_amount'] = max(0, $total - min($received, $total));
    return $r;
}, $rows);

jsonResponse(['success' => true, 'orders' => $rows]);
