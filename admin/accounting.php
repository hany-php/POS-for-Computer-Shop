<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole(['admin']);
$pageTitle = 'المحاسبة والمالية';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCsrfTokenOrFail();

    if ($_POST['action'] === 'save_policy') {
        $userId = intval($_POST['user_id'] ?? 0);
        $requireReview = intval($_POST['require_manager_review'] ?? 1) === 1 ? 1 : 0;
        setUserFinancePolicy($userId, $requireReview);
        logActivity('تحديث سياسة دورة المستخدم', 'user', $userId, 'require_review=' . $requireReview);
        setFlash('success', 'تم تحديث سياسة الإقفال للمستخدم');
        header('Location: accounting.php');
        exit;
    }

    if ($_POST['action'] === 'review_cycle') {
        $cycleId = intval($_POST['cycle_id'] ?? 0);
        $approve = intval($_POST['approve'] ?? 0) === 1;
        $reviewNote = trim((string)($_POST['review_note'] ?? ''));
        $res = reviewCashierCycleClose($cycleId, $approve, $reviewNote);
        if ($res['success'] ?? false) {
            setFlash('success', $res['message']);
        } else {
            setFlash('error', $res['error'] ?? 'تعذر مراجعة الدورة');
        }
        header('Location: accounting.php');
        exit;
    }
}

$cashiers = $db->fetchAll(
    "SELECT u.id, u.full_name, u.username, u.role, COALESCE(p.require_manager_review, CASE WHEN u.role='cashier' THEN 1 ELSE 0 END) AS require_manager_review
     FROM users u
     LEFT JOIN user_finance_policies p ON p.user_id = u.id
     WHERE u.is_active = 1
     ORDER BY CASE u.role WHEN 'admin' THEN 1 WHEN 'cashier' THEN 2 ELSE 3 END, u.full_name"
);

$pendingCycles = $db->fetchAll(
    "SELECT c.*, u.full_name, u.username
     FROM cashier_cycles c
     JOIN users u ON u.id = c.user_id
     WHERE c.status = 'pending_review'
     ORDER BY c.close_requested_at ASC"
);

$recentEntries = $db->fetchAll(
    "SELECT je.id, je.entry_number, je.ref_type, je.ref_number, je.description, je.created_at, u.full_name AS user_name,
            COALESCE((SELECT SUM(debit) FROM journal_lines jl WHERE jl.entry_id = je.id), 0) AS total_debit
     FROM journal_entries je
     LEFT JOIN users u ON u.id = je.created_by
     ORDER BY je.id DESC
     LIMIT 30"
);

include __DIR__ . '/../includes/header.php';
?>

<div class="flex h-screen overflow-hidden">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="flex-1 overflow-y-auto bg-background-light">
        <header class="bg-white border-b border-slate-200 px-6 py-5">
            <h1 class="text-2xl font-bold text-slate-900">المحاسبة والمالية</h1>
            <p class="text-sm text-slate-500 mt-1">سياسات إقفال دورات المستخدمين + مراجعة الطلبات + قيود محاسبية تلقائية</p>
        </header>

        <div class="p-6 space-y-6">
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="p-4 border-b border-slate-100">
                    <h3 class="font-bold text-slate-900">سياسة إقفال الدورة لكل مستخدم</h3>
                </div>
                <div class="divide-y divide-slate-100">
                    <?php foreach ($cashiers as $u): ?>
                    <form method="POST" class="p-4 grid grid-cols-1 md:grid-cols-5 gap-3 items-center">
                        <input type="hidden" name="action" value="save_policy">
                        <input type="hidden" name="user_id" value="<?= intval($u['id']) ?>">
                        <?php csrfInput(); ?>
                        <div class="md:col-span-2">
                            <p class="font-semibold text-slate-800"><?= sanitize($u['full_name']) ?></p>
                            <p class="text-xs text-slate-400 font-num"><?= sanitize($u['username']) ?> · <?= sanitize(Auth::getRoleNameAr($u['role'])) ?></p>
                        </div>
                        <div class="md:col-span-2">
                            <select name="require_manager_review" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm">
                                <option value="1" <?= intval($u['require_manager_review']) === 1 ? 'selected' : '' ?>>يحتاج مراجعة المدير قبل الإقفال</option>
                                <option value="0" <?= intval($u['require_manager_review']) === 0 ? 'selected' : '' ?>>دورة كاملة تلقائيًا للمستخدم</option>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="w-full bg-primary text-white rounded-lg py-2 text-sm font-medium hover:bg-primary-dark">حفظ</button>
                        </div>
                    </form>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="p-4 border-b border-slate-100">
                    <h3 class="font-bold text-slate-900">طلبات إقفال الدورات (تحتاج مراجعة)</h3>
                </div>
                <?php if (empty($pendingCycles)): ?>
                <div class="p-8 text-center text-slate-400">لا توجد طلبات حالياً</div>
                <?php else: ?>
                <div class="divide-y divide-slate-100">
                    <?php foreach ($pendingCycles as $c): ?>
                    <div class="p-4 space-y-3">
                        <div class="flex flex-wrap gap-3 justify-between">
                            <div>
                                <p class="font-semibold text-slate-900"><?= sanitize($c['full_name']) ?> <span class="text-xs text-slate-400 font-num">(<?= sanitize($c['username']) ?>)</span></p>
                                <p class="text-xs text-slate-500">فتح: <?= sanitize(formatDateTimeAr($c['opened_at'])) ?> | طلب إقفال: <?= sanitize(!empty($c['close_requested_at']) ? formatDateTimeAr($c['close_requested_at']) : '—') ?></p>
                            </div>
                            <div class="text-sm text-slate-600 font-num">
                                <p>مبيعات: <?= number_format(floatval($c['total_sales']), 2) ?></p>
                                <p>مرتجعات: <?= number_format(floatval($c['refunds_total']), 2) ?></p>
                                <p class="font-bold text-slate-900">صافي: <?= number_format(floatval($c['net_total']), 2) ?></p>
                            </div>
                        </div>
                        <?php if (!empty($c['close_request_note'])): ?>
                        <p class="text-xs text-slate-500 bg-slate-50 border border-slate-200 rounded-lg p-2"><?= sanitize($c['close_request_note']) ?></p>
                        <?php endif; ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            <form method="POST" class="flex gap-2">
                                <input type="hidden" name="action" value="review_cycle">
                                <input type="hidden" name="cycle_id" value="<?= intval($c['id']) ?>">
                                <input type="hidden" name="approve" value="1">
                                <?php csrfInput(); ?>
                                <input type="text" name="review_note" placeholder="ملاحظة الاعتماد (اختياري)" class="flex-1 bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm">
                                <button type="submit" class="bg-emerald-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-emerald-700">اعتماد</button>
                            </form>
                            <form method="POST" class="flex gap-2">
                                <input type="hidden" name="action" value="review_cycle">
                                <input type="hidden" name="cycle_id" value="<?= intval($c['id']) ?>">
                                <input type="hidden" name="approve" value="0">
                                <?php csrfInput(); ?>
                                <input type="text" name="review_note" placeholder="سبب الرفض (اختياري)" class="flex-1 bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm">
                                <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-700">رفض</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="p-4 border-b border-slate-100">
                    <h3 class="font-bold text-slate-900">آخر القيود المحاسبية التلقائية</h3>
                </div>
                <div class="overflow-auto">
                    <table class="w-full text-sm text-right">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="p-3">رقم القيد</th>
                                <th class="p-3">المرجع</th>
                                <th class="p-3">الوصف</th>
                                <th class="p-3">القيمة</th>
                                <th class="p-3">المنشئ</th>
                                <th class="p-3">التاريخ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($recentEntries)): ?>
                            <tr><td colspan="6" class="p-6 text-center text-slate-400">لا توجد قيود بعد</td></tr>
                            <?php endif; ?>
                            <?php foreach ($recentEntries as $e): ?>
                            <tr>
                                <td class="p-3 font-num"><?= sanitize($e['entry_number']) ?></td>
                                <td class="p-3 text-xs text-slate-500"><?= sanitize(($e['ref_type'] ?? '') . ' / ' . ($e['ref_number'] ?? '')) ?></td>
                                <td class="p-3"><?= sanitize($e['description'] ?? '') ?></td>
                                <td class="p-3 font-num font-bold"><?= number_format(floatval($e['total_debit']), 2) ?></td>
                                <td class="p-3"><?= sanitize($e['user_name'] ?? 'System') ?></td>
                                <td class="p-3 text-xs text-slate-500"><?= sanitize(formatDateTimeAr($e['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
