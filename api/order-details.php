<?php
/**
 * Order Details API
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
header('Content-Type: application/json; charset=utf-8');

$id = intval($_GET['id'] ?? 0);
if (!$id) jsonResponse(['success' => false, 'error' => 'Invalid ID'], 400);

$order = $db->fetchOne("SELECT * FROM orders WHERE id = ?", [$id]);
$items = $db->fetchAll("SELECT * FROM order_items WHERE order_id = ?", [$id]);

if (!$order) jsonResponse(['success' => false, 'error' => 'Order not found'], 404);

jsonResponse(['success' => true, 'order' => $order, 'items' => $items]);
