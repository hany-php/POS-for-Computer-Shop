<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole(['admin']);
$pageTitle = 'إدارة الفئات';
$user = Auth::user();

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
                $rows = $db->fetchAll("SELECT name, sort_order FROM categories WHERE is_active = 1 AND id IN ($ph) ORDER BY sort_order, name", $ids);
            }
        }
        if (empty($rows)) {
            $rows = $db->fetchAll("SELECT name, sort_order FROM categories WHERE is_active = 1 ORDER BY sort_order, name");
        }
        $out = array_map(fn($r) => [$r['name'], $r['sort_order']], $rows);
        outputCsvDownload('categories_export_' . date('Ymd_His') . '.csv', ['name', 'sort_order'], $out);
    }

    if ($_POST['action'] === 'download_template') {
        outputCsvDownload(
            'categories_template.csv',
            ['name', 'sort_order'],
            [['فئة تجريبية', 10]]
        );
    }

    if ($_POST['action'] === 'import_csv') {
        try {
            $rows = parseUploadedCsvAssoc($_FILES['csv_file'] ?? []);
            $inserted = 0;
            $updated = 0;
            foreach ($rows as $r) {
                $name = trim($r['name'] ?? $r['category_name'] ?? '');
                if ($name === '') {
                    continue;
                }
                $sortOrder = intval($r['sort_order'] ?? 0);

                $existing = $db->fetchOne("SELECT id FROM categories WHERE name = ? LIMIT 1", [$name]);
                if ($existing) {
                    $db->query("UPDATE categories SET sort_order = ?, is_active = 1 WHERE id = ?", [$sortOrder, $existing['id']]);
                    $updated++;
                } else {
                    $db->insert("INSERT INTO categories (name, sort_order, is_active) VALUES (?, ?, 1)", [$name, $sortOrder]);
                    $inserted++;
                }
            }
            logActivity('استيراد فئات CSV', 'category', null, "inserted:$inserted, updated:$updated");
            setFlash('success', "تم استيراد الفئات بنجاح (إضافة: $inserted ، تحديث: $updated)");
        } catch (Exception $e) {
            setFlash('error', 'فشل استيراد CSV: ' . $e->getMessage());
        }
        header('Location: categories.php');
        exit;
    }

    if ($_POST['action'] === 'create') {
        $db->insert(
            "INSERT INTO categories (name, sort_order, is_active) VALUES (?, ?, 1)",
            [$_POST['name'], intval($_POST['sort_order'] ?? 0)]
        );
        setFlash('success', 'تم إضافة الفئة بنجاح');
        logActivity('إضافة فئة', 'category', null, $_POST['name']);
        header('Location: categories.php');
        exit;
    }

    if ($_POST['action'] === 'update') {
        $db->query(
            "UPDATE categories SET name=?, sort_order=? WHERE id=?",
            [$_POST['name'], intval($_POST['sort_order'] ?? 0), $_POST['category_id']]
        );
        setFlash('success', 'تم تحديث الفئة بنجاح');
        logActivity('تعديل فئة', 'category', $_POST['category_id'], $_POST['name']);
        header('Location: categories.php');
        exit;
    }

    if ($_POST['action'] === 'delete') {
        $db->query("UPDATE categories SET is_active = 0 WHERE id = ?", [$_POST['category_id']]);
        setFlash('success', 'تم حذف الفئة');
        logActivity('حذف فئة', 'category', $_POST['category_id']);
        header('Location: categories.php');
        exit;
    }
}

$whereSql = " FROM categories WHERE is_active = 1";
$params = [];
if ($search !== '') {
    $whereSql .= " AND name LIKE ?";
    $params[] = '%' . $search . '%';
}

$totalCategories = intval(($db->fetchOne("SELECT COUNT(*) AS cnt" . $whereSql, $params)['cnt'] ?? 0));
$totalPages = max(1, (int)ceil($totalCategories / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$categories = $db->fetchAll("SELECT *" . $whereSql . " ORDER BY sort_order ASC, name ASC LIMIT $perPage OFFSET $offset", $params);

$baseQuery = $_GET;
unset($baseQuery['page']);
function categoriesPageUrl($targetPage, $baseQuery) {
    $q = $baseQuery;
    $q['page'] = $targetPage;
    return 'categories.php?' . http_build_query($q);
}

include __DIR__ . '/../includes/header.php';
?>

<div class="flex h-screen overflow-hidden">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-background-light">
        <header class="bg-white border-b border-slate-200 px-6 py-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">إدارة الفئات</h1>
                    <p class="text-sm text-slate-500 mt-1">تضيف وتعديل فئات المنتجات</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <form method="GET" class="relative">
                        <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-400"><span class="material-icons-outlined text-sm">search</span></span>
                        <input type="hidden" name="per_page" value="<?= $perPage ?>">
                        <input type="text" name="q" value="<?= sanitize($search) ?>" placeholder="بحث باسم الفئة..." class="bg-slate-50 border border-slate-200 rounded-lg pr-10 pl-4 py-2 text-sm focus:outline-none focus:border-primary w-56">
                    </form>
                    <form method="POST" id="categories-export-form" class="inline">
                        <input type="hidden" name="action" value="export_csv">
                        <?php csrfInput(); ?>
                        <button type="submit" class="bg-white border border-slate-200 text-slate-700 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-slate-50">تصدير CSV</button>
                    </form>
                    <button type="button" onclick="submitSelectedCategoriesExport()" class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-blue-100">تصدير المحدد</button>
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
                    <button onclick="openModal('create')" class="bg-primary hover:bg-primary-dark text-white px-5 py-2.5 rounded-xl shadow-lg shadow-primary/30 font-medium text-sm flex items-center gap-2 transition-all">
                        <span class="material-icons-outlined text-base">add</span>
                        إضافة فئة
                    </button>
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
            <div class="mb-3 flex items-center justify-between text-sm max-w-5xl mx-auto w-full">
                <p class="text-slate-500">المعروض: <span class="font-num font-bold"><?= count($categories) ?></span> من <span class="font-num font-bold"><?= $totalCategories ?></span></p>
                <div class="flex items-center gap-2">
                    <?php if ($page > 1): ?>
                    <a href="<?= sanitize(categoriesPageUrl($page - 1, $baseQuery)) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50">السابق</a>
                    <?php else: ?><span class="px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-100 text-slate-400">السابق</span><?php endif; ?>
                    <span class="font-num text-slate-600">صفحة <?= $page ?> / <?= $totalPages ?></span>
                    <?php if ($page < $totalPages): ?>
                    <a href="<?= sanitize(categoriesPageUrl($page + 1, $baseQuery)) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50">التالي</a>
                    <?php else: ?><span class="px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-100 text-slate-400">التالي</span><?php endif; ?>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden max-w-5xl mx-auto w-full">
                <div class="overflow-auto">
                    <table class="w-full text-right">
                        <thead class="bg-slate-50 sticky top-0 z-10">
                            <tr>
                                <th class="p-4 text-xs font-semibold text-slate-500 w-12 text-center"><input type="checkbox" id="categories-select-all" class="rounded border-slate-300"></th>
                                <th class="p-4 text-xs font-semibold text-slate-500 w-20 text-center">الترتيب</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">اسم الفئة</th>
                                <th class="p-4 text-xs font-semibold text-slate-500 w-32">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($categories)): ?>
                            <tr><td colspan="4" class="p-8 text-center text-slate-400">لا توجد فئات حالياً</td></tr>
                            <?php endif; ?>
                            <?php foreach ($categories as $cat): ?>
                            <tr class="hover:bg-slate-50 transition-colors group">
                                <td class="p-4 text-center"><input type="checkbox" class="category-row-check rounded border-slate-300" value="<?= intval($cat['id']) ?>"></td>
                                <td class="p-4 text-sm font-num text-center text-slate-500"><?= $cat['sort_order'] ?></td>
                                <td class="p-4 text-sm font-medium text-slate-900"><?= sanitize($cat['name']) ?></td>
                                <td class="p-4">
                                    <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity justify-end">
                                        <button onclick='openModal("update", <?= json_encode($cat, JSON_UNESCAPED_UNICODE) ?>)' class="p-1.5 hover:bg-slate-100 rounded-lg text-slate-500 hover:text-primary transition-colors"><span class="material-icons-outlined text-[18px]">edit</span></button>
                                        <form method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذه الفئة؟')" class="inline-block">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                            <?php csrfInput(); ?>
                                            <button type="submit" class="p-1.5 hover:bg-red-50 rounded-lg text-slate-500 hover:text-red-500 transition-colors"><span class="material-icons-outlined text-[18px]">delete</span></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-3 flex items-center justify-between text-sm max-w-5xl mx-auto w-full">
                <p class="text-slate-500">المعروض: <span class="font-num font-bold"><?= count($categories) ?></span> من <span class="font-num font-bold"><?= $totalCategories ?></span></p>
                <div class="flex items-center gap-2">
                    <?php if ($page > 1): ?>
                    <a href="<?= sanitize(categoriesPageUrl($page - 1, $baseQuery)) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50">السابق</a>
                    <?php else: ?><span class="px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-100 text-slate-400">السابق</span><?php endif; ?>
                    <span class="font-num text-slate-600">صفحة <?= $page ?> / <?= $totalPages ?></span>
                    <?php if ($page < $totalPages): ?>
                    <a href="<?= sanitize(categoriesPageUrl($page + 1, $baseQuery)) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50">التالي</a>
                    <?php else: ?><span class="px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-100 text-slate-400">التالي</span><?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<div id="category-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden border border-slate-100">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h2 class="text-lg font-bold" id="modal-title">إضافة فئة جديدة</h2>
            <button onclick="closeModal()" class="p-2 rounded-full hover:bg-slate-100 text-slate-500"><span class="material-icons-outlined">close</span></button>
        </div>
        <form id="category-form" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" id="form-action" value="create">
            <input type="hidden" name="category_id" id="form-category-id" value="">
            <?php csrfInput(); ?>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">اسم الفئة *</label>
                <input type="text" name="name" id="f-name" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary" placeholder="مثال: لاب توب">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">ترتيب العرض</label>
                <input type="number" name="sort_order" id="f-sort" dir="ltr" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 text-sm font-num focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary text-left" placeholder="0">
                <p class="text-xs text-slate-400 mt-1">الرقم الأصغر يظهر أولاً</p>
            </div>
            <div class="pt-4 border-t border-slate-100">
                <button type="submit" class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-3 rounded-xl shadow-lg shadow-primary/30 transition-all">
                    <span id="submit-text">إضافة الفئة</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(action, cat = null) {
    document.getElementById('form-action').value = action;
    if (action === 'update' && cat) {
        document.getElementById('form-category-id').value = cat.id;
        document.getElementById('f-name').value = cat.name;
        document.getElementById('f-sort').value = cat.sort_order;
        document.getElementById('modal-title').textContent = 'تعديل الفئة';
        document.getElementById('submit-text').textContent = 'حفظ التعديلات';
    } else {
        document.getElementById('form-category-id').value = '';
        document.getElementById('category-form').reset();
        document.getElementById('modal-title').textContent = 'إضافة فئة جديدة';
        document.getElementById('submit-text').textContent = 'إضافة الفئة';
    }
    document.getElementById('category-modal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('category-modal').classList.add('hidden');
}

document.getElementById('categories-select-all')?.addEventListener('change', function() {
    document.querySelectorAll('.category-row-check').forEach(ch => ch.checked = this.checked);
});

function submitSelectedCategoriesExport() {
    const ids = Array.from(document.querySelectorAll('.category-row-check:checked')).map(ch => ch.value);
    if (!ids.length) {
        alert('اختر فئة واحدة على الأقل للتصدير');
        return;
    }
    const form = document.getElementById('categories-export-form');
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

document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
