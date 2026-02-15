<?php
/**
 * Suspended Orders API
 * حفظ/استدعاء الطلبات المعلقة في نقطة البيع
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

header('Content-Type: application/json; charset=utf-8');

function generateSuspendNumber($db) {
    $date = date('Ymd');
    $prefix = 'SUS-' . $date . '-';
    $last = $db->fetchOne(
        "SELECT suspend_number FROM suspended_orders WHERE suspend_number LIKE ? ORDER BY id DESC LIMIT 1",
        [$prefix . '%']
    );
    $seq = 1;
    if ($last && !empty($last['suspend_number'])) {
        $parts = explode('-', $last['suspend_number']);
        $n = intval(end($parts));
        if ($n > 0) $seq = $n + 1;
    }
    return $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = intval($_GET['id'] ?? 0);
    if ($id > 0) {
        $row = $db->fetchOne("SELECT * FROM suspended_orders WHERE id = ? LIMIT 1", [$id]);
        if (!$row) {
            jsonResponse(['success' => false, 'error' => 'الطلب المعلق غير موجود'], 404);
        }
        $state = json_decode($row['state_json'] ?? '{}', true);
        if (!is_array($state)) $state = [];
        jsonResponse(['success' => true, 'order' => $row, 'state' => $state]);
    }

    $q = trim((string)($_GET['q'] ?? ''));
    $params = [];
    $sql = "SELECT id, suspend_number, customer_name, customer_phone, total, created_at
            FROM suspended_orders
            WHERE status = 'open'";
    if ($q !== '') {
        $sql .= " AND (suspend_number LIKE ? OR customer_name LIKE ? OR customer_phone LIKE ?)";
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }
    $sql .= " ORDER BY id DESC LIMIT 30";
    $rows = $db->fetchAll($sql, $params);
    jsonResponse(['success' => true, 'orders' => $rows]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'create') {
        $state = $input['state'] ?? [];
        $cart = $state['cart'] ?? [];
        if (!is_array($cart) || empty($cart)) {
            jsonResponse(['success' => false, 'error' => 'السلة فارغة'], 400);
        }

        $customer = $state['selectedCustomer'] ?? null;
        $customerId = intval($customer['id'] ?? 0);
        if ($customerId <= 0) $customerId = null;
        $customerName = trim((string)($customer['name'] ?? ($input['customer_name'] ?? '')));
        $customerPhone = trim((string)($customer['phone'] ?? ($input['customer_phone'] ?? '')));
        $total = floatval($input['total'] ?? ($state['total'] ?? 0));
        $notes = trim((string)($input['notes'] ?? ''));
        $suspendNumber = generateSuspendNumber($db);

        $id = $db->insert(
            "INSERT INTO suspended_orders
             (suspend_number, user_id, customer_id, customer_name, customer_phone, state_json, total, status, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'open', ?)",
            [
                $suspendNumber,
                $_SESSION['user_id'] ?? null,
                $customerId,
                ($customerName !== '' ? $customerName : null),
                ($customerPhone !== '' ? $customerPhone : null),
                json_encode($state, JSON_UNESCAPED_UNICODE),
                $total,
                ($notes !== '' ? $notes : null)
            ]
        );
        logActivity('حفظ طلب معلق', 'suspended_order', $id, $suspendNumber);
        jsonResponse(['success' => true, 'id' => $id, 'suspend_number' => $suspendNumber]);
    }

    if ($action === 'close') {
        $id = intval($input['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['success' => false, 'error' => 'رقم الطلب غير صالح'], 400);
        }
        $db->query(
            "UPDATE suspended_orders SET status = 'closed', updated_at = CURRENT_TIMESTAMP WHERE id = ?",
            [$id]
        );
        jsonResponse(['success' => true]);
    }

    if ($action === 'cancel') {
        $id = intval($input['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['success' => false, 'error' => 'رقم الطلب غير صالح'], 400);
        }
        $db->query(
            "UPDATE suspended_orders SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP WHERE id = ?",
            [$id]
        );
        logActivity('إلغاء طلب معلق', 'suspended_order', $id);
        jsonResponse(['success' => true]);
    }

    jsonResponse(['success' => false, 'error' => 'إجراء غير مدعوم'], 400);
}

jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
