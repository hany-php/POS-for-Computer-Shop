<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole(['admin']);
$pageTitle = 'إدارة الخزنة';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCsrfTokenOrFail();
    if ($_POST['action'] === 'add_treasury_txn') {
        try {
            $txnType = $_POST['txn_type'] ?? 'in';
            $paymentMethod = $_POST['payment_method'] ?? 'cash';
            $amount = floatval($_POST['amount'] ?? 0);
            $notes = trim((string)($_POST['notes'] ?? ''));
            postManualTreasuryEntry($txnType, $amount, $paymentMethod, $notes);
            logActivity($txnType === 'in' ? 'قبض خزنة' : 'صرف خزنة', 'treasury', null, 'amount=' . $amount);
            setFlash('success', 'تم تسجيل حركة الخزنة بنجاح');
        } catch (Exception $e) {
            setFlash('error', $e->getMessage());
        }
        header('Location: treasury.php');
        exit;
    }
}

$cashBalance = getAccountBalance('1010');
$bankBalance = getAccountBalance('1020');
$todayIn = $db->fetchOne("SELECT COALESCE(SUM(amount),0) AS total FROM treasury_transactions WHERE txn_type='in' AND date(created_at)=date('now')");
$todayOut = $db->fetchOne("SELECT COALESCE(SUM(amount),0) AS total FROM treasury_transactions WHERE txn_type='out' AND date(created_at)=date('now')");
$todaySales = $db->fetchOne(
    "SELECT
        COALESCE(SUM(total),0) AS gross_total,
        COALESCE(SUM(CASE WHEN total > payment_received THEN (CASE WHEN payment_received > total THEN total ELSE payment_received END) ELSE total END),0) AS collected_total,
        COALESCE(SUM(CASE WHEN total > payment_received THEN (total - payment_received) ELSE 0 END),0) AS due_total,
        COUNT(CASE WHEN total > payment_received THEN 1 END) AS due_count
     FROM orders
     WHERE status='completed' AND date(created_at)=date('now')"
);
$customersDueTotal = floatval(($db->fetchOne("SELECT COALESCE(SUM(balance),0) AS total FROM customers WHERE is_active = 1")['total'] ?? 0));
$recentTxns = $db->fetchAll(
    "SELECT t.*, u.full_name AS created_by_name
     FROM treasury_transactions t
     LEFT JOIN users u ON u.id = t.created_by
     ORDER BY t.id DESC
     LIMIT 80"
);

include __DIR__ . '/../includes/header.php';
?>

<div class="flex h-screen overflow-hidden">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="flex-1 overflow-y-auto bg-background-light">
        <header class="bg-white border-b border-slate-200 px-6 py-5">
            <h1 class="text-2xl font-bold text-slate-900">إدارة الخزنة</h1>
            <p class="text-sm text-slate-500 mt-1">قبض/صرف يدوي مع ترحيل محاسبي تلقائي وتحديث أرصدة السيولة</p>
        </header>

        <div class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-7 gap-4">
                <div class="bg-white rounded-xl border border-slate-200 p-5">
                    <p class="text-xs text-slate-500">رصيد الخزنة (1010)</p>
                    <p class="text-2xl font-bold font-num text-slate-900"><?= number_format($cashBalance, 2) ?> <?= CURRENCY ?></p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-5">
                    <p class="text-xs text-slate-500">رصيد البنك/المحافظ (1020)</p>
                    <p class="text-2xl font-bold font-num text-slate-900"><?= number_format($bankBalance, 2) ?> <?= CURRENCY ?></p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-5">
                    <p class="text-xs text-slate-500">إجمالي مبيعات اليوم</p>
                    <p class="text-2xl font-bold font-num text-slate-900"><?= number_format(floatval($todaySales['gross_total'] ?? 0), 2) ?> <?= CURRENCY ?></p>
                </div>
                <div class="bg-white rounded-xl border border-emerald-200 p-5">
                    <p class="text-xs text-slate-500">المحصل من مبيعات اليوم</p>
                    <p class="text-2xl font-bold font-num text-emerald-700"><?= number_format(floatval($todaySales['collected_total'] ?? 0), 2) ?> <?= CURRENCY ?></p>
                </div>
                <div class="bg-white rounded-xl border border-amber-200 p-5">
                    <p class="text-xs text-slate-500">دين مبيعات اليوم</p>
                    <p class="text-2xl font-bold font-num text-amber-700"><?= number_format(floatval($todaySales['due_total'] ?? 0), 2) ?> <?= CURRENCY ?></p>
                    <p class="text-xs text-slate-400 font-num mt-1"><?= intval($todaySales['due_count'] ?? 0) ?> فواتير دين</p>
                </div>
                <div class="bg-white rounded-xl border border-red-200 p-5">
                    <p class="text-xs text-slate-500">إجمالي دين العملاء القائم</p>
                    <p class="text-2xl font-bold font-num text-red-700"><?= number_format($customersDueTotal, 2) ?> <?= CURRENCY ?></p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-5">
                    <p class="text-xs text-slate-500">مقبوضات خزنة يدوية اليوم</p>
                    <p class="text-2xl font-bold font-num text-emerald-600"><?= number_format(floatval($todayIn['total'] ?? 0), 2) ?> <?= CURRENCY ?></p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-5">
                    <p class="text-xs text-slate-500">مدفوعات خزنة يدوية اليوم</p>
                    <p class="text-2xl font-bold font-num text-red-600"><?= number_format(floatval($todayOut['total'] ?? 0), 2) ?> <?= CURRENCY ?></p>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 p-6">
                <h3 class="font-bold text-slate-900 mb-4">إضافة حركة خزنة</h3>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
                    <input type="hidden" name="action" value="add_treasury_txn">
                    <?php csrfInput(); ?>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">نوع الحركة</label>
                        <select name="txn_type" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm">
                            <option value="in">قبض</option>
                            <option value="out">صرف</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">وسيلة الحركة</label>
                        <select name="payment_method" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm">
                            <option value="cash">نقدي</option>
                            <option value="card">بنك / بطاقة</option>
                            <option value="transfer">تحويل</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">المبلغ</label>
                        <input type="number" name="amount" min="0.01" step="0.01" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-num text-left" dir="ltr">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">ملاحظات</label>
                        <input type="text" name="notes" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="مثال: توريد رأس مال">
                    </div>
                    <div>
                        <button type="submit" class="w-full bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-lg text-sm font-medium">حفظ الحركة</button>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="p-4 border-b border-slate-100">
                    <h3 class="font-bold text-slate-900">حركات الخزنة الأخيرة</h3>
                </div>
                <div class="overflow-auto">
                    <table class="w-full text-sm text-right">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="p-3">الوقت</th>
                                <th class="p-3">النوع</th>
                                <th class="p-3">الحساب</th>
                                <th class="p-3">المبلغ</th>
                                <th class="p-3">المصدر</th>
                                <th class="p-3">الملاحظات</th>
                                <th class="p-3">بواسطة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($recentTxns)): ?>
                            <tr><td colspan="7" class="p-6 text-center text-slate-400">لا توجد حركات بعد</td></tr>
                            <?php endif; ?>
                            <?php foreach ($recentTxns as $t): ?>
                            <tr>
                                <td class="p-3 text-xs"><?= sanitize(formatDateTimeAr($t['created_at'])) ?></td>
                                <td class="p-3">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= $t['txn_type'] === 'in' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' ?>">
                                        <?= $t['txn_type'] === 'in' ? 'قبض' : 'صرف' ?>
                                    </span>
                                </td>
                                <td class="p-3 font-num"><?= sanitize($t['account_code']) ?></td>
                                <td class="p-3 font-num font-bold <?= $t['txn_type'] === 'in' ? 'text-emerald-600' : 'text-red-600' ?>"><?= number_format(floatval($t['amount']), 2) ?></td>
                                <td class="p-3 text-xs text-slate-500"><?= sanitize($t['source'] ?? '') ?></td>
                                <td class="p-3 text-xs text-slate-500"><?= sanitize($t['notes'] ?? '') ?></td>
                                <td class="p-3 text-xs"><?= sanitize($t['created_by_name'] ?? 'System') ?></td>
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
