<?php
/**
 * POS System Configuration
 * نظام نقاط البيع - إعدادات النظام
 */

// App Settings
define('APP_NAME', 'شركة تــقـنـيـــة');
define('APP_NAME_EN', 'tiqniacom POS');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost:8000');

// Database
define('DB_PATH', __DIR__ . '/database/pos.db');

// Tax Rate (15% VAT)
define('TAX_RATE', 0);

// Currency
define('CURRENCY', 'ج.م');
define('CURRENCY_EN', 'EGP');

// Store Info
define('STORE_NAME', 'تك ستور للإلكترونيات');
define('STORE_ADDRESS', 'شارع الملك فهد، الرياض');
define('STORE_PHONE', '920000000');

// Session
define('SESSION_LIFETIME', 3600 * 8); // 8 hours

// Low stock threshold
define('LOW_STOCK_THRESHOLD', 5);

// Timezone
date_default_timezone_set('Asia/Riyadh');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
