<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole(['admin']);
$pageTitle = 'إدارة المستخدمين';
$user = Auth::user();

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCsrfTokenOrFail();
    if ($_POST['action'] === 'create') {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $pinRaw = trim($_POST['pin'] ?? '');
        $pinHash = $pinRaw !== '' ? password_hash($pinRaw, PASSWORD_DEFAULT) : null;
        $db->insert(
            "INSERT INTO users (username, password, pin, pin_hash, full_name, role, is_active) VALUES (?, ?, NULL, ?, ?, ?, 1)",
            [$_POST['username'], $password, $pinHash, $_POST['full_name'], $_POST['role']]
        );
        logActivity('إضافة مستخدم', 'user', null, $_POST['full_name'] . ' (' . $_POST['role'] . ')');
        setFlash('success', 'تم إضافة المستخدم بنجاح');
        header('Location: users.php');
        exit;
    }
    if ($_POST['action'] === 'update') {
        $pinRaw = trim($_POST['pin'] ?? '');
        $pinHash = $pinRaw !== '' ? password_hash($pinRaw, PASSWORD_DEFAULT) : null;
        $sql = "UPDATE users SET username=?, full_name=?, role=?, pin=NULL, pin_hash=? WHERE id=?";
        $params = [$_POST['username'], $_POST['full_name'], $_POST['role'], $pinHash, $_POST['user_id']];
        $db->query($sql, $params);
        
        // Update password only if provided
        if (!empty($_POST['password'])) {
            $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $db->query("UPDATE users SET password=? WHERE id=?", [$hash, $_POST['user_id']]);
        }
        logActivity('تعديل مستخدم', 'user', $_POST['user_id'], $_POST['full_name']);
        setFlash('success', 'تم تحديث المستخدم بنجاح');
        header('Location: users.php');
        exit;
    }
    if ($_POST['action'] === 'toggle') {
        $u = $db->fetchOne("SELECT * FROM users WHERE id=?", [$_POST['user_id']]);
        $newState = $u['is_active'] ? 0 : 1;
        $db->query("UPDATE users SET is_active=? WHERE id=?", [$newState, $_POST['user_id']]);
        logActivity($newState ? 'تفعيل مستخدم' : 'تعطيل مستخدم', 'user', $_POST['user_id'], $u['full_name']);
        setFlash('success', $newState ? 'تم تفعيل المستخدم' : 'تم تعطيل المستخدم');
        header('Location: users.php');
        exit;
    }
}

$users = $db->fetchAll("SELECT * FROM users ORDER BY is_active DESC, created_at DESC");

include __DIR__ . '/../includes/header.php';
?>

<div class="flex h-screen overflow-hidden">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-background-light">
        <header class="bg-white border-b border-slate-200 px-6 py-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">إدارة المستخدمين</h1>
                    <p class="text-sm text-slate-500 mt-1">إضافة وتعديل صلاحيات المستخدمين</p>
                </div>
                <button onclick="openModal('create')" class="bg-primary hover:bg-primary-dark text-white px-5 py-2.5 rounded-xl shadow-lg shadow-primary/30 font-medium text-sm flex items-center gap-2 transition-all">
                    <span class="material-icons-outlined text-base">person_add</span>
                    إضافة مستخدم
                </button>
            </div>
        </header>

        <div class="p-6">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-auto">
                    <table class="w-full text-right">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="p-4 text-xs font-semibold text-slate-500">المستخدم</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">اسم المستخدم</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">الدور</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">PIN</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">الحالة</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($users as $u): ?>
                            <tr class="hover:bg-slate-50 transition-colors group <?= !$u['is_active'] ? 'opacity-50' : '' ?>">
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-sm">
                                            <?= mb_substr($u['full_name'], 0, 1) ?>
                                        </div>
                                        <span class="text-sm font-medium"><?= sanitize($u['full_name']) ?></span>
                                    </div>
                                </td>
                                <td class="p-4 text-sm text-slate-500 font-num"><?= sanitize($u['username']) ?></td>
                                <td class="p-4">
                                    <?php
                                    $roleColors = ['admin' => 'bg-purple-50 text-purple-700 border-purple-100', 'cashier' => 'bg-blue-50 text-blue-700 border-blue-100', 'technician' => 'bg-orange-50 text-orange-700 border-orange-100'];
                                    ?>
                                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium border <?= $roleColors[$u['role']] ?? '' ?>"><?= Auth::getRoleNameAr($u['role']) ?></span>
                                </td>
                                <td class="p-4 text-sm font-num text-slate-400"><?= (!empty($u['pin']) || !empty($u['pin_hash'])) ? '••••' : '—' ?></td>
                                <td class="p-4">
                                    <?php if ($u['is_active']): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>نشط</span>
                                    <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-red-50 text-red-700"><span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>معطل</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4">
                                    <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button onclick='openModal("update", <?= json_encode($u, JSON_UNESCAPED_UNICODE) ?>)' class="p-1.5 hover:bg-slate-100 rounded-lg text-slate-500 hover:text-primary transition-colors" title="تعديل"><span class="material-icons-outlined text-[18px]">edit</span></button>
                                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                        <form method="POST" class="inline-block" onsubmit="return confirm('<?= $u['is_active'] ? 'تعطيل' : 'تفعيل' ?> هذا المستخدم؟')">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <?php csrfInput(); ?>
                                            <button type="submit" class="p-1.5 hover:bg-<?= $u['is_active'] ? 'red' : 'green' ?>-50 rounded-lg text-slate-500 hover:text-<?= $u['is_active'] ? 'red' : 'green' ?>-500 transition-colors" title="<?= $u['is_active'] ? 'تعطيل' : 'تفعيل' ?>">
                                                <span class="material-icons-outlined text-[18px]"><?= $u['is_active'] ? 'block' : 'check_circle' ?></span>
                                            </button>
                                        </form>
                                        <?php endif; ?>
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

<!-- User Modal -->
<div id="user-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden border border-slate-100">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h2 class="text-lg font-bold" id="modal-title">إضافة مستخدم جديد</h2>
            <button onclick="closeModal()" class="p-2 rounded-full hover:bg-slate-100 text-slate-500"><span class="material-icons-outlined">close</span></button>
        </div>
        <form id="user-form" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" id="form-action" value="create">
            <input type="hidden" name="user_id" id="form-user-id" value="">
            <?php csrfInput(); ?>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">الاسم الكامل *</label>
                <input type="text" name="full_name" id="f-name" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">اسم المستخدم *</label>
                    <input type="text" name="username" id="f-username" required dir="ltr" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 text-sm font-num text-left focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">الدور *</label>
                    <select name="role" id="f-role" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                        <option value="admin">مدير النظام</option>
                        <option value="cashier">كاشير</option>
                        <option value="technician">فني صيانة</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" id="pwd-label">كلمة المرور *</label>
                    <input type="password" name="password" id="f-password" dir="ltr" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 text-sm font-num text-left focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">رقم PIN (للكاشير)</label>
                    <input type="text" name="pin" id="f-pin" dir="ltr" maxlength="6" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 text-sm font-num text-left focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary" placeholder="1234">
                </div>
            </div>
            <div class="pt-4 border-t border-slate-100">
                <button type="submit" class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-3 rounded-xl shadow-lg shadow-primary/30 transition-all">
                    <span id="submit-text">إضافة المستخدم</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(action, u = null) {
    document.getElementById('form-action').value = action;
    if (action === 'update' && u) {
        document.getElementById('form-user-id').value = u.id;
        document.getElementById('f-name').value = u.full_name;
        document.getElementById('f-username').value = u.username;
        document.getElementById('f-role').value = u.role;
        document.getElementById('f-pin').value = u.pin || '';
        document.getElementById('f-password').value = '';
        document.getElementById('f-password').removeAttribute('required');
        document.getElementById('pwd-label').textContent = 'كلمة المرور (اتركها فارغة للإبقاء)';
        document.getElementById('modal-title').textContent = 'تعديل المستخدم';
        document.getElementById('submit-text').textContent = 'حفظ التعديلات';
    } else {
        document.getElementById('user-form').reset();
        document.getElementById('form-user-id').value = '';
        document.getElementById('f-password').setAttribute('required', 'required');
        document.getElementById('pwd-label').textContent = 'كلمة المرور *';
        document.getElementById('modal-title').textContent = 'إضافة مستخدم جديد';
        document.getElementById('submit-text').textContent = 'إضافة المستخدم';
    }
    document.getElementById('user-modal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('user-modal').classList.add('hidden');
}

document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
