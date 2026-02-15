<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole(['admin']);
$pageTitle = 'إدارة المصروفات';
$user = Auth::user();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCsrfTokenOrFail();

    if ($_POST['action'] === 'add_expense') {
        try {
            $amount = floatval($_POST['amount'] ?? 0);
            if ($amount <= 0) throw new Exception('قيمة المصروف غير صالحة');

            $expenseDate = $_POST['expense_date'] ?? date('Y-m-d');
            $categoryId = intval($_POST['category_id'] ?? 0);
            $paymentMethod = $_POST['payment_method'] ?? 'cash';
            $notes = trim((string)($_POST['notes'] ?? ''));

            $expenseId = $db->insert(
                "INSERT INTO expenses (expense_date, category_id, amount, payment_method, notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$expenseDate, $categoryId ?: null, $amount, $paymentMethod, $notes, $_SESSION['user_id'] ?? null]
            );

            postExpenseAccountingEntry($expenseId);
            logActivity('إضافة مصروف', 'expense', $expenseId, 'amount=' . $amount);
            setFlash('success', 'تم تسجيل المصروف وترحيله محاسبيًا بنجاح');
        } catch (Exception $e) {
            setFlash('error', $e->getMessage());
        }
        header('Location: expenses.php');
        exit;
    }

    if ($_POST['action'] === 'add_category') {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name !== '') {
            $db->query("INSERT OR IGNORE INTO expense_categories (name, is_active) VALUES (?, 1)", [$name]);
            setFlash('success', 'تم إضافة تصنيف المصروف');
        }
        header('Location: expenses.php');
        exit;
    }
}

$categories = $db->fetchAll("SELECT * FROM expense_categories WHERE is_active = 1 ORDER BY name");
$recentExpenses = $db->fetchAll(
    "SELECT e.*, c.name AS category_name, u.full_name AS created_by_name
     FROM expenses e
     LEFT JOIN expense_categories c ON c.id = e.category_id
     LEFT JOIN users u ON u.id = e.created_by
     ORDER BY e.id DESC
     LIMIT 50"
);
$monthSummary = $db->fetchOne(
    "SELECT COALESCE(SUM(amount),0) AS total, COUNT(*) AS cnt
     FROM expenses
     WHERE strftime('%Y-%m', expense_date) = strftime('%Y-%m', 'now')"
);

include __DIR__ . '/../includes/header.php';
?>

<div class="flex h-screen overflow-hidden">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="flex-1 overflow-y-auto bg-background-light">
        <header class="bg-white border-b border-slate-200 px-6 py-5">
            <h1 class="text-2xl font-bold text-slate-900">إدارة المصروفات</h1>
            <p class="text-sm text-slate-500 mt-1">تسجيل المصروفات مع ترحيل تلقائي للخزنة والحسابات</p>
        </header>

        <div class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-white rounded-xl border border-slate-200 p-5">
                    <p class="text-xs text-slate-500">إجمالي مصروفات الشهر</p>
                    <p class="text-2xl font-bold font-num text-red-600"><?= number_format(floatval($monthSummary['total'] ?? 0), 2) ?> <?= CURRENCY ?></p>
                    <p class="text-xs text-slate-400 mt-1"><?= intval($monthSummary['cnt'] ?? 0) ?> حركة</p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-5">
                    <form method="POST" class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-end">
                        <input type="hidden" name="action" value="add_category">
                        <?php csrfInput(); ?>
                        <div class="sm:col-span-2">
                            <label class="block text-xs text-slate-500 mb-1">إضافة تصنيف مصروف جديد</label>
                            <input type="text" name="name" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="مثال: إنترنت">
                        </div>
                        <div class="sm:col-span-2">
                            <button type="submit" class="bg-slate-800 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-900">إضافة التصنيف</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 p-6">
                <h3 class="font-bold text-slate-900 mb-4">تسجيل مصروف</h3>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
                    <input type="hidden" name="action" value="add_expense">
                    <?php csrfInput(); ?>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">التاريخ</label>
                        <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-num">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">التصنيف</label>
                        <select name="category_id" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm">
                            <option value="">بدون تصنيف</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= intval($cat['id']) ?>"><?= sanitize($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">طريقة الدفع</label>
                        <select name="payment_method" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm">
                            <option value="cash">نقدي (الخزنة)</option>
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
                        <input type="text" name="notes" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="اختياري">
                    </div>
                    <div class="md:col-span-5">
                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-5 py-2.5 rounded-lg text-sm font-medium">حفظ المصروف</button>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="p-4 border-b border-slate-100">
                    <h3 class="font-bold text-slate-900">آخر المصروفات</h3>
                </div>
                <div class="overflow-auto">
                    <table class="w-full text-sm text-right">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="p-3">التاريخ</th>
                                <th class="p-3">التصنيف</th>
                                <th class="p-3">طريقة الدفع</th>
                                <th class="p-3">المبلغ</th>
                                <th class="p-3">ملاحظات</th>
                                <th class="p-3">بواسطة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($recentExpenses)): ?>
                            <tr><td colspan="6" class="p-6 text-center text-slate-400">لا توجد مصروفات حتى الآن</td></tr>
                            <?php endif; ?>
                            <?php foreach ($recentExpenses as $e): ?>
                            <tr>
                                <td class="p-3"><?= sanitize(formatDateAr($e['expense_date'])) ?></td>
                                <td class="p-3"><?= sanitize($e['category_name'] ?? '—') ?></td>
                                <td class="p-3"><?= sanitize(getPaymentMethodAr($e['payment_method'])) ?></td>
                                <td class="p-3 font-num font-bold text-red-600"><?= number_format(floatval($e['amount']), 2) ?></td>
                                <td class="p-3 text-xs text-slate-500"><?= sanitize($e['notes'] ?? '') ?></td>
                                <td class="p-3 text-xs"><?= sanitize($e['created_by_name'] ?? 'System') ?></td>
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
