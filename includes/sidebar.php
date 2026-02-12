<?php
/**
 * Sidebar Navigation Template (Admin pages)
 * قالب القائمة الجانبية
 */
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
if (!isset($settings)) $settings = getStoreSettings();
$userRole = $_SESSION['role'] ?? 'cashier';
?>
<!-- Mobile Hamburger Button -->
<button id="mobile-menu-btn" onclick="document.getElementById('sidebar-overlay').classList.remove('hidden')" 
    class="md:hidden fixed top-4 right-4 z-40 bg-white shadow-lg rounded-xl p-2.5 border border-slate-200 text-slate-600 hover:text-primary transition-colors">
    <span class="material-icons-outlined">menu</span>
</button>

<!-- Mobile Sidebar Overlay -->
<div id="sidebar-overlay" class="hidden md:hidden fixed inset-0 z-50">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="document.getElementById('sidebar-overlay').classList.add('hidden')"></div>
    <aside class="relative w-72 h-full bg-white shadow-2xl flex flex-col overflow-y-auto">
        <div class="p-4 flex items-center justify-between border-b border-slate-100">
            <h1 class="font-bold text-lg"><?= sanitize($settings['store_name']) ?><span class="text-primary">.</span></h1>
            <button onclick="document.getElementById('sidebar-overlay').classList.add('hidden')" class="p-2 rounded-lg hover:bg-slate-100 text-slate-500"><span class="material-icons-outlined">close</span></button>
        </div>
        <nav class="flex-1 px-3 py-3 space-y-1">
            <?php if ($userRole === 'admin'): ?>
            <a class="flex items-center gap-3 px-4 py-2.5 text-sm <?= $currentPage === 'dashboard' ? 'bg-primary/10 text-primary' : 'text-slate-500' ?> rounded-lg" href="dashboard.php"><span class="material-icons-outlined text-xl">dashboard</span>لوحة التحكم</a>
            <a class="flex items-center gap-3 px-4 py-2.5 text-sm <?= $currentPage === 'products' ? 'bg-primary/10 text-primary' : 'text-slate-500' ?> rounded-lg" href="products.php"><span class="material-icons-outlined text-xl">inventory_2</span>المخزون</a>
            <a class="flex items-center gap-3 px-4 py-2.5 text-sm <?= $currentPage === 'categories' ? 'bg-primary/10 text-primary' : 'text-slate-500' ?> rounded-lg" href="categories.php"><span class="material-icons-outlined text-xl">category</span>الفئات</a>
            <?php endif; ?>
            <a class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-500 rounded-lg" href="../pos.php"><span class="material-icons-outlined text-xl">point_of_sale</span>نقاط البيع</a>
            <?php if ($userRole === 'admin'): ?>
            <a class="flex items-center gap-3 px-4 py-2.5 text-sm <?= $currentPage === 'sales' ? 'bg-primary/10 text-primary' : 'text-slate-500' ?> rounded-lg" href="sales.php"><span class="material-icons-outlined text-xl">receipt_long</span>المبيعات</a>
            <div class="space-y-1">
                <a class="flex items-center gap-3 px-4 py-2.5 text-sm <?= $currentPage === 'reports' ? 'bg-primary/10 text-primary' : 'text-slate-500' ?> rounded-lg" href="reports.php"><span class="material-icons-outlined text-xl">analytics</span>التقارير</a>
                <a class="flex items-center gap-3 pr-10 py-1.5 text-xs <?= $currentPage === 'advanced-reports' ? 'text-primary font-bold' : 'text-slate-400 hover:text-slate-600' ?> rounded-lg" href="advanced-reports.php">التقارير المتقدمة</a>
            </div>
            <a class="flex items-center gap-3 px-4 py-2.5 text-sm <?= $currentPage === 'customers' ? 'bg-primary/10 text-primary' : 'text-slate-500' ?> rounded-lg" href="customers.php"><span class="material-icons-outlined text-xl">people</span>العملاء</a>
            <?php endif; ?>
            <?php if (in_array($userRole, ['admin', 'technician'])): ?>
            <a class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-500 rounded-lg" href="../maintenance.php"><span class="material-icons-outlined text-xl">build</span>الصيانة</a>
            <?php endif; ?>
            <?php if ($userRole === 'admin'): ?>
            <a class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-500 rounded-lg" href="../buy-used.php"><span class="material-icons-outlined text-xl">phonelink_setup</span>شراء مستعمل</a>
            <div class="border-t border-slate-100 my-2"></div>
            <a class="flex items-center gap-3 px-4 py-2.5 text-sm <?= $currentPage === 'users' ? 'bg-primary/10 text-primary' : 'text-slate-500' ?> rounded-lg" href="users.php"><span class="material-icons-outlined text-xl">manage_accounts</span>المستخدمين</a>
            <a class="flex items-center gap-3 px-4 py-2.5 text-sm <?= $currentPage === 'activity-log' ? 'bg-primary/10 text-primary' : 'text-slate-500' ?> rounded-lg" href="activity-log.php"><span class="material-icons-outlined text-xl">history</span>سجل النشاطات</a>
            <a class="flex items-center gap-3 px-4 py-2.5 text-sm <?= $currentPage === 'backup' ? 'bg-primary/10 text-primary' : 'text-slate-500' ?> rounded-lg" href="backup.php"><span class="material-icons-outlined text-xl">backup</span>النسخ الاحتياطي</a>
            <a class="flex items-center gap-3 px-4 py-2.5 text-sm <?= $currentPage === 'settings' ? 'bg-primary/10 text-primary' : 'text-slate-500' ?> rounded-lg" href="settings.php"><span class="material-icons-outlined text-xl">settings</span>الإعدادات</a>
            <?php endif; ?>
        </nav>
        <div class="p-3 border-t border-slate-100">
            <a class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-500 hover:text-red-500" href="../logout.php"><span class="material-icons-outlined text-xl">logout</span>تسجيل الخروج</a>
        </div>
    </aside>
</div>

<!-- Desktop Sidebar -->
<aside class="w-full md:w-64 bg-white border-l border-slate-200 flex-shrink-0 hidden md:flex md:flex-col h-screen sticky top-0">
    <div class="p-6 flex items-center gap-3">
        <?php if (!empty($settings['store_logo_url'])): ?>
        <div class="w-10 h-10 rounded-lg overflow-hidden flex items-center justify-center bg-white border border-slate-100 p-0.5">
            <img src="<?= sanitize($settings['store_logo_url']) ?>" alt="Logo" class="w-full h-full object-contain">
        </div>
        <?php else: ?>
        <div class="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center text-primary">
            <span class="material-icons-outlined text-2xl">computer</span>
        </div>
        <?php endif; ?>
        <h1 class="font-bold text-xl tracking-wide"><?= sanitize($settings['store_name']) ?><span class="text-primary">.</span></h1>
    </div>
    <nav class="flex-1 px-4 py-4 space-y-1 overflow-y-auto">
        <?php if ($userRole === 'admin'): ?>
        <a class="flex items-center gap-3 px-4 py-3 <?= $currentPage === 'dashboard' ? 'bg-primary/10 text-primary' : 'text-slate-500 hover:bg-slate-50' ?> rounded-lg transition-colors group" href="dashboard.php">
            <span class="material-icons-outlined group-hover:text-primary transition-colors">dashboard</span>
            <span class="font-medium">لوحة التحكم</span>
        </a>
        <a class="flex items-center gap-3 px-4 py-3 <?= $currentPage === 'products' ? 'bg-primary/10 text-primary' : 'text-slate-500 hover:bg-slate-50' ?> rounded-lg transition-colors group" href="products.php">
            <span class="material-icons-outlined group-hover:text-primary transition-colors">inventory_2</span>
            <span class="font-medium">المخزون</span>
        </a>
        <a class="flex items-center gap-3 px-4 py-3 <?= $currentPage === 'categories' ? 'bg-primary/10 text-primary' : 'text-slate-500 hover:bg-slate-50' ?> rounded-lg transition-colors group" href="categories.php">
            <span class="material-icons-outlined group-hover:text-primary transition-colors">category</span>
            <span class="font-medium">الفئات</span>
        </a>
        <?php endif; ?>

        <a class="flex items-center gap-3 px-4 py-3 text-slate-500 hover:bg-slate-50 rounded-lg transition-colors group" href="../pos.php">
            <span class="material-icons-outlined group-hover:text-primary transition-colors">point_of_sale</span>
            <span class="font-medium">نقاط البيع</span>
        </a>

        <?php if ($userRole === 'admin'): ?>
        <a class="flex items-center gap-3 px-4 py-3 <?= $currentPage === 'sales' ? 'bg-primary/10 text-primary' : 'text-slate-500 hover:bg-slate-50' ?> rounded-lg transition-colors group" href="sales.php">
            <span class="material-icons-outlined group-hover:text-primary transition-colors">receipt_long</span>
            <span class="font-medium">المبيعات</span>
        </a>
        <div class="space-y-1">
            <a class="flex items-center gap-3 px-4 py-3 <?= in_array($currentPage, ['reports', 'advanced-reports']) ? 'bg-primary/10 text-primary' : 'text-slate-500 hover:bg-slate-50' ?> rounded-lg transition-colors group" href="reports.php">
                <span class="material-icons-outlined group-hover:text-primary transition-colors">analytics</span>
                <span class="font-medium">التقارير</span>
            </a>
            <?php if (in_array($currentPage, ['reports', 'advanced-reports'])): ?>
            <a class="flex items-center gap-3 pr-12 py-2 text-sm <?= $currentPage === 'advanced-reports' ? 'text-primary font-bold' : 'text-slate-400 hover:text-primary' ?> transition-colors" href="advanced-reports.php">
                <span class="text-xs">← التقارير المتقدمة</span>
            </a>
            <?php endif; ?>
        </div>
        <a class="flex items-center gap-3 px-4 py-3 <?= $currentPage === 'customers' ? 'bg-primary/10 text-primary' : 'text-slate-500 hover:bg-slate-50' ?> rounded-lg transition-colors group" href="customers.php">
            <span class="material-icons-outlined group-hover:text-primary transition-colors">people</span>
            <span class="font-medium">العملاء</span>
        </a>
        <?php endif; ?>

        <?php if (in_array($userRole, ['admin', 'technician'])): ?>
        <a class="flex items-center gap-3 px-4 py-3 text-slate-500 hover:bg-slate-50 rounded-lg transition-colors group" href="../maintenance.php">
            <span class="material-icons-outlined group-hover:text-primary transition-colors">build</span>
            <span class="font-medium">الصيانة</span>
        </a>
        <?php endif; ?>

        <?php if ($userRole === 'admin'): ?>
        <a class="flex items-center gap-3 px-4 py-3 text-slate-500 hover:bg-slate-50 rounded-lg transition-colors group" href="../buy-used.php">
            <span class="material-icons-outlined group-hover:text-primary transition-colors">phonelink_setup</span>
            <span class="font-medium">شراء مستعمل</span>
        </a>
        <div class="border-t border-slate-100 my-2"></div>
        <a class="flex items-center gap-3 px-4 py-3 <?= $currentPage === 'users' ? 'bg-primary/10 text-primary' : 'text-slate-500 hover:bg-slate-50' ?> rounded-lg transition-colors group" href="users.php">
            <span class="material-icons-outlined group-hover:text-primary transition-colors">manage_accounts</span>
            <span class="font-medium">المستخدمين</span>
        </a>
        <a class="flex items-center gap-3 px-4 py-3 <?= $currentPage === 'activity-log' ? 'bg-primary/10 text-primary' : 'text-slate-500 hover:bg-slate-50' ?> rounded-lg transition-colors group" href="activity-log.php">
            <span class="material-icons-outlined group-hover:text-primary transition-colors">history</span>
            <span class="font-medium">سجل النشاطات</span>
        </a>
        <a class="flex items-center gap-3 px-4 py-3 <?= $currentPage === 'backup' ? 'bg-primary/10 text-primary' : 'text-slate-500 hover:bg-slate-50' ?> rounded-lg transition-colors group" href="backup.php">
            <span class="material-icons-outlined group-hover:text-primary transition-colors">backup</span>
            <span class="font-medium">النسخ الاحتياطي</span>
        </a>
        <a class="flex items-center gap-3 px-4 py-3 <?= $currentPage === 'settings' ? 'bg-primary/10 text-primary' : 'text-slate-500 hover:bg-slate-50' ?> rounded-lg transition-colors group" href="settings.php">
            <span class="material-icons-outlined group-hover:text-primary transition-colors">settings</span>
            <span class="font-medium">الإعدادات</span>
        </a>
        <?php endif; ?>
    </nav>
    <div class="p-4 mt-auto border-t border-slate-100">
        <div class="flex items-center gap-3 px-4 py-2 mb-2">
            <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-primary text-sm font-bold">
                <?= mb_substr($user['full_name'] ?? '', 0, 1) ?>
            </div>
            <div>
                <p class="text-sm font-semibold leading-tight"><?= $user['full_name'] ?? '' ?></p>
                <p class="text-xs text-slate-400"><?= Auth::getRoleNameAr($user['role'] ?? '') ?></p>
            </div>
        </div>
        <a class="flex items-center gap-3 px-4 py-3 text-slate-500 hover:text-red-500 transition-colors" href="../logout.php">
            <span class="material-icons-outlined">logout</span>
            <span class="font-medium">تسجيل الخروج</span>
        </a>
    </div>
</aside>
