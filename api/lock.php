<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

requireCsrfTokenOrFail();

$_SESSION['screen_locked'] = true;
jsonResponse(['success' => true]);

