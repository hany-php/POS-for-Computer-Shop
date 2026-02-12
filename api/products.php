<?php
/**
 * Products API
 * واجهة برمجة المنتجات
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $category = $_GET['category'] ?? '';
    $search = $_GET['search'] ?? '';
    
    $sql = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.is_active = 1";
    $params = [];
    
    if ($category) {
        $sql .= " AND p.category_id = ?";
        $params[] = $category;
    }
    if ($search) {
        $sql .= " AND (p.name LIKE ? OR p.barcode LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    $sql .= " ORDER BY p.name";
    
    $products = $db->fetchAll($sql, $params);
    jsonResponse(['success' => true, 'products' => $products]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireRole(['admin']);
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'create') {
        $id = $db->insert(
            "INSERT INTO products (name, description, category_id, price, cost_price, quantity, barcode, serial_number, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$input['name'], $input['description'] ?? '', $input['category_id'], $input['price'], $input['cost_price'] ?? 0, $input['quantity'] ?? 0, $input['barcode'] ?? '', $input['serial_number'] ?? '', $input['image_url'] ?? '']
        );
        jsonResponse(['success' => true, 'id' => $id]);
    }
    
    if ($action === 'update') {
        $db->query(
            "UPDATE products SET name=?, description=?, category_id=?, price=?, cost_price=?, quantity=?, barcode=?, serial_number=?, image_url=?, updated_at=CURRENT_TIMESTAMP WHERE id=?",
            [$input['name'], $input['description'] ?? '', $input['category_id'], $input['price'], $input['cost_price'] ?? 0, $input['quantity'] ?? 0, $input['barcode'] ?? '', $input['serial_number'] ?? '', $input['image_url'] ?? '', $input['id']]
        );
        jsonResponse(['success' => true]);
    }
    
    if ($action === 'delete') {
        $db->query("UPDATE products SET is_active = 0 WHERE id = ?", [$input['id']]);
        jsonResponse(['success' => true]);
    }
}
