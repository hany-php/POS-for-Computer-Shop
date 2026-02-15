<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole(['admin']);
$pageTitle = 'المركز المالي';
$includeChartJS = true;

[$from, $to] = resolveDateRange('admin_financial_center', date('Y-m-01'), date('Y-m-d'));

$cashBalance = getAccountBalance('1010');
$bankBalance = getAccountBalance('1020');
$supplierDue = floatval(($db->fetchOne("SELECT COALESCE(SUM(due_amount),0) AS total FROM purchase_invoices")['total'] ?? 0));
$monthSales = floatval(($db->fetchOne(
    "SELECT COALESCE(SUM(total),0) AS total FROM orders WHERE status='completed' AND strftime('%Y-%m', created_at)=strftime('%Y-%m','now')"
)['total'] ?? 0));
$monthExpenses = floatval(($db->fetchOne(
    "SELECT COALESCE(SUM(amount),0) AS total FROM expenses WHERE strftime('%Y-%m', expense_date)=strftime('%Y-%m','now')"
)['total'] ?? 0));
$monthPurchases = floatval(($db->fetchOne(
    "SELECT COALESCE(SUM(total_amount),0) AS total FROM purchase_invoices WHERE strftime('%Y-%m', invoice_date)=strftime('%Y-%m','now')"
)['total'] ?? 0));

$rangeSales = floatval(($db->fetchOne(
    "SELECT COALESCE(SUM(total),0) AS total FROM orders WHERE status='completed' AND date(created_at) BETWEEN ? AND ?",
    [$from, $to]
)['total'] ?? 0));
$rangeExpenses = floatval(($db->fetchOne(
    "SELECT COALESCE(SUM(amount),0) AS total FROM expenses WHERE date(expense_date) BETWEEN ? AND ?",
    [$from, $to]
)['total'] ?? 0));
$rangePurchases = floatval(($db->fetchOne(
    "SELECT COALESCE(SUM(total_amount),0) AS total FROM purchase_invoices WHERE date(invoice_date) BETWEEN ? AND ?",
    [$from, $to]
)['total'] ?? 0));
$rangeSupplierPayments = floatval(($db->fetchOne(
    "SELECT COALESCE(SUM(amount),0) AS total FROM supplier_payments WHERE date(payment_date) BETWEEN ? AND ?",
    [$from, $to]
)['total'] ?? 0));

$treasuryTodayIn = floatval(($db->fetchOne("SELECT COALESCE(SUM(amount),0) AS total FROM treasury_transactions WHERE txn_type='in' AND date(created_at)=date('now')")['total'] ?? 0));
$treasuryTodayOut = floatval(($db->fetchOne("SELECT COALESCE(SUM(amount),0) AS total FROM treasury_transactions WHERE txn_type='out' AND date(created_at)=date('now')")['total'] ?? 0));
$todayNet = $treasuryTodayIn - $treasuryTodayOut;

$expenseByCategory = $db->fetchAll(
    "SELECT COALESCE(c.name, 'بدون تصنيف') AS cat_name, COALESCE(SUM(e.amount),0) AS total
     FROM expenses e
     LEFT JOIN expense_categories c ON c.id = e.category_id
     WHERE date(e.expense_date) BETWEEN ? AND ?
     GROUP BY c.name
     ORDER BY total DESC
     LIMIT 8",
    [$from, $to]
);

$topDueSuppliers = $db->fetchAll(
    "SELECT s.name, COALESCE(SUM(pi.due_amount),0) AS due_total
     FROM suppliers s
     LEFT JOIN purchase_invoices pi ON pi.supplier_id = s.id
     WHERE s.is_active = 1
     GROUP BY s.id
     ORDER BY due_total DESC
     LIMIT 8"
);

$recentJournal = $db->fetchAll(
    "SELECT je.entry_number, je.ref_type, je.ref_number, je.description, je.created_at,
            COALESCE((SELECT SUM(debit) FROM journal_lines jl WHERE jl.entry_id = je.id),0) AS amount
     FROM journal_entries je
     ORDER BY je.id DESC
     LIMIT 20"
);

$dailyFinance = $db->fetchAll(
    "WITH RECURSIVE days(d) AS (
        SELECT date(?, '-29 days')
        UNION ALL
        SELECT date(d, '+1 day') FROM days WHERE d < date(?)
     )
     SELECT
        d AS day,
        COALESCE((SELECT SUM(total) FROM orders o WHERE o.status='completed' AND date(o.created_at)=d),0) AS sales,
        COALESCE((SELECT SUM(amount) FROM expenses e WHERE date(e.expense_date)=d),0) AS expenses,
        COALESCE((SELECT SUM(total_amount) FROM purchase_invoices p WHERE date(p.invoice_date)=d),0) AS purchases
     FROM days",
    [$to, $to]
);

include __DIR__ . '/../includes/header.php';
?>

<div class="flex h-screen overflow-hidden">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="flex-1 overflow-y-auto bg-background-light">
        <header class="bg-white border-b border-slate-200 px-6 py-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">المركز المالي</h1>
                    <p class="text-sm text-slate-500 mt-1">لوحة موحدة لملف الخزنة والمبيعات والمصروفات والموردين</p>
                </div>
                <form method="GET" class="flex items-center gap-2">
                    <input type="date" name="from" value="<?= sanitize($from) ?>" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-num">
                    <input type="date" name="to" value="<?= sanitize($to) ?>" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-num">
                    <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-lg text-sm font-medium">تحديث</button>
                </form>
            </div>
        </header>

        <div class="p-6 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-6 gap-4">
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <p class="text-xs text-slate-500">رصيد الخزنة</p>
                    <p class="text-xl font-bold font-num"><?= number_format($cashBalance, 2) ?></p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <p class="text-xs text-slate-500">رصيد البنك</p>
                    <p class="text-xl font-bold font-num"><?= number_format($bankBalance, 2) ?></p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <p class="text-xs text-slate-500">ذمم الموردين</p>
                    <p class="text-xl font-bold font-num text-red-600"><?= number_format($supplierDue, 2) ?></p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <p class="text-xs text-slate-500">مبيعات الشهر</p>
                    <p class="text-xl font-bold font-num"><?= number_format($monthSales, 2) ?></p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <p class="text-xs text-slate-500">مشتريات الشهر</p>
                    <p class="text-xl font-bold font-num"><?= number_format($monthPurchases, 2) ?></p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <p class="text-xs text-slate-500">مصروفات الشهر</p>
                    <p class="text-xl font-bold font-num text-red-600"><?= number_format($monthExpenses, 2) ?></p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <p class="text-xs text-slate-500">مبيعات الفترة</p>
                    <p class="text-lg font-bold font-num"><?= number_format($rangeSales, 2) ?></p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <p class="text-xs text-slate-500">مشتريات الفترة</p>
                    <p class="text-lg font-bold font-num"><?= number_format($rangePurchases, 2) ?></p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <p class="text-xs text-slate-500">مصروفات الفترة</p>
                    <p class="text-lg font-bold font-num text-red-600"><?= number_format($rangeExpenses, 2) ?></p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <p class="text-xs text-slate-500">سداد موردين (الفترة)</p>
                    <p class="text-lg font-bold font-num"><?= number_format($rangeSupplierPayments, 2) ?></p>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <h3 class="font-bold text-slate-900 mb-3">اتجاه مالي آخر 30 يوم</h3>
                <div style="height: 300px;">
                    <canvas id="financeTrendChart"></canvas>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                    <div class="p-4 border-b border-slate-100">
                        <h3 class="font-bold text-slate-900">أعلى تصنيفات المصروفات (الفترة)</h3>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <?php if (empty($expenseByCategory)): ?>
                        <p class="p-5 text-sm text-slate-400 text-center">لا توجد مصروفات</p>
                        <?php endif; ?>
                        <?php foreach ($expenseByCategory as $row): ?>
                        <div class="p-3 flex justify-between text-sm">
                            <span><?= sanitize($row['cat_name']) ?></span>
                            <span class="font-num font-bold"><?= number_format(floatval($row['total']), 2) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                    <div class="p-4 border-b border-slate-100">
                        <h3 class="font-bold text-slate-900">أعلى الموردين مديونية</h3>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <?php if (empty($topDueSuppliers)): ?>
                        <p class="p-5 text-sm text-slate-400 text-center">لا توجد بيانات</p>
                        <?php endif; ?>
                        <?php foreach ($topDueSuppliers as $row): ?>
                        <div class="p-3 flex justify-between text-sm">
                            <span><?= sanitize($row['name']) ?></span>
                            <span class="font-num font-bold <?= floatval($row['due_total']) > 0 ? 'text-red-600' : 'text-slate-400' ?>"><?= number_format(floatval($row['due_total']), 2) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="font-bold text-slate-900">آخر القيود المالية</h3>
                    <span class="text-xs text-slate-500">صافي اليوم: <span class="font-num font-bold <?= $todayNet >= 0 ? 'text-emerald-600' : 'text-red-600' ?>"><?= number_format($todayNet, 2) ?></span></span>
                </div>
                <div class="overflow-auto">
                    <table class="w-full text-sm text-right">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="p-3">رقم القيد</th>
                                <th class="p-3">المرجع</th>
                                <th class="p-3">الوصف</th>
                                <th class="p-3">القيمة</th>
                                <th class="p-3">التاريخ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($recentJournal)): ?>
                            <tr><td colspan="5" class="p-6 text-center text-slate-400">لا توجد قيود</td></tr>
                            <?php endif; ?>
                            <?php foreach ($recentJournal as $j): ?>
                            <tr>
                                <td class="p-3 font-num"><?= sanitize($j['entry_number']) ?></td>
                                <td class="p-3 text-xs text-slate-500"><?= sanitize(($j['ref_type'] ?? '') . ' / ' . ($j['ref_number'] ?? '')) ?></td>
                                <td class="p-3"><?= sanitize($j['description'] ?? '') ?></td>
                                <td class="p-3 font-num font-bold"><?= number_format(floatval($j['amount']), 2) ?></td>
                                <td class="p-3 text-xs text-slate-500"><?= sanitize(formatDateTimeAr($j['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
const dailyFinance = <?= json_encode($dailyFinance, JSON_UNESCAPED_UNICODE) ?>;
function formatGregorianLabelAr(d) {
    try {
        return new Date(d + 'T00:00:00').toLocaleDateString('ar-EG', { day: 'numeric', month: 'short' });
    } catch (e) {
        return d;
    }
}
new Chart(document.getElementById('financeTrendChart'), {
    type: 'line',
    data: {
        labels: dailyFinance.map(d => formatGregorianLabelAr(d.day)),
        datasets: [
            {
                label: 'المبيعات',
                data: dailyFinance.map(d => Number(d.sales || 0)),
                borderColor: '#16a34a',
                backgroundColor: 'rgba(22,163,74,0.1)',
                fill: false,
                tension: 0.35
            },
            {
                label: 'المشتريات',
                data: dailyFinance.map(d => Number(d.purchases || 0)),
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37,99,235,0.1)',
                fill: false,
                tension: 0.35
            },
            {
                label: 'المصروفات',
                data: dailyFinance.map(d => Number(d.expenses || 0)),
                borderColor: '#dc2626',
                backgroundColor: 'rgba(220,38,38,0.1)',
                fill: false,
                tension: 0.35
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        scales: {
            y: { beginAtZero: true },
            x: { ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 10 } }
        }
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
