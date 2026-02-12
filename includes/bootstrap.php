<?php
/**
 * Bootstrap file - include this in every page
 * ملف التهيئة - يتم تضمينه في كل صفحة
 */
// Set secure session cookie parameters
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';

// Initialize database
$db = Database::getInstance();
