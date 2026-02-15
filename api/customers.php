<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $q = trim((string)($_GET['q'] ?? ''));
    $params = [];
    $sql = "SELECT id, name, phone FROM customers WHERE is_active = 1";
    if ($q !== '') {
        $sql .= " AND (name LIKE ? OR phone LIKE ?)";
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
    }
    $sql .= " ORDER BY name ASC LIMIT 20";
    $rows = $db->fetchAll($sql, $params);
    jsonResponse(['success' => true, 'customers' => $rows]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::hasRole(['admin', 'cashier'])) {
        jsonResponse(['success' => false, 'error' => 'غير مصرح'], 403);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'create') {
        $name = trim((string)($input['name'] ?? ''));
        $phone = trim((string)($input['phone'] ?? ''));
        if ($name === '') {
            jsonResponse(['success' => false, 'error' => 'اسم العميل مطلوب'], 422);
        }

        $existing = null;
        if ($phone !== '') {
            $existing = $db->fetchOne("SELECT id, name, phone FROM customers WHERE is_active = 1 AND phone = ? LIMIT 1", [$phone]);
        }
        if (!$existing) {
            $existing = $db->fetchOne("SELECT id, name, phone FROM customers WHERE is_active = 1 AND name = ? LIMIT 1", [$name]);
        }

        if ($existing) {
            jsonResponse(['success' => true, 'customer' => $existing, 'existing' => true]);
        }

        $id = $db->insert(
            "INSERT INTO customers (name, phone, email, address, notes, is_active) VALUES (?, ?, '', '', '', 1)",
            [$name, $phone]
        );
        $customer = $db->fetchOne("SELECT id, name, phone FROM customers WHERE id = ? LIMIT 1", [$id]);
        logActivity('إضافة عميل سريع من POS', 'customer', $id, $name);
        jsonResponse(['success' => true, 'customer' => $customer, 'existing' => false]);
    }
}

jsonResponse(['success' => false, 'error' => 'طلب غير صالح'], 400);
