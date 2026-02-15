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

// Environment and error reporting
$appEnv = getenv('APP_ENV') ?: 'development';
define('APP_ENV', $appEnv);
define('ERROR_LOG_PATH', __DIR__ . '/logs/app.log');
if (!is_dir(__DIR__ . '/logs')) {
    @mkdir(__DIR__ . '/logs', 0777, true);
}
ini_set('log_errors', '1');
ini_set('error_log', ERROR_LOG_PATH);
if (APP_ENV === 'production') {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}
