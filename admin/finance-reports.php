<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole(['admin']);
$pageTitle = 'التقارير المالية';

[$from, $to] = resolveDateRange('admin_finance_reports', date('Y-m-01'), date('Y-m-d'));
$supplierId = intval($_GET['supplier_id'] ?? 0);

$payablesSummary = $db->fetchOne(
    "SELECT
        COALESCE(SUM(due_amount), 0) AS total_due,
        COUNT(CASE WHEN due_amount > 0 THEN 1 END) AS open_invoices
     FROM purchase_invoices"
);

$topSuppliers = $db->fetchAll(
    "SELECT s.id, s.name, COALESCE(SUM(pi.due_amount), 0) AS due_total
     FROM suppliers s
     LEFT JOIN purchase_invoices pi ON pi.supplier_id = s.id
     WHERE s.is_active = 1
     GROUP BY s.id
     ORDER BY due_total DESC
     LIMIT 12"
);

$agingBuckets = $db->fetchOne(
    "SELECT
        COALESCE(SUM(CASE WHEN julianday('now') - julianday(invoice_date) BETWEEN 0 AND 30 THEN due_amount ELSE 0 END), 0) AS b0_30,
        COALESCE(SUM(CASE WHEN julianday('now') - julianday(invoice_date) BETWEEN 31 AND 60 THEN due_amount ELSE 0 END), 0) AS b31_60,
        COALESCE(SUM(CASE WHEN julianday('now') - julianday(invoice_date) BETWEEN 61 AND 90 THEN due_amount ELSE 0 END), 0) AS b61_90,
        COALESCE(SUM(CASE WHEN julianday('now') - julianday(invoice_date) > 90 THEN due_amount ELSE 0 END), 0) AS b90p
     FROM purchase_invoices
     WHERE due_amount > 0"
);

$rangePurchases = $db->fetchOne(
    "SELECT COALESCE(SUM(total_amount),0) AS total FROM purchase_invoices WHERE date(invoice_date) BETWEEN ? AND ?",
    [$from, $to]
);
$rangePayments = $db->fetchOne(
    "SELECT COALESCE(SUM(amount),0) AS total FROM supplier_payments WHERE date(payment_date) BETWEEN ? AND ?",
    [$from, $to]
);

$supplierOptions = $db->fetchAll("SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name");
$selectedSupplier = null;
$statementRows = [];
$openingBalance = 0.0;
$closingBalance = 0.0;

if ($supplierId > 0) {
    $selectedSupplier = $db->fetchOne("SELECT * FROM suppliers WHERE id = ?", [$supplierId]);
    if ($selectedSupplier) {
        $beforeInvoices = $db->fetchOne(
            "SELECT COALESCE(SUM(total_amount - paid_amount), 0) AS net
             FROM purchase_invoices
             WHERE supplier_id = ? AND date(invoice_date) < ?",
            [$supplierId, $from]
        );
        $beforePayments = $db->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) AS total
             FROM supplier_payments
             WHERE supplier_id = ? AND date(payment_date) < ?",
            [$supplierId, $from]
        );
        $openingBalance = floatval($beforeInvoices['net'] ?? 0) - floatval($beforePayments['total'] ?? 0);

        $invoiceRows = $db->fetchAll(
            "SELECT id, invoice_date AS tx_date, invoice_number AS ref_number, notes, total_amount, paid_amount
             FROM purchase_invoices
             WHERE supplier_id = ? AND date(invoice_date) BETWEEN ? AND ?
             ORDER BY invoice_date ASC, id ASC",
            [$supplierId, $from, $to]
        );
        $paymentRows = $db->fetchAll(
            "SELECT id, payment_date AS tx_date, amount, notes
             FROM supplier_payments
             WHERE supplier_id = ? AND date(payment_date) BETWEEN ? AND ?
             ORDER BY payment_date ASC, id ASC",
            [$supplierId, $from, $to]
        );

        foreach ($invoiceRows as $inv) {
            $statementRows[] = [
                'tx_date' => $inv['tx_date'],
                'sort_key' => $inv['tx_date'] . '-1-' . str_pad((string)$inv['id'], 8, '0', STR_PAD_LEFT),
                'type' => 'invoice',
                'ref' => $inv['ref_number'],
                'description' => 'فاتورة شراء',
                'debit' => floatval($inv['total_amount']),
                'credit' => 0.0,
                'notes' => $inv['notes'] ?? ''
            ];
            $immediatePaid = floatval($inv['paid_amount'] ?? 0);
            if ($immediatePaid > 0) {
                $statementRows[] = [
                    'tx_date' => $inv['tx_date'],
                    'sort_key' => $inv['tx_date'] . '-2-' . str_pad((string)$inv['id'], 8, '0', STR_PAD_LEFT),
                    'type' => 'initial_payment',
                    'ref' => $inv['ref_number'],
                    'description' => 'سداد فوري مع الفاتورة',
                    'debit' => 0.0,
                    'credit' => $immediatePaid,
                    'notes' => ''
                ];
            }
        }

        foreach ($paymentRows as $pay) {
            $statementRows[] = [
                'tx_date' => $pay['tx_date'],
                'sort_key' => $pay['tx_date'] . '-3-' . str_pad((string)$pay['id'], 8, '0', STR_PAD_LEFT),
                'type' => 'payment',
                'ref' => 'PAY-' . $pay['id'],
                'description' => 'سداد مورد',
                'debit' => 0.0,
                'credit' => floatval($pay['amount']),
                'notes' => $pay['notes'] ?? ''
            ];
        }

        usort($statementRows, fn($a, $b) => strcmp($a['sort_key'], $b['sort_key']));
        $running = $openingBalance;
        foreach ($statementRows as &$row) {
            $running += floatval($row['debit']) - floatval($row['credit']);
            $row['balance'] = $running;
        }
        unset($row);
        $closingBalance = $running;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="flex h-screen overflow-hidden">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="flex-1 overflow-y-auto bg-background-light">
        <header class="bg-white border-b border-slate-200 px-6 py-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">التقارير المالية</h1>
                    <p class="text-sm text-slate-500 mt-1">ذمم الموردين، أعمار الديون، وكشف حساب تفصيلي</p>
                </div>
                <form method="GET" class="flex items-center gap-2">
                    <input type="date" name="from" value="<?= sanitize($from) ?>" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-num">
                    <input type="date" name="to" value="<?= sanitize($to) ?>" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-num">
                    <?php if ($supplierId > 0): ?>
                    <input type="hidden" name="supplier_id" value="<?= intval($supplierId) ?>">
                    <?php endif; ?>
                    <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-lg text-sm font-medium">تحديث</button>
                </form>
            </div>
        </header>

        <div class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl border border-slate-200 p-5">
                    <p class="text-xs text-slate-500">إجمالي ذمم الموردين</p>
                    <p class="text-2xl font-bold font-num text-red-600"><?= number_format(floatval($payablesSummary['total_due'] ?? 0), 2) ?></p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-5">
                    <p class="text-xs text-slate-500">فواتير مفتوحة</p>
                    <p class="text-2xl font-bold font-num"><?= intval($payablesSummary['open_invoices'] ?? 0) ?></p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-5">
                    <p class="text-xs text-slate-500">مشتريات الفترة</p>
                    <p class="text-2xl font-bold font-num text-slate-900"><?= number_format(floatval($rangePurchases['total'] ?? 0), 2) ?></p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-5">
                    <p class="text-xs text-slate-500">سداد الموردين (الفترة)</p>
                    <p class="text-2xl font-bold font-num text-emerald-600"><?= number_format(floatval($rangePayments['total'] ?? 0), 2) ?></p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-xl border border-slate-200 p-5">
                    <h3 class="font-bold text-slate-900 mb-3">أعمار الديون</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between"><span>0 - 30 يوم</span><span class="font-num font-bold"><?= number_format(floatval($agingBuckets['b0_30'] ?? 0), 2) ?></span></div>
                        <div class="flex justify-between"><span>31 - 60 يوم</span><span class="font-num font-bold"><?= number_format(floatval($agingBuckets['b31_60'] ?? 0), 2) ?></span></div>
                        <div class="flex justify-between"><span>61 - 90 يوم</span><span class="font-num font-bold"><?= number_format(floatval($agingBuckets['b61_90'] ?? 0), 2) ?></span></div>
                        <div class="flex justify-between"><span>أكثر من 90 يوم</span><span class="font-num font-bold text-red-600"><?= number_format(floatval($agingBuckets['b90p'] ?? 0), 2) ?></span></div>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                    <div class="p-4 border-b border-slate-100">
                        <h3 class="font-bold text-slate-900">أعلى موردين (مستحقات)</h3>
                    </div>
                    <div class="divide-y divide-slate-100 max-h-64 overflow-auto">
                        <?php foreach ($topSuppliers as $ts): ?>
                        <a href="finance-reports.php?supplier_id=<?= intval($ts['id']) ?>&from=<?= sanitize($from) ?>&to=<?= sanitize($to) ?>" class="block p-3 hover:bg-slate-50">
                            <div class="flex justify-between text-sm">
                                <span class="font-medium text-slate-800"><?= sanitize($ts['name']) ?></span>
                                <span class="font-num <?= floatval($ts['due_total']) > 0 ? 'text-red-600' : 'text-slate-400' ?>"><?= number_format(floatval($ts['due_total']), 2) ?></span>
                            </div>
                        </a>
                        <?php endforeach; ?>
                        <?php if (empty($topSuppliers)): ?>
                        <p class="p-4 text-sm text-slate-400 text-center">لا توجد بيانات</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
                    <input type="hidden" name="from" value="<?= sanitize($from) ?>">
                    <input type="hidden" name="to" value="<?= sanitize($to) ?>">
                    <div class="md:col-span-3">
                        <label class="block text-xs text-slate-500 mb-1">كشف حساب مورد</label>
                        <select name="supplier_id" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm">
                            <option value="">اختر المورد</option>
                            <?php foreach ($supplierOptions as $s): ?>
                            <option value="<?= intval($s['id']) ?>" <?= $supplierId === intval($s['id']) ? 'selected' : '' ?>><?= sanitize($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-lg text-sm font-medium">عرض كشف الحساب</button>
                    </div>
                </form>
            </div>

            <?php if ($selectedSupplier): ?>
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="font-bold text-slate-900">كشف حساب: <?= sanitize($selectedSupplier['name']) ?></h3>
                    <div class="text-xs text-slate-500 font-num">
                        رصيد أول المدة: <?= number_format($openingBalance, 2) ?> |
                        رصيد آخر المدة: <span class="<?= $closingBalance > 0 ? 'text-red-600' : 'text-emerald-600' ?>"><?= number_format($closingBalance, 2) ?></span>
                    </div>
                </div>
                <div class="overflow-auto">
                    <table class="w-full text-sm text-right">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="p-3">التاريخ</th>
                                <th class="p-3">المرجع</th>
                                <th class="p-3">البيان</th>
                                <th class="p-3">مدين</th>
                                <th class="p-3">دائن</th>
                                <th class="p-3">الرصيد الجاري</th>
                                <th class="p-3">ملاحظات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($statementRows)): ?>
                            <tr><td colspan="7" class="p-6 text-center text-slate-400">لا توجد حركات خلال هذه الفترة</td></tr>
                            <?php endif; ?>
                            <?php foreach ($statementRows as $row): ?>
                            <tr>
                                <td class="p-3"><?= sanitize(formatDateAr($row['tx_date'])) ?></td>
                                <td class="p-3 font-num text-xs"><?= sanitize($row['ref']) ?></td>
                                <td class="p-3"><?= sanitize($row['description']) ?></td>
                                <td class="p-3 font-num"><?= number_format(floatval($row['debit']), 2) ?></td>
                                <td class="p-3 font-num"><?= number_format(floatval($row['credit']), 2) ?></td>
                                <td class="p-3 font-num font-bold <?= floatval($row['balance']) > 0 ? 'text-red-600' : 'text-emerald-600' ?>"><?= number_format(floatval($row['balance']), 2) ?></td>
                                <td class="p-3 text-xs text-slate-500"><?= sanitize($row['notes'] ?? '') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
