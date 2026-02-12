<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole(['admin']);
$pageTitle = 'إدارة الفئات';
$user = Auth::user();

// Handle category actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
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
        // Soft delete
        $db->query("UPDATE categories SET is_active = 0 WHERE id = ?", [$_POST['category_id']]);
        setFlash('success', 'تم حذف الفئة');
        logActivity('حذف فئة', 'category', $_POST['category_id']);
        header('Location: categories.php');
        exit;
    }
}

$categories = $db->fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");

include __DIR__ . '/../includes/header.php';
?>

<div class="flex h-screen overflow-hidden">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-background-light">
        <!-- Page Header -->
        <header class="bg-white border-b border-slate-200 px-6 py-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">إدارة الفئات</h1>
                    <p class="text-sm text-slate-500 mt-1">تضيف وتعديل فئات المنتجات</p>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="openModal('create')" class="bg-primary hover:bg-primary-dark text-white px-5 py-2.5 rounded-xl shadow-lg shadow-primary/30 font-medium text-sm flex items-center gap-2 transition-all">
                        <span class="material-icons-outlined text-base">add</span>
                        إضافة فئة
                    </button>
                </div>
            </div>
        </header>

        <div class="p-6 space-y-6">
            <!-- Categories Table -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden max-w-4xl mx-auto">
                <div class="overflow-auto">
                    <table class="w-full text-right">
                        <thead class="bg-slate-50 sticky top-0 z-10">
                            <tr>
                                <th class="p-4 text-xs font-semibold text-slate-500 w-20 text-center">الترتيب</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">اسم الفئة</th>
                                <th class="p-4 text-xs font-semibold text-slate-500 w-32">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($categories)): ?>
                            <tr><td colspan="3" class="p-8 text-center text-slate-400">لا توجد فئات حالياً</td></tr>
                            <?php endif; ?>
                            <?php foreach ($categories as $cat): ?>
                            <tr class="hover:bg-slate-50 transition-colors group">
                                <td class="p-4 text-sm font-num text-center text-slate-500"><?= $cat['sort_order'] ?></td>
                                <td class="p-4 text-sm font-medium text-slate-900"><?= sanitize($cat['name']) ?></td>
                                <td class="p-4">
                                    <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity justify-end">
                                        <button onclick='openModal("update", <?= json_encode($cat, JSON_UNESCAPED_UNICODE) ?>)' class="p-1.5 hover:bg-slate-100 rounded-lg text-slate-500 hover:text-primary transition-colors"><span class="material-icons-outlined text-[18px]">edit</span></button>
                                        <form method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذه الفئة؟')" class="inline-block">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
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
        </div>
    </main>
</div>

<!-- Add/Edit Category Modal -->
<div id="category-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden border border-slate-100">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h2 class="text-lg font-bold" id="modal-title">إضافة فئة جديدة</h2>
            <button onclick="closeModal()" class="p-2 rounded-full hover:bg-slate-100 text-slate-500"><span class="material-icons-outlined">close</span></button>
        </div>
        <form id="category-form" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" id="form-action" value="create">
            <input type="hidden" name="category_id" id="form-category-id" value="">
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

document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
