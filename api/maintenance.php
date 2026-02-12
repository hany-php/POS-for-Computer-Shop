<?php
/**
 * Maintenance API
 * واجهة برمجة الصيانة
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'create') {
        $ticketNumber = $db->generateTicketNumber();
        $id = $db->insert(
            "INSERT INTO maintenance_tickets (ticket_number, customer_name, customer_phone, device_type, device_brand, device_model, serial_number, problem_description, estimated_cost, technician_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$ticketNumber, $input['customer_name'], $input['customer_phone'], $input['device_type'], $input['device_brand'] ?? '', $input['device_model'] ?? '', $input['serial_number'] ?? '', $input['problem_description'], floatval($input['estimated_cost'] ?? 0), $input['technician_id'] ?? null]
        );
        jsonResponse(['success' => true, 'id' => $id, 'ticket_number' => $ticketNumber]);
    }
    
    if ($action === 'update_status') {
        $db->query("UPDATE maintenance_tickets SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$input['status'], $input['id']]);
        jsonResponse(['success' => true]);
    }
    
    if ($action === 'update') {
        $db->query(
            "UPDATE maintenance_tickets SET customer_name=?, customer_phone=?, device_type=?, device_brand=?, device_model=?, serial_number=?, problem_description=?, estimated_cost=?, actual_cost=?, technician_id=?, notes=?, updated_at=CURRENT_TIMESTAMP WHERE id=?",
            [$input['customer_name'], $input['customer_phone'], $input['device_type'], $input['device_brand'] ?? '', $input['device_model'] ?? '', $input['serial_number'] ?? '', $input['problem_description'], floatval($input['estimated_cost'] ?? 0), floatval($input['actual_cost'] ?? 0), $input['technician_id'] ?? null, $input['notes'] ?? '', $input['id']]
        );
        jsonResponse(['success' => true]);
    }
}
