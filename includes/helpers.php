
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
    if (!$date) return '';
    $ts = strtotime((string)$date);
    if ($ts === false) return '';
    $g = formatGregorianDateArLong($ts);
    if (!isHijriDateEnabled()) {
        return $g;
    }
    return $g . ' | ' . formatHijriDateArLong($ts);
}

// Format datetime
function formatDateTimeAr($datetime) {
    if (!$datetime) return '';
    $ts = strtotime((string)$datetime);
    if ($ts === false) return '';
    $g = formatGregorianDateArLong($ts) . ' - ' . date('H:i', $ts);
    if (!isHijriDateEnabled()) {
        return $g;
    }
    return $g . ' | ' . formatHijriDateArLong($ts);
}

function formatDateArShort($date) {
    if (!$date) return '';
    $ts = strtotime((string)$date);
    if ($ts === false) return '';
    $dt = new DateTime('@' . $ts);
    $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));
    if (class_exists('IntlDateFormatter')) {
        $fmt = new IntlDateFormatter(
            'ar',
            IntlDateFormatter::MEDIUM,
            IntlDateFormatter::NONE,
            date_default_timezone_get(),
            IntlDateFormatter::GREGORIAN,
            'd MMM'
        );
        if ($fmt) {
            $formatted = $fmt->format($dt);
            if ($formatted !== false) return $formatted;
        }
    }
    $months = [1 => 'ينا', 'فبر', 'مار', 'أبر', 'ماي', 'يون', 'يول', 'أغس', 'سبت', 'أكت', 'نوف', 'ديس'];
    return intval($dt->format('j')) . ' ' . ($months[intval($dt->format('n'))] ?? '');
}

function formatGregorianDateArLong($timestamp = null) {
    $ts = $timestamp ? intval($timestamp) : time();
    $dt = new DateTime('@' . $ts);
    $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));

    if (class_exists('IntlDateFormatter')) {
        $fmt = new IntlDateFormatter(
            'ar',
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE,
            date_default_timezone_get(),
            IntlDateFormatter::GREGORIAN,
            'EEEE، d MMMM y'
        );
        if ($fmt) {
            $formatted = $fmt->format($dt);
            if ($formatted !== false) {
                return $formatted;
            }
        }
    }

    $days = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
    $months = [1 => 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
    $dayName = $days[intval($dt->format('w'))] ?? '';
    $day = intval($dt->format('j'));
    $month = $months[intval($dt->format('n'))] ?? '';
    $year = $dt->format('Y');
    return $dayName . '، ' . $day . ' ' . $month . ' ' . $year;
}

function formatHijriDateArLong($timestamp = null) {
    $ts = $timestamp ? intval($timestamp) : time();
    $dt = new DateTime('@' . $ts);
    $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));

    if (class_exists('IntlDateFormatter')) {
        $fmt = new IntlDateFormatter(
            'ar-SA@calendar=islamic-umalqura',
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE,
            date_default_timezone_get(),
            IntlDateFormatter::TRADITIONAL,
            'EEEE، d MMMM y'
        );
        if ($fmt) {
            $formatted = $fmt->format($dt);
            if ($formatted !== false) {
                return $formatted . ' هـ';
            }
        }
    }

    // Fallback (civil approximation) when intl extension is unavailable.
    $y = intval($dt->format('Y'));
    $m = intval($dt->format('n'));
    $d = intval($dt->format('j'));

    $jd = gregoriantojd($m, $d, $y);
    $l = $jd - 1948440 + 10632;
    $n = intdiv(($l - 1), 10631);
    $l = $l - 10631 * $n + 354;
    $j = (int)((10985 - $l) / 5316) * (int)((50 * $l) / 17719) + (int)($l / 5670) * (int)((43 * $l) / 15238);
    $l = $l - (int)((30 - $j) / 15) * (int)((17719 * $j) / 50) - (int)($j / 16) * (int)((15238 * $j) / 43) + 29;
    $im = (int)((24 * $l) / 709);
    $id = $l - (int)((709 * $im) / 24);
    $iy = 30 * $n + $j - 30;

    $days = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
    $hijriMonths = [1 => 'محرم', 'صفر', 'ربيع الأول', 'ربيع الآخر', 'جمادى الأولى', 'جمادى الآخرة', 'رجب', 'شعبان', 'رمضان', 'شوال', 'ذو القعدة', 'ذو الحجة'];
    $dayName = $days[intval($dt->format('w'))] ?? '';
    $monthName = $hijriMonths[$im] ?? '';
    return $dayName . '، ' . $id . ' ' . $monthName . ' ' . $iy . ' هـ';
}

// Sanitize input
function sanitize($input) {
    if ($input === null) return '';
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Normalize tax rate from DB/config to decimal format.
// Accepts values like 15 (percent) or 0.15 (decimal) and returns 0.15.
function normalizeTaxRate($rawRate) {
    $rate = floatval($rawRate ?? 0);
    if ($rate < 0) return 0.0;
    if ($rate > 1) return $rate / 100;
    return $rate;
}

// Get tax rate as decimal (e.g. 0.15).
function getTaxRateDecimal() {
    global $db;
    try {
        $enabledRow = $db->fetchOne("SELECT value FROM settings WHERE key = 'tax_enabled' LIMIT 1");
        $isEnabled = true;
        if ($enabledRow && isset($enabledRow['value'])) {
            $isEnabled = in_array(strtolower(trim((string)$enabledRow['value'])), ['1', 'true', 'yes', 'on'], true);
        }
        if (!$isEnabled) {
            return 0.0;
        }

        $row = $db->fetchOne("SELECT value FROM settings WHERE key = 'tax_rate' LIMIT 1");
        if ($row && isset($row['value'])) {
            return normalizeTaxRate($row['value']);
        }
    } catch (Exception $e) {
        // Fallback to config constant if settings table is not ready.
    }
    return normalizeTaxRate(TAX_RATE);
}

function isHijriDateEnabled() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    global $db;
    $cached = true;
    try {
        $row = $db->fetchOne("SELECT value FROM settings WHERE key = 'hijri_date_enabled' LIMIT 1");
        if ($row && isset($row['value'])) {
            $cached = in_array(strtolower(trim((string)$row['value'])), ['1', 'true', 'yes', 'on'], true);
        }
    } catch (Exception $e) {
        $cached = true;
    }
    return $cached;
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

function csvEscapeCell($value) {
    $v = csvTextToUtf8((string)($value ?? ''));
    $v = str_replace('"', '""', $v);
    return '"' . $v . '"';
}

function outputCsvDownload($filename, $headers, $rows) {
    $safeFilename = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$filename);
    if ($safeFilename === '' || $safeFilename === null) {
        $safeFilename = 'export.csv';
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $safeFilename . '"; filename*=UTF-8\'\'' . rawurlencode($safeFilename));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel Arabic support
    echo implode(',', array_map('csvEscapeCell', $headers)) . "\r\n";
    foreach ($rows as $row) {
        echo implode(',', array_map('csvEscapeCell', $row)) . "\r\n";
    }
    exit;
}

function normalizeCsvHeader($h) {
    $h = strtolower(trim(csvTextToUtf8((string)$h)));
    $h = str_replace([' ', '-', '/', '\\', ':'], '_', $h);
    return $h;
}

function csvTextToUtf8($text) {
    $text = (string)$text;
    if ($text === '') {
        return '';
    }

    // UTF-8 BOM
    if (strncmp($text, "\xEF\xBB\xBF", 3) === 0) {
        $text = substr($text, 3);
    }

    // UTF-16 BOM
    if (strncmp($text, "\xFF\xFE", 2) === 0) {
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding(substr($text, 2), 'UTF-8', 'UTF-16LE');
        }
        return iconv('UTF-16LE', 'UTF-8//IGNORE', substr($text, 2));
    }
    if (strncmp($text, "\xFE\xFF", 2) === 0) {
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding(substr($text, 2), 'UTF-8', 'UTF-16BE');
        }
        return iconv('UTF-16BE', 'UTF-8//IGNORE', substr($text, 2));
    }

    if (function_exists('mb_check_encoding') && mb_check_encoding($text, 'UTF-8')) {
        return $text;
    }
    if (!function_exists('mb_check_encoding') && preg_match('//u', $text)) {
        return $text;
    }

    if (function_exists('mb_detect_encoding') && function_exists('mb_convert_encoding')) {
        $detected = mb_detect_encoding($text, ['Windows-1256', 'ISO-8859-6', 'Windows-1252', 'ISO-8859-1'], true);
        if ($detected !== false) {
            return mb_convert_encoding($text, 'UTF-8', $detected);
        }
    }

    $converted = @iconv('Windows-1256', 'UTF-8//IGNORE', $text);
    if ($converted !== false && $converted !== '') {
        return $converted;
    }
    return $text;
}

function parseUploadedCsvAssoc($fileInfo) {
    if (!isset($fileInfo['tmp_name']) || $fileInfo['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('ملف CSV غير صالح');
    }
    $path = $fileInfo['tmp_name'];

    $rawContent = file_get_contents($path);
    if ($rawContent === false) {
        throw new Exception('تعذر قراءة ملف CSV');
    }
    $utf8Content = csvTextToUtf8($rawContent);

    $fh = fopen('php://temp', 'r+');
    if (!$fh) {
        throw new Exception('تعذر قراءة ملف CSV');
    }
    fwrite($fh, $utf8Content);
    rewind($fh);

    $rawHeaders = fgetcsv($fh);
    if (!$rawHeaders || count($rawHeaders) === 0) {
        fclose($fh);
        throw new Exception('ملف CSV فارغ');
    }
    $headers = array_map(function ($h) {
        $h = (string)$h;
        $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);
        return normalizeCsvHeader($h);
    }, $rawHeaders);

    $rows = [];
    while (($data = fgetcsv($fh)) !== false) {
        if ($data === [null] || count(array_filter($data, fn($v) => trim((string)$v) !== '')) === 0) {
            continue;
        }
        $row = [];
        foreach ($headers as $i => $key) {
            $row[$key] = trim(csvTextToUtf8((string)($data[$i] ?? '')));
        }
        $rows[] = $row;
    }
    fclose($fh);
    return $rows;
}

// Resolve and persist a date range per screen using session storage.
function resolveDateRange($sessionKey, $defaultFrom, $defaultTo) {
    $from = $_GET['from'] ?? null;
    $to = $_GET['to'] ?? null;

    $isValid = function ($date) {
        if (!is_string($date)) return false;
        $dt = DateTime::createFromFormat('Y-m-d', $date);
        return $dt && $dt->format('Y-m-d') === $date;
    };

    if ($isValid($from) && $isValid($to)) {
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        $_SESSION['date_range_' . $sessionKey] = ['from' => $from, 'to' => $to];
        return [$from, $to];
    }

    $saved = $_SESSION['date_range_' . $sessionKey] ?? null;
    if (is_array($saved) && $isValid($saved['from'] ?? '') && $isValid($saved['to'] ?? '')) {
        return [$saved['from'], $saved['to']];
    }

    return [$defaultFrom, $defaultTo];
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
        'tax_enabled' => in_array(strtolower(trim((string)($s['tax_enabled'] ?? '1'))), ['1', 'true', 'yes', 'on'], true) ? 1 : 0,
        'hijri_date_enabled' => in_array(strtolower(trim((string)($s['hijri_date_enabled'] ?? '1'))), ['1', 'true', 'yes', 'on'], true) ? 1 : 0,
        'tax_rate' => normalizeTaxRate($s['tax_rate'] ?? TAX_RATE) * 100,
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

// Log timeline events for maintenance tickets.
function logMaintenanceHistory($ticketId, $actionType, $actionLabel, $notes = null) {
    global $db;
    try {
        $db->insert(
            "INSERT INTO maintenance_ticket_history (ticket_id, action_type, action_label, notes, changed_by, changed_by_name)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                intval($ticketId),
                (string)$actionType,
                (string)$actionLabel,
                $notes,
                $_SESSION['user_id'] ?? null,
                $_SESSION['full_name'] ?? 'System'
            ]
        );
    } catch (Exception $e) {
        // Timeline logging should not block the workflow.
    }
}

function getUserFinancePolicy($userId) {
    global $db;
    $userId = intval($userId);
    if ($userId <= 0) return ['require_manager_review' => 1];

    $row = $db->fetchOne("SELECT require_manager_review FROM user_finance_policies WHERE user_id = ?", [$userId]);
    if ($row) {
        return ['require_manager_review' => intval($row['require_manager_review']) === 1 ? 1 : 0];
    }

    $user = $db->fetchOne("SELECT role FROM users WHERE id = ?", [$userId]);
    $defaultRequire = (($user['role'] ?? '') === 'cashier') ? 1 : 0;
    $db->query(
        "INSERT OR IGNORE INTO user_finance_policies (user_id, require_manager_review, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)",
        [$userId, $defaultRequire]
    );
    return ['require_manager_review' => $defaultRequire];
}

function setUserFinancePolicy($userId, $requireManagerReview) {
    global $db;
    $db->query(
        "INSERT INTO user_finance_policies (user_id, require_manager_review, updated_by, updated_at)
         VALUES (?, ?, ?, CURRENT_TIMESTAMP)
         ON CONFLICT(user_id) DO UPDATE SET
         require_manager_review = excluded.require_manager_review,
         updated_by = excluded.updated_by,
         updated_at = CURRENT_TIMESTAMP",
        [intval($userId), intval($requireManagerReview) ? 1 : 0, $_SESSION['user_id'] ?? null]
    );
}

function getOrCreateActiveCashierCycle($userId) {
    global $db;
    $userId = intval($userId);
    if ($userId <= 0) return null;

    $cycle = $db->fetchOne(
        "SELECT * FROM cashier_cycles
         WHERE user_id = ? AND status IN ('open','pending_review')
         ORDER BY id DESC LIMIT 1",
        [$userId]
    );
    if ($cycle) return $cycle;

    $newId = $db->insert(
        "INSERT INTO cashier_cycles (user_id, status, opened_at) VALUES (?, 'open', CURRENT_TIMESTAMP)",
        [$userId]
    );
    return $db->fetchOne("SELECT * FROM cashier_cycles WHERE id = ?", [$newId]);
}

function applySaleToCashierCycle($userId, $paymentMethod, $total) {
    global $db;
    $cycle = getOrCreateActiveCashierCycle($userId);
    if (!$cycle) return;

    $cashDelta = 0.0;
    $cardDelta = 0.0;
    $transferDelta = 0.0;
    $method = strtolower(trim((string)$paymentMethod));
    if ($method === 'cash') {
        $cashDelta = $total;
    } elseif ($method === 'card') {
        $cardDelta = $total;
    } else {
        $transferDelta = $total;
    }

    $db->query(
        "UPDATE cashier_cycles SET
            cash_sales_total = cash_sales_total + ?,
            card_sales_total = card_sales_total + ?,
            transfer_sales_total = transfer_sales_total + ?,
            total_sales = total_sales + ?,
            net_total = total_sales + ? - refunds_total,
            orders_count = orders_count + 1
         WHERE id = ?",
        [$cashDelta, $cardDelta, $transferDelta, $total, $total, $cycle['id']]
    );
}

function applyRefundToCashierCycle($userId, $total) {
    global $db;
    $cycle = getOrCreateActiveCashierCycle($userId);
    if (!$cycle) return;

    $db->query(
        "UPDATE cashier_cycles SET
            refunds_total = refunds_total + ?,
            net_total = total_sales - (refunds_total + ?)
         WHERE id = ?",
        [$total, $total, $cycle['id']]
    );
}

function requestCashierCycleClose($userId, $note = '') {
    global $db;
    $userId = intval($userId);
    $note = trim((string)$note);
    $cycle = getOrCreateActiveCashierCycle($userId);
    if (!$cycle) {
        return ['success' => false, 'error' => 'تعذر العثور على دورة نشطة'];
    }
    if (($cycle['status'] ?? '') === 'pending_review') {
        return ['success' => false, 'error' => 'هناك طلب إقفال قيد المراجعة بالفعل'];
    }

    $policy = getUserFinancePolicy($userId);
    if (intval($policy['require_manager_review']) === 1) {
        $db->query(
            "UPDATE cashier_cycles
             SET status = 'pending_review', close_request_note = ?, close_requested_at = CURRENT_TIMESTAMP
             WHERE id = ?",
            [$note, $cycle['id']]
        );
        return ['success' => true, 'mode' => 'pending_review', 'message' => 'تم إرسال طلب الإقفال لمراجعة المدير'];
    }

    $db->query(
        "UPDATE cashier_cycles
         SET status = 'closed', reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP, review_note = ?, closed_at = CURRENT_TIMESTAMP
         WHERE id = ?",
        [$userId, ($note !== '' ? $note : 'إقفال مباشر - دورة كاملة'), $cycle['id']]
    );
    $newCycleId = $db->insert(
        "INSERT INTO cashier_cycles (user_id, status, opened_at) VALUES (?, 'open', CURRENT_TIMESTAMP)",
        [$userId]
    );
    return ['success' => true, 'mode' => 'full_cycle', 'message' => 'تم إغلاق الدورة وفتح دورة جديدة', 'new_cycle_id' => $newCycleId];
}

function reviewCashierCycleClose($cycleId, $approve, $reviewNote = '') {
    global $db;
    $cycleId = intval($cycleId);
    $cycle = $db->fetchOne("SELECT * FROM cashier_cycles WHERE id = ?", [$cycleId]);
    if (!$cycle) return ['success' => false, 'error' => 'الدورة غير موجودة'];
    if (($cycle['status'] ?? '') !== 'pending_review') return ['success' => false, 'error' => 'هذه الدورة ليست في حالة انتظار مراجعة'];

    $reviewerId = $_SESSION['user_id'] ?? null;
    $reviewNote = trim((string)$reviewNote);
    if ($approve) {
        $db->query(
            "UPDATE cashier_cycles
             SET status = 'closed', reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP, review_note = ?, closed_at = CURRENT_TIMESTAMP
             WHERE id = ?",
            [$reviewerId, $reviewNote, $cycleId]
        );
        $db->insert(
            "INSERT INTO cashier_cycles (user_id, status, opened_at) VALUES (?, 'open', CURRENT_TIMESTAMP)",
            [$cycle['user_id']]
        );
        return ['success' => true, 'message' => 'تم اعتماد الإقفال وفتح دورة جديدة للمستخدم'];
    }

    $db->query(
        "UPDATE cashier_cycles
         SET status = 'open', reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP, review_note = ?, close_requested_at = NULL
         WHERE id = ?",
        [$reviewerId, ($reviewNote !== '' ? $reviewNote : 'تم رفض الإقفال وإعادة الدورة مفتوحة'), $cycleId]
    );
    return ['success' => true, 'message' => 'تم رفض الإقفال وإعادة الدورة للحالة المفتوحة'];
}

function generateJournalEntryNumber() {
    return 'JE-' . date('ymd-His') . '-' . substr((string)microtime(true), -4);
}

function createJournalEntry($refType, $refId, $refNumber, $description, $lines, $createdBy = null) {
    global $db;
    $refType = (string)$refType;
    $refId = intval($refId);
    if ($refType !== '' && $refId > 0) {
        $existing = $db->fetchOne("SELECT id FROM journal_entries WHERE ref_type = ? AND ref_id = ? LIMIT 1", [$refType, $refId]);
        if ($existing) {
            return intval($existing['id']);
        }
    }

    $totalDebit = 0.0;
    $totalCredit = 0.0;
    foreach ($lines as $line) {
        $totalDebit += floatval($line['debit'] ?? 0);
        $totalCredit += floatval($line['credit'] ?? 0);
    }
    if (abs($totalDebit - $totalCredit) > 0.0001) {
        throw new Exception('القيد المحاسبي غير متوازن');
    }

    $entryId = $db->insert(
        "INSERT INTO journal_entries (entry_number, ref_type, ref_id, ref_number, description, created_by)
         VALUES (?, ?, ?, ?, ?, ?)",
        [generateJournalEntryNumber(), $refType, ($refId > 0 ? $refId : null), $refNumber, $description, $createdBy]
    );

    foreach ($lines as $line) {
        $db->insert(
            "INSERT INTO journal_lines (entry_id, account_code, debit, credit, line_note) VALUES (?, ?, ?, ?, ?)",
            [
                $entryId,
                $line['account_code'],
                floatval($line['debit'] ?? 0),
                floatval($line['credit'] ?? 0),
                $line['note'] ?? null
            ]
        );
    }
    return $entryId;
}

function postSaleAccountingEntry($orderId) {
    global $db;
    $order = $db->fetchOne(
        "SELECT id, order_number, user_id, subtotal, tax_amount, discount_amount, total, payment_method, payment_received
         FROM orders WHERE id = ? LIMIT 1",
        [intval($orderId)]
    );
    if (!$order) return null;

    $total = floatval($order['total'] ?? 0);
    $received = max(0, floatval($order['payment_received'] ?? 0));
    $collected = min($total, $received);
    $due = max(0, $total - $collected);
    $tax = floatval($order['tax_amount'] ?? 0);
    $salesNet = max(0, floatval($order['subtotal'] ?? 0) - floatval($order['discount_amount'] ?? 0));
    $paymentMethod = strtolower((string)($order['payment_method'] ?? 'cash'));
    $cashAccount = ($paymentMethod === 'cash') ? '1010' : '1020';

    $lines = [];
    if ($collected > 0) {
        $lines[] = ['account_code' => $cashAccount, 'debit' => $collected, 'credit' => 0, 'note' => 'تحصيل قيمة الفاتورة'];
    }
    if ($due > 0) {
        $lines[] = ['account_code' => '1040', 'debit' => $due, 'credit' => 0, 'note' => 'ذمم عملاء - دين مبيعات'];
    }
    $lines[] = ['account_code' => '4010', 'debit' => 0, 'credit' => $salesNet, 'note' => 'إيراد المبيعات'];
    if ($tax > 0) {
        $lines[] = ['account_code' => '2010', 'debit' => 0, 'credit' => $tax, 'note' => 'ضريبة مخرجات'];
    }

    $entryId = createJournalEntry('sale', intval($order['id']), $order['order_number'], 'قيد بيع تلقائي', $lines, $order['user_id'] ?? null);
    applySaleToCashierCycle($order['user_id'] ?? 0, $order['payment_method'] ?? 'cash', $collected);
    return $entryId;
}

function postRefundAccountingEntry($orderId) {
    global $db;
    $order = $db->fetchOne(
        "SELECT id, order_number, user_id, subtotal, tax_amount, discount_amount, total, payment_method, payment_received
         FROM orders WHERE id = ? LIMIT 1",
        [intval($orderId)]
    );
    if (!$order) return null;

    $total = floatval($order['total'] ?? 0);
    $received = max(0, floatval($order['payment_received'] ?? 0));
    $collected = min($total, $received);
    $due = max(0, $total - $collected);
    $tax = floatval($order['tax_amount'] ?? 0);
    $salesNet = max(0, floatval($order['subtotal'] ?? 0) - floatval($order['discount_amount'] ?? 0));
    $paymentMethod = strtolower((string)($order['payment_method'] ?? 'cash'));
    $cashAccount = ($paymentMethod === 'cash') ? '1010' : '1020';

    $lines = [
        ['account_code' => '5010', 'debit' => $salesNet, 'credit' => 0, 'note' => 'مردودات مبيعات']
    ];
    if ($collected > 0) {
        $lines[] = ['account_code' => $cashAccount, 'debit' => 0, 'credit' => $collected, 'note' => 'رد الجزء المحصل من الفاتورة'];
    }
    if ($due > 0) {
        $lines[] = ['account_code' => '1040', 'debit' => 0, 'credit' => $due, 'note' => 'عكس ذمم عملاء'];
    }
    if ($tax > 0) {
        $lines[] = ['account_code' => '2010', 'debit' => $tax, 'credit' => 0, 'note' => 'عكس ضريبة المخرجات'];
    }

    $entryId = createJournalEntry('refund', intval($order['id']), $order['order_number'], 'قيد مرتجع تلقائي', $lines, $order['user_id'] ?? null);
    applyRefundToCashierCycle($order['user_id'] ?? 0, $collected);
    return $entryId;
}

function getLiquidityAccountCodeByPaymentMethod($paymentMethod) {
    $method = strtolower(trim((string)$paymentMethod));
    return $method === 'cash' ? '1010' : '1020';
}

function recordTreasuryTransaction($txnType, $accountCode, $amount, $source = null, $paymentMethod = 'cash', $refType = null, $refId = null, $notes = null) {
    global $db;
    $amount = floatval($amount);
    if ($amount <= 0) {
        throw new Exception('قيمة الحركة يجب أن تكون أكبر من صفر');
    }
    $txnType = ($txnType === 'in') ? 'in' : 'out';
    $db->insert(
        "INSERT INTO treasury_transactions (txn_type, account_code, amount, source, payment_method, ref_type, ref_id, notes, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $txnType,
            $accountCode,
            $amount,
            $source,
            $paymentMethod,
            $refType,
            $refId,
            $notes,
            $_SESSION['user_id'] ?? null
        ]
    );
}

function getAccountBalance($accountCode) {
    global $db;
    $row = $db->fetchOne(
        "SELECT COALESCE(SUM(debit - credit), 0) AS balance FROM journal_lines WHERE account_code = ?",
        [$accountCode]
    );
    return floatval($row['balance'] ?? 0);
}

function postExpenseAccountingEntry($expenseId) {
    global $db;
    $expense = $db->fetchOne(
        "SELECT e.*, c.name AS category_name
         FROM expenses e
         LEFT JOIN expense_categories c ON c.id = e.category_id
         WHERE e.id = ?",
        [intval($expenseId)]
    );
    if (!$expense) {
        throw new Exception('المصروف غير موجود');
    }

    $amount = floatval($expense['amount'] ?? 0);
    if ($amount <= 0) {
        throw new Exception('قيمة المصروف غير صالحة');
    }

    $liquidityAccount = getLiquidityAccountCodeByPaymentMethod($expense['payment_method'] ?? 'cash');
    $description = 'قيد مصروف تلقائي';
    if (!empty($expense['category_name'])) {
        $description .= ' - ' . $expense['category_name'];
    }
    if (!empty($expense['notes'])) {
        $description .= ' - ' . mb_substr((string)$expense['notes'], 0, 120);
    }

    $entryId = createJournalEntry(
        'expense',
        intval($expense['id']),
        'EXP-' . intval($expense['id']),
        $description,
        [
            ['account_code' => '6010', 'debit' => $amount, 'credit' => 0, 'note' => 'مصروف'],
            ['account_code' => $liquidityAccount, 'debit' => 0, 'credit' => $amount, 'note' => 'سداد المصروف']
        ],
        $expense['created_by'] ?? null
    );

    recordTreasuryTransaction(
        'out',
        $liquidityAccount,
        $amount,
        'expense',
        $expense['payment_method'] ?? 'cash',
        'expense',
        intval($expense['id']),
        $expense['notes'] ?? null
    );

    return $entryId;
}

function postManualTreasuryEntry($txnType, $amount, $paymentMethod, $notes = '') {
    $amount = floatval($amount);
    if ($amount <= 0) {
        throw new Exception('المبلغ غير صالح');
    }
    $txnType = ($txnType === 'in') ? 'in' : 'out';
    $liqAccount = getLiquidityAccountCodeByPaymentMethod($paymentMethod);

    if ($txnType === 'in') {
        $lines = [
            ['account_code' => $liqAccount, 'debit' => $amount, 'credit' => 0, 'note' => 'قبض خزنة'],
            ['account_code' => '3010', 'debit' => 0, 'credit' => $amount, 'note' => 'مقابل قبض خزنة']
        ];
    } else {
        $lines = [
            ['account_code' => '3010', 'debit' => $amount, 'credit' => 0, 'note' => 'مقابل صرف خزنة'],
            ['account_code' => $liqAccount, 'debit' => 0, 'credit' => $amount, 'note' => 'صرف خزنة']
        ];
    }

    $entryId = createJournalEntry(
        'treasury',
        0,
        null,
        ($txnType === 'in' ? 'قبض خزنة' : 'صرف خزنة') . ($notes ? ' - ' . mb_substr($notes, 0, 120) : ''),
        $lines,
        $_SESSION['user_id'] ?? null
    );

    recordTreasuryTransaction($txnType, $liqAccount, $amount, 'manual', $paymentMethod, 'journal_entry', $entryId, $notes);
    return $entryId;
}

function generatePurchaseInvoiceNumber() {
    global $db;
    $prefix = 'PUR';
    $date = date('ymd');
    $last = $db->fetchOne(
        "SELECT invoice_number FROM purchase_invoices WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 1",
        ["$prefix-$date-%"]
    );
    if ($last && !empty($last['invoice_number'])) {
        $num = intval(substr((string)$last['invoice_number'], -4)) + 1;
    } else {
        $num = 1;
    }
    return sprintf('%s-%s-%04d', $prefix, $date, $num);
}

function recalcSupplierBalance($supplierId) {
    global $db;
    $supplierId = intval($supplierId);
    if ($supplierId <= 0) return 0.0;
    $row = $db->fetchOne(
        "SELECT COALESCE(SUM(due_amount), 0) AS due_total
         FROM purchase_invoices
         WHERE supplier_id = ?",
        [$supplierId]
    );
    $balance = floatval($row['due_total'] ?? 0);
    $db->query(
        "UPDATE suppliers SET current_balance = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
        [$balance, $supplierId]
    );
    return $balance;
}

function postPurchaseInvoiceAccountingEntry($invoiceId) {
    global $db;
    $invoice = $db->fetchOne(
        "SELECT * FROM purchase_invoices WHERE id = ? LIMIT 1",
        [intval($invoiceId)]
    );
    if (!$invoice) {
        throw new Exception('فاتورة الشراء غير موجودة');
    }

    $total = floatval($invoice['total_amount'] ?? 0);
    $paid = floatval($invoice['paid_amount'] ?? 0);
    $due = max(0, $total - $paid);
    if ($total <= 0) {
        throw new Exception('إجمالي فاتورة الشراء غير صالح');
    }

    $liqAccount = getLiquidityAccountCodeByPaymentMethod($invoice['payment_method'] ?? 'cash');
    $lines = [
        ['account_code' => '1030', 'debit' => $total, 'credit' => 0, 'note' => 'إضافة مخزون من مشتريات']
    ];
    if ($paid > 0) {
        $lines[] = ['account_code' => $liqAccount, 'debit' => 0, 'credit' => $paid, 'note' => 'سداد فوري للمورد'];
    }
    if ($due > 0) {
        $lines[] = ['account_code' => '2020', 'debit' => 0, 'credit' => $due, 'note' => 'ذمم موردين'];
    }

    $entryId = createJournalEntry(
        'purchase_invoice',
        intval($invoice['id']),
        $invoice['invoice_number'],
        'قيد شراء تلقائي',
        $lines,
        $invoice['created_by'] ?? null
    );

    if ($paid > 0) {
        recordTreasuryTransaction(
            'out',
            $liqAccount,
            $paid,
            'purchase_invoice',
            $invoice['payment_method'] ?? 'cash',
            'purchase_invoice',
            intval($invoice['id']),
            'سداد جزء من فاتورة شراء'
        );
    }
    recalcSupplierBalance(intval($invoice['supplier_id']));
    return $entryId;
}

function postSupplierPaymentAccountingEntry($paymentId) {
    global $db;
    $payment = $db->fetchOne(
        "SELECT * FROM supplier_payments WHERE id = ? LIMIT 1",
        [intval($paymentId)]
    );
    if (!$payment) throw new Exception('دفعة المورد غير موجودة');

    $amount = floatval($payment['amount'] ?? 0);
    if ($amount <= 0) throw new Exception('قيمة دفعة المورد غير صالحة');
    $liqAccount = getLiquidityAccountCodeByPaymentMethod($payment['payment_method'] ?? 'cash');

    $entryId = createJournalEntry(
        'supplier_payment',
        intval($payment['id']),
        'SUP-PAY-' . intval($payment['id']),
        'سداد مورد',
        [
            ['account_code' => '2020', 'debit' => $amount, 'credit' => 0, 'note' => 'تخفيض ذمم الموردين'],
            ['account_code' => $liqAccount, 'debit' => 0, 'credit' => $amount, 'note' => 'صرف سيولة لسداد المورد']
        ],
        $payment['created_by'] ?? null
    );

    recordTreasuryTransaction(
        'out',
        $liqAccount,
        $amount,
        'supplier_payment',
        $payment['payment_method'] ?? 'cash',
        'supplier_payment',
        intval($payment['id']),
        $payment['notes'] ?? 'سداد مورد'
    );

    recalcSupplierBalance(intval($payment['supplier_id']));
    return $entryId;
}

function postCustomerDuePaymentAccountingEntry($paymentId) {
    global $db;
    $payment = $db->fetchOne(
        "SELECT * FROM customer_due_payments WHERE id = ? LIMIT 1",
        [intval($paymentId)]
    );
    if (!$payment) throw new Exception('سداد العميل غير موجود');

    $amount = floatval($payment['amount'] ?? 0);
    if ($amount <= 0) throw new Exception('قيمة السداد غير صالحة');
    $liqAccount = getLiquidityAccountCodeByPaymentMethod($payment['payment_method'] ?? 'cash');

    $entryId = createJournalEntry(
        'customer_due_payment',
        intval($payment['id']),
        'CUST-PAY-' . intval($payment['id']),
        'تحصيل دين عميل',
        [
            ['account_code' => $liqAccount, 'debit' => $amount, 'credit' => 0, 'note' => 'تحصيل نقدي/بنكي من العميل'],
            ['account_code' => '1040', 'debit' => 0, 'credit' => $amount, 'note' => 'تخفيض ذمم العملاء']
        ],
        $payment['created_by'] ?? null
    );

    recordTreasuryTransaction(
        'in',
        $liqAccount,
        $amount,
        'customer_due_payment',
        $payment['payment_method'] ?? 'cash',
        'customer_due_payment',
        intval($payment['id']),
        $payment['notes'] ?? 'تحصيل دين عميل'
    );

    return $entryId;
}
