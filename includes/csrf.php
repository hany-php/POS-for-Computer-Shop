<?php
/**
 * CSRF Protection Helper
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function requireCsrfTokenOrFail() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!verifyCsrfToken($token)) {
            http_response_code(419);
            die('خطأ في التحقق من صحة الطلب (CSRF)');
        }
    }
}

function csrfInput() {
    echo '<input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">';
}
