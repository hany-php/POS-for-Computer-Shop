<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

requireCsrfTokenOrFail();

$secret = trim($_POST['secret'] ?? '');
$auth = new Auth();

if ($auth->unlockSession($secret)) {
    $_SESSION['screen_locked'] = false;
    $_SESSION['unlock_time'] = time();
    jsonResponse(['success' => true]);
}

jsonResponse(['success' => false, 'error' => 'بيانات الدخول غير صحيحة'], 401);
