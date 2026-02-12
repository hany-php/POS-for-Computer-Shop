
<?php
/**
 * Helper Functions
 * الدوال المساعدة
 */

// Format currency
function formatPrice($amount) {
    return number_format($amount, 2) . ' ' . CURRENCY;
}

// Format date in Arabic
function formatDateAr($date) {
    return date('Y/m/d', strtotime($date));
}

// Format datetime
function formatDateTimeAr($datetime) {
    return date('Y/m/d H:i', strtotime($datetime));
}

// Sanitize input
function sanitize($input) {
    if ($input === null) return '';
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Flash message
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Get condition name in Arabic
function getConditionAr($condition) {
    $conditions = [
        'excellent' => 'ممتاز',
        'very_good' => 'جيد جداً',
        'good' => 'جيد',
        'acceptable' => 'مقبول',
        'damaged' => 'تالف'
    ];
    return $conditions[$condition] ?? $condition;
}

// Get condition color class
function getConditionColor($condition) {
    $colors = [
        'excellent' => 'bg-green-100 text-green-800',
        'very_good' => 'bg-blue-100 text-blue-800',
        'good' => 'bg-yellow-100 text-yellow-800',
        'acceptable' => 'bg-orange-100 text-orange-800',
        'damaged' => 'bg-red-100 text-red-800'
    ];
    return $colors[$condition] ?? 'bg-slate-100 text-slate-800';
}

// Get maintenance status in Arabic
function getMaintenanceStatusAr($status) {
    $statuses = [
        'pending_inspection' => 'قيد الفحص',
        'under_maintenance' => 'تحت الصيانة',
        'ready_for_pickup' => 'جاهز للاستلام',
        'delivered' => 'تم التسليم',
        'cancelled' => 'ملغي'
    ];
    return $statuses[$status] ?? $status;
}

// Get maintenance status color
function getMaintenanceStatusColor($status) {
    $colors = [
        'pending_inspection' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
        'under_maintenance' => 'bg-blue-100 text-blue-800 border-blue-200',
        'ready_for_pickup' => 'bg-green-100 text-green-800 border-green-200',
        'delivered' => 'bg-slate-100 text-slate-600 border-slate-200',
        'cancelled' => 'bg-red-100 text-red-800 border-red-200'
    ];
    return $colors[$status] ?? 'bg-slate-100 text-slate-800';
}

// Get payment method in Arabic
function getPaymentMethodAr($method) {
    $methods = [
        'cash' => 'نقدي',
        'card' => 'بطاقة',
        'transfer' => 'تحويل / محفظة',
        'split' => 'دفع مقسم'
    ];
    return $methods[$method] ?? $method;
}

// JSON response helper
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Get store settings from DB (with fallback to config constants)
function getStoreSettings() {
    global $db;
    $raw = $db->fetchAll("SELECT key, value FROM settings");
    $s = [];
    foreach ($raw as $r) $s[$r['key']] = $r['value'];
    return [
        'store_name' => $s['store_name'] ?? STORE_NAME,
        'store_address' => $s['store_address'] ?? '',
        'store_phone' => $s['store_phone'] ?? STORE_PHONE,
        'store_email' => $s['store_email'] ?? '',
        'store_logo_url' => $s['store_logo_url'] ?? '',
        'currency' => $s['currency'] ?? CURRENCY,
        'tax_rate' => $s['tax_rate'] ?? (TAX_RATE * 100),
        'receipt_footer' => $s['receipt_footer'] ?? 'شكراً لتعاملكم معنا',
        'print_type' => $s['print_type'] ?? 'thermal',
    ];
}

// Log activity to audit_log table
function logActivity($action, $entityType = null, $entityId = null, $details = null) {
    global $db;
    try {
        $userId = $_SESSION['user_id'] ?? null;
        $userName = $_SESSION['full_name'] ?? 'نظام';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $db->insert(
            "INSERT INTO audit_log (user_id, user_name, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$userId, $userName, $action, $entityType, $entityId, $details, $ip]
        );
    } catch (Exception $e) {
        // Silently fail - logging should not break the app
    }
}
