<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

requireCsrfTokenOrFail();

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;
$action = trim((string)($input['action'] ?? ''));

if ($action === 'close_request') {
    $userId = intval($_SESSION['user_id'] ?? 0);
    $note = trim((string)($input['note'] ?? ''));
    $res = requestCashierCycleClose($userId, $note);
    if (!($res['success'] ?? false)) {
        jsonResponse(['success' => false, 'error' => $res['error'] ?? 'تعذر تنفيذ طلب الإقفال'], 400);
    }
    logActivity('طلب إقفال دورة كاشير', 'cashier_cycle', null, ($res['mode'] ?? 'unknown') . ' - user:' . $userId);
    jsonResponse(['success' => true, 'mode' => $res['mode'] ?? null, 'message' => $res['message'] ?? 'تم التنفيذ']);
}

if ($action === 'current_status') {
    $userId = intval($_SESSION['user_id'] ?? 0);
    $cycle = getOrCreateActiveCashierCycle($userId);
    $policy = getUserFinancePolicy($userId);
    jsonResponse([
        'success' => true,
        'cycle' => $cycle,
        'policy' => $policy
    ]);
}

if ($action === 'review' && Auth::hasRole(['admin'])) {
    $cycleId = intval($input['cycle_id'] ?? 0);
    $approve = intval($input['approve'] ?? 0) === 1;
    $reviewNote = trim((string)($input['review_note'] ?? ''));
    $res = reviewCashierCycleClose($cycleId, $approve, $reviewNote);
    if (!($res['success'] ?? false)) {
        jsonResponse(['success' => false, 'error' => $res['error'] ?? 'تعذر مراجعة الدورة'], 400);
    }
    logActivity($approve ? 'اعتماد إقفال دورة' : 'رفض إقفال دورة', 'cashier_cycle', $cycleId, $reviewNote);
    jsonResponse(['success' => true, 'message' => $res['message'] ?? 'تمت المراجعة']);
}

jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);

