<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole(['admin']);
$pageTitle = 'إدارة العملاء';
$user = Auth::user();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $db->insert(
            "INSERT INTO customers (name, phone, email, address, notes) VALUES (?, ?, ?, ?, ?)",
            [$_POST['name'], $_POST['phone'] ?? '', $_POST['email'] ?? '', $_POST['address'] ?? '', $_POST['notes'] ?? '']
        );
        logActivity('إضافة عميل', 'customer', null, $_POST['name']);
        setFlash('success', 'تم إضافة العميل بنجاح');
        header('Location: customers.php');
        exit;
    }
    if ($_POST['action'] === 'update') {
        $db->query(
            "UPDATE customers SET name=?, phone=?, email=?, address=?, notes=?, updated_at=CURRENT_TIMESTAMP WHERE id=?",
            [$_POST['name'], $_POST['phone'] ?? '', $_POST['email'] ?? '', $_POST['address'] ?? '', $_POST['notes'] ?? '', $_POST['customer_id']]
        );
        logActivity('تعديل عميل', 'customer', $_POST['customer_id'], $_POST['name']);
        setFlash('success', 'تم تحديث بيانات العميل');
        header('Location: customers.php');
        exit;
    }
    if ($_POST['action'] === 'delete') {
        $db->query("UPDATE customers SET is_active=0 WHERE id=?", [$_POST['customer_id']]);
        logActivity('حذف عميل', 'customer', $_POST['customer_id']);
        setFlash('success', 'تم حذف العميل');
        header('Location: customers.php');
        exit;
    }
}

$search = $_GET['q'] ?? '';
if ($search) {
    $customers = $db->fetchAll("SELECT * FROM customers WHERE is_active=1 AND (name LIKE ? OR phone LIKE ?) ORDER BY name", ["%$search%", "%$search%"]);
} else {
    $customers = $db->fetchAll("SELECT * FROM customers WHERE is_active=1 ORDER BY name");
}

include __DIR__ . '/../includes/header.php';
?>

<div class="flex h-screen overflow-hidden">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="flex-1 overflow-y-auto bg-background-light">
        <header class="bg-white border-b border-slate-200 px-6 py-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">إدارة العملاء</h1>
                    <p class="text-sm text-slate-500 mt-1">سجل العملاء والمعلومات</p>
                </div>
                <div class="flex items-center gap-3">
                    <form method="GET" class="relative">
                        <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-400"><span class="material-icons-outlined text-sm">search</span></span>
                        <input type="text" name="q" value="<?= sanitize($search) ?>" placeholder="بحث بالاسم أو الهاتف..." class="bg-slate-50 border border-slate-200 rounded-lg pr-10 pl-4 py-2 text-sm focus:outline-none focus:border-primary w-60">
                    </form>
                    <button onclick="openModal('create')" class="bg-primary hover:bg-primary-dark text-white px-5 py-2.5 rounded-xl shadow-lg shadow-primary/30 font-medium text-sm flex items-center gap-2 transition-all">
                        <span class="material-icons-outlined text-base">person_add</span>
                        إضافة عميل
                    </button>
                </div>
            </div>
        </header>

        <div class="p-6">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-auto">
                    <table class="w-full text-right">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="p-4 text-xs font-semibold text-slate-500">العميل</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">الهاتف</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">البريد</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">الرصيد</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($customers)): ?>
                            <tr><td colspan="5" class="p-8 text-center text-slate-400">لا يوجد عملاء</td></tr>
                            <?php endif; ?>
                            <?php foreach ($customers as $c): ?>
                            <tr class="hover:bg-slate-50 transition-colors group">
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600 font-bold text-sm"><?= mb_substr($c['name'], 0, 1) ?></div>
                                        <div>
                                            <p class="text-sm font-medium"><?= sanitize($c['name']) ?></p>
                                            <?php if ($c['address']): ?><p class="text-xs text-slate-400"><?= sanitize($c['address']) ?></p><?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 text-sm font-num text-slate-500"><?= sanitize($c['phone']) ?: '—' ?></td>
                                <td class="p-4 text-sm font-num text-slate-500"><?= sanitize($c['email']) ?: '—' ?></td>
                                <td class="p-4 text-sm font-num <?= $c['balance'] > 0 ? 'text-red-600 font-bold' : 'text-slate-500' ?>"><?= number_format($c['balance'], 2) ?></td>
                                <td class="p-4">
                                    <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button onclick='openModal("update", <?= json_encode($c, JSON_UNESCAPED_UNICODE) ?>)' class="p-1.5 hover:bg-slate-100 rounded-lg text-slate-500 hover:text-primary transition-colors"><span class="material-icons-outlined text-[18px]">edit</span></button>
                                        <form method="POST" onsubmit="return confirm('حذف هذا العميل؟')" class="inline-block">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="customer_id" value="<?= $c['id'] ?>">
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

<!-- Customer Modal -->
<div id="customer-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden border border-slate-100">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h2 class="text-lg font-bold" id="modal-title">إضافة عميل جديد</h2>
            <button onclick="closeModal()" class="p-2 rounded-full hover:bg-slate-100 text-slate-500"><span class="material-icons-outlined">close</span></button>
        </div>
        <form id="customer-form" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" id="form-action" value="create">
            <input type="hidden" name="customer_id" id="form-id" value="">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">الاسم *</label>
                    <input type="text" name="name" id="f-name" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">الهاتف</label>
                    <input type="text" name="phone" id="f-phone" dir="ltr" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 text-sm font-num text-left focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">البريد الإلكتروني</label>
                    <input type="email" name="email" id="f-email" dir="ltr" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 text-sm font-num text-left focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">العنوان</label>
                    <input type="text" name="address" id="f-address" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">ملاحظات</label>
                <textarea name="notes" id="f-notes" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary resize-none"></textarea>
            </div>
            <div class="pt-4 border-t border-slate-100">
                <button type="submit" class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-3 rounded-xl shadow-lg shadow-primary/30 transition-all">
                    <span id="submit-text">إضافة العميل</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(action, c = null) {
    document.getElementById('form-action').value = action;
    if (action === 'update' && c) {
        document.getElementById('form-id').value = c.id;
        document.getElementById('f-name').value = c.name;
        document.getElementById('f-phone').value = c.phone || '';
        document.getElementById('f-email').value = c.email || '';
        document.getElementById('f-address').value = c.address || '';
        document.getElementById('f-notes').value = c.notes || '';
        document.getElementById('modal-title').textContent = 'تعديل بيانات العميل';
        document.getElementById('submit-text').textContent = 'حفظ التعديلات';
    } else {
        document.getElementById('customer-form').reset();
        document.getElementById('form-id').value = '';
        document.getElementById('modal-title').textContent = 'إضافة عميل جديد';
        document.getElementById('submit-text').textContent = 'إضافة العميل';
    }
    document.getElementById('customer-modal').classList.remove('hidden');
}
function closeModal() { document.getElementById('customer-modal').classList.add('hidden'); }
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
