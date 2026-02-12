<?php
/**
 * Bootstrap file - include this in every page
 * ملف التهيئة - يتم تضمينه في كل صفحة
 */
session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/helpers.php';

// Initialize database
$db = Database::getInstance();
