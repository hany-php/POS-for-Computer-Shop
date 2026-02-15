<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole(['admin']);
$pageTitle = 'إدارة الموردين';

$page = max(1, intval($_GET['page'] ?? 1));
$perPageAllowed = [15, 30, 50, 100];
$perPage = intval($_GET['per_page'] ?? 15);
if (!in_array($perPage, $perPageAllowed, true)) {
    $perPage = 15;
}
$search = trim($_GET['q'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCsrfTokenOrFail();

    if ($_POST['action'] === 'export_csv') {
        $selectedIds = $_POST['selected_ids'] ?? [];
        $rows = [];
        if (is_array($selectedIds) && !empty($selectedIds)) {
            $ids = array_values(array_filter(array_map('intval', $selectedIds), fn($v) => $v > 0));
            if (!empty($ids)) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $rows = $db->fetchAll("SELECT name, phone, email, address, notes, current_balance, is_active FROM suppliers WHERE id IN ($ph) ORDER BY name", $ids);
            }
        }
        if (empty($rows)) {
            $rows = $db->fetchAll("SELECT name, phone, email, address, notes, current_balance, is_active FROM suppliers ORDER BY name");
        }
        $out = array_map(fn($r) => [
            $r['name'],
            $r['phone'],
            $r['email'],
            $r['address'],
            $r['notes'],
            $r['current_balance'],
            intval($r['is_active']) === 1 ? 'active' : 'inactive'
        ], $rows);
        outputCsvDownload('suppliers_export_' . date('Ymd_His') . '.csv', ['name', 'phone', 'email', 'address', 'notes', 'current_balance', 'status'], $out);
    }

    if ($_POST['action'] === 'download_template') {
        outputCsvDownload(
            'suppliers_template.csv',
            ['name', 'phone', 'email', 'address', 'notes'],
            [['مورد تجريبي', '0500000000', 'supplier@example.com', 'الرياض', 'ملاحظة اختيارية']]
        );
    }

    if ($_POST['action'] === 'import_csv') {
        try {
            $rows = parseUploadedCsvAssoc($_FILES['csv_file'] ?? []);
            $inserted = 0;
            $updated = 0;
            foreach ($rows as $r) {
                $name = trim($r['name'] ?? $r['supplier_name'] ?? '');
                if ($name === '') {
                    continue;
                }
                $phone = trim($r['phone'] ?? '');
                $email = trim($r['email'] ?? '');
                $address = trim($r['address'] ?? '');
                $notes = trim($r['notes'] ?? '');

                $existing = $db->fetchOne("SELECT id FROM suppliers WHERE name = ? LIMIT 1", [$name]);
                if ($existing) {
                    $db->query(
                        "UPDATE suppliers SET phone = ?, email = ?, address = ?, notes = ?, is_active = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                        [$phone, $email, $address, $notes, $existing['id']]
                    );
                    $updated++;
                } else {
                    $db->insert(
                        "INSERT INTO suppliers (name, phone, email, address, notes, is_active) VALUES (?, ?, ?, ?, ?, 1)",
                        [$name, $phone, $email, $address, $notes]
                    );
                    $inserted++;
                }
            }
            logActivity('استيراد موردين CSV', 'supplier', null, "inserted:$inserted, updated:$updated");
            setFlash('success', "تم استيراد الموردين بنجاح (إضافة: $inserted ، تحديث: $updated)");
        } catch (Exception $e) {
            setFlash('error', 'فشل استيراد CSV: ' . $e->getMessage());
        }
        header('Location: suppliers.php');
        exit;
    }

    if ($_POST['action'] === 'create_supplier') {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            setFlash('error', 'اسم المورد مطلوب');
            header('Location: suppliers.php');
            exit;
        }
        $supplierId = $db->insert(
            "INSERT INTO suppliers (name, phone, email, address, notes, is_active) VALUES (?, ?, ?, ?, ?, 1)",
            [
                $name,
                trim((string)($_POST['phone'] ?? '')),
                trim((string)($_POST['email'] ?? '')),
                trim((string)($_POST['address'] ?? '')),
                trim((string)($_POST['notes'] ?? ''))
            ]
        );
        recalcSupplierBalance($supplierId);
        logActivity('إضافة مورد', 'supplier', $supplierId, $name);
        setFlash('success', 'تم إضافة المورد بنجاح');
        header('Location: suppliers.php');
        exit;
    }

    if ($_POST['action'] === 'toggle_supplier') {
        $supplierId = intval($_POST['supplier_id'] ?? 0);
        $supplier = $db->fetchOne("SELECT is_active FROM suppliers WHERE id = ?", [$supplierId]);
        if ($supplier) {
            $newVal = intval($supplier['is_active']) === 1 ? 0 : 1;
            $db->query("UPDATE suppliers SET is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$newVal, $supplierId]);
            logActivity($newVal ? 'تفعيل مورد' : 'تعطيل مورد', 'supplier', $supplierId, null);
            setFlash('success', $newVal ? 'تم تفعيل المورد' : 'تم تعطيل المورد');
        }
        header('Location: suppliers.php');
        exit;
    }
}

$whereSql = " FROM suppliers s WHERE 1=1";
$params = [];
if ($search !== '') {
    $whereSql .= " AND (s.name LIKE ? OR s.phone LIKE ? OR s.email LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$totalSuppliers = intval(($db->fetchOne("SELECT COUNT(*) AS cnt" . $whereSql, $params)['cnt'] ?? 0));
$totalPages = max(1, (int)ceil($totalSuppliers / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$suppliers = $db->fetchAll(
    "SELECT s.*,
            COALESCE((SELECT COUNT(*) FROM purchase_invoices pi WHERE pi.supplier_id = s.id), 0) AS invoices_count,
            COALESCE((SELECT SUM(total_amount) FROM purchase_invoices pi WHERE pi.supplier_id = s.id), 0) AS purchases_total,
            COALESCE((SELECT SUM(amount) FROM supplier_payments sp WHERE sp.supplier_id = s.id), 0) AS payments_total" .
     $whereSql .
    " ORDER BY s.is_active DESC, s.name ASC LIMIT $perPage OFFSET $offset",
    $params
);

foreach ($suppliers as &$s) {
    $s['current_balance'] = recalcSupplierBalance(intval($s['id']));
}
unset($s);

$baseQuery = $_GET;
unset($baseQuery['page']);
function suppliersPageUrl($targetPage, $baseQuery) {
    $q = $baseQuery;
    $q['page'] = $targetPage;
    return 'suppliers.php?' . http_build_query($q);
}

include __DIR__ . '/../includes/header.php';
?>

<div class="flex h-screen overflow-hidden">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="flex-1 overflow-y-auto bg-background-light">
        <header class="bg-white border-b border-slate-200 px-6 py-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">إدارة الموردين</h1>
                    <p class="text-sm text-slate-500 mt-1">بيانات الموردين والذمم والحالة التشغيلية</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <form method="GET" class="relative">
                        <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-400"><span class="material-icons-outlined text-sm">search</span></span>
                        <input type="hidden" name="per_page" value="<?= $perPage ?>">
                        <input type="text" name="q" value="<?= sanitize($search) ?>" placeholder="بحث بالاسم/الهاتف/البريد..." class="bg-slate-50 border border-slate-200 rounded-lg pr-10 pl-4 py-2 text-sm focus:outline-none focus:border-primary w-60">
                    </form>
                    <form method="POST" id="suppliers-export-form" class="inline">
                        <input type="hidden" name="action" value="export_csv">
                        <?php csrfInput(); ?>
                        <button type="submit" class="bg-white border border-slate-200 text-slate-700 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-slate-50">تصدير CSV</button>
                    </form>
                    <button type="button" onclick="submitSelectedSuppliersExport()" class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-blue-100">تصدير المحدد</button>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="download_template">
                        <?php csrfInput(); ?>
                        <button type="submit" class="bg-amber-50 border border-amber-200 text-amber-700 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-amber-100">تحميل قالب CSV</button>
                    </form>
                    <form method="POST" enctype="multipart/form-data" class="flex items-center gap-2">
                        <input type="hidden" name="action" value="import_csv">
                        <?php csrfInput(); ?>
                        <input type="file" name="csv_file" accept=".csv,text/csv" required class="text-xs text-slate-500 file:py-1.5 file:px-2 file:rounded file:border-0 file:bg-slate-100 file:text-slate-700">
                        <button type="submit" class="bg-slate-800 text-white px-3 py-2 rounded-lg text-xs font-medium hover:bg-slate-900">استيراد CSV</button>
                    </form>
                    <form method="GET" class="flex items-center gap-2">
                        <input type="hidden" name="q" value="<?= sanitize($search) ?>">
                        <label class="text-xs text-slate-500">عرض</label>
                        <select name="per_page" onchange="this.form.submit()" class="bg-slate-50 border border-slate-200 rounded-lg px-2 py-2 text-sm font-num">
                            <?php foreach ($perPageAllowed as $pp): ?>
                            <option value="<?= $pp ?>" <?= $pp === $perPage ? 'selected' : '' ?>><?= $pp ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>
        </header>

        <div class="p-6 space-y-6">
            <div class="bg-white rounded-xl border border-slate-200 p-6">
                <h3 class="font-bold text-slate-900 mb-4">إضافة مورد جديد</h3>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
                    <input type="hidden" name="action" value="create_supplier">
                    <?php csrfInput(); ?>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">اسم المورد</label>
                        <input type="text" name="name" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">الهاتف</label>
                        <input type="text" name="phone" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-num" dir="ltr">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">البريد</label>
                        <input type="email" name="email" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm" dir="ltr">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">العنوان</label>
                        <input type="text" name="address" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <button type="submit" class="w-full bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-lg text-sm font-medium">حفظ المورد</button>
                    </div>
                    <div class="md:col-span-5">
                        <label class="block text-xs text-slate-500 mb-1">ملاحظات</label>
                        <input type="text" name="notes" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm">
                    </div>
                </form>
            </div>

            <div class="mb-3 flex items-center justify-between text-sm">
                <p class="text-slate-500">المعروض: <span class="font-num font-bold"><?= count($suppliers) ?></span> من <span class="font-num font-bold"><?= $totalSuppliers ?></span></p>
                <div class="flex items-center gap-2">
                    <?php if ($page > 1): ?>
                    <a href="<?= sanitize(suppliersPageUrl($page - 1, $baseQuery)) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50">السابق</a>
                    <?php else: ?><span class="px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-100 text-slate-400">السابق</span><?php endif; ?>
                    <span class="font-num text-slate-600">صفحة <?= $page ?> / <?= $totalPages ?></span>
                    <?php if ($page < $totalPages): ?>
                    <a href="<?= sanitize(suppliersPageUrl($page + 1, $baseQuery)) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50">التالي</a>
                    <?php else: ?><span class="px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-100 text-slate-400">التالي</span><?php endif; ?>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="p-4 border-b border-slate-100">
                    <h3 class="font-bold text-slate-900">قائمة الموردين</h3>
                </div>
                <div class="overflow-auto">
                    <table class="w-full text-sm text-right">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="p-3 w-12 text-center"><input type="checkbox" id="suppliers-select-all" class="rounded border-slate-300"></th>
                                <th class="p-3">المورد</th>
                                <th class="p-3">الاتصال</th>
                                <th class="p-3">إجمالي المشتريات</th>
                                <th class="p-3">إجمالي المدفوع</th>
                                <th class="p-3">الرصيد المستحق</th>
                                <th class="p-3">الحالة</th>
                                <th class="p-3">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($suppliers)): ?>
                            <tr><td colspan="8" class="p-6 text-center text-slate-400">لا يوجد موردون بعد</td></tr>
                            <?php endif; ?>
                            <?php foreach ($suppliers as $s): ?>
                            <tr>
                                <td class="p-3 text-center"><input type="checkbox" class="supplier-row-check rounded border-slate-300" value="<?= intval($s['id']) ?>"></td>
                                <td class="p-3">
                                    <p class="font-semibold text-slate-900"><?= sanitize($s['name']) ?></p>
                                    <p class="text-xs text-slate-400"><?= sanitize($s['address'] ?? '') ?></p>
                                </td>
                                <td class="p-3">
                                    <p class="font-num text-xs"><?= sanitize($s['phone'] ?? '—') ?></p>
                                    <p class="text-xs text-slate-400"><?= sanitize($s['email'] ?? '') ?></p>
                                </td>
                                <td class="p-3 font-num"><?= number_format(floatval($s['purchases_total']), 2) ?></td>
                                <td class="p-3 font-num"><?= number_format(floatval($s['payments_total']), 2) ?></td>
                                <td class="p-3 font-num font-bold <?= floatval($s['current_balance']) > 0 ? 'text-red-600' : 'text-emerald-600' ?>">
                                    <?= number_format(floatval($s['current_balance']), 2) ?>
                                </td>
                                <td class="p-3">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= intval($s['is_active']) === 1 ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>">
                                        <?= intval($s['is_active']) === 1 ? 'نشط' : 'متوقف' ?>
                                    </span>
                                </td>
                                <td class="p-3">
                                    <div class="flex items-center gap-1">
                                    <a href="finance-reports.php?supplier_id=<?= intval($s['id']) ?>" class="px-3 py-1.5 rounded-lg text-xs font-medium bg-blue-50 text-blue-700 hover:bg-blue-100">
                                        كشف حساب
                                    </a>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="toggle_supplier">
                                        <input type="hidden" name="supplier_id" value="<?= intval($s['id']) ?>">
                                        <?php csrfInput(); ?>
                                        <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-medium <?= intval($s['is_active']) === 1 ? 'bg-red-50 text-red-700 hover:bg-red-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' ?>">
                                            <?= intval($s['is_active']) === 1 ? 'تعطيل' : 'تفعيل' ?>
                                        </button>
                                    </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-3 flex items-center justify-between text-sm">
                <p class="text-slate-500">المعروض: <span class="font-num font-bold"><?= count($suppliers) ?></span> من <span class="font-num font-bold"><?= $totalSuppliers ?></span></p>
                <div class="flex items-center gap-2">
                    <?php if ($page > 1): ?>
                    <a href="<?= sanitize(suppliersPageUrl($page - 1, $baseQuery)) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50">السابق</a>
                    <?php else: ?><span class="px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-100 text-slate-400">السابق</span><?php endif; ?>
                    <span class="font-num text-slate-600">صفحة <?= $page ?> / <?= $totalPages ?></span>
                    <?php if ($page < $totalPages): ?>
                    <a href="<?= sanitize(suppliersPageUrl($page + 1, $baseQuery)) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50">التالي</a>
                    <?php else: ?><span class="px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-100 text-slate-400">التالي</span><?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
document.getElementById('suppliers-select-all')?.addEventListener('change', function() {
    document.querySelectorAll('.supplier-row-check').forEach(ch => ch.checked = this.checked);
});

function submitSelectedSuppliersExport() {
    const ids = Array.from(document.querySelectorAll('.supplier-row-check:checked')).map(ch => ch.value);
    if (!ids.length) {
        alert('اختر موردًا واحدًا على الأقل للتصدير');
        return;
    }
    const form = document.getElementById('suppliers-export-form');
    form.querySelectorAll('input[name="selected_ids[]"]').forEach(el => el.remove());
    ids.forEach(id => {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'selected_ids[]';
        inp.value = id;
        form.appendChild(inp);
    });
    form.submit();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
