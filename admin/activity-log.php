<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole(['admin']);
$pageTitle = 'سجل النشاطات';
$user = Auth::user();

// Filters
$filterUser = $_GET['user'] ?? '';
$filterAction = $_GET['action'] ?? '';
$filterDate = $_GET['date'] ?? '';

$sql = "SELECT * FROM audit_log WHERE 1=1";
$params = [];

if ($filterUser) {
    $sql .= " AND user_name LIKE ?";
    $params[] = "%$filterUser%";
}
if ($filterAction) {
    $sql .= " AND action LIKE ?";
    $params[] = "%$filterAction%";
}
if ($filterDate) {
    $sql .= " AND date(created_at) = ?";
    $params[] = $filterDate;
}

$sql .= " ORDER BY created_at DESC LIMIT 200";
$logs = $db->fetchAll($sql, $params);

include __DIR__ . '/../includes/header.php';
?>

<div class="flex h-screen overflow-hidden">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="flex-1 overflow-y-auto bg-background-light">
        <header class="bg-white border-b border-slate-200 px-6 py-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">سجل النشاطات</h1>
                    <p class="text-sm text-slate-500 mt-1">تتبع جميع العمليات في النظام</p>
                </div>
                <form method="GET" class="flex items-center gap-2 flex-wrap">
                    <input type="text" name="user" value="<?= sanitize($filterUser) ?>" placeholder="المستخدم" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary w-32">
                    <input type="text" name="action" value="<?= sanitize($filterAction) ?>" placeholder="نوع العملية" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary w-36">
                    <input type="date" name="date" value="<?= $filterDate ?>" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-num focus:outline-none focus:border-primary">
                    <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors">تصفية</button>
                    <a href="activity-log.php" class="text-slate-400 hover:text-slate-600 text-sm px-2">مسح</a>
                </form>
            </div>
        </header>

        <div class="p-6">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-auto max-h-[calc(100vh-200px)]">
                    <table class="w-full text-right">
                        <thead class="bg-slate-50 sticky top-0 z-10">
                            <tr>
                                <th class="p-4 text-xs font-semibold text-slate-500">التاريخ</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">المستخدم</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">العملية</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">النوع</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">التفاصيل</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">IP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($logs)): ?>
                            <tr><td colspan="6" class="p-8 text-center text-slate-400">لا توجد سجلات</td></tr>
                            <?php endif; ?>
                            <?php foreach ($logs as $log): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="p-4 text-xs font-num text-slate-400 whitespace-nowrap"><?= formatDateTimeAr($log['created_at']) ?></td>
                                <td class="p-4">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 text-xs font-bold"><?= mb_substr($log['user_name'] ?? '', 0, 1) ?></div>
                                        <span class="text-sm"><?= sanitize($log['user_name'] ?? '—') ?></span>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <span class="inline-flex px-2 py-1 rounded-md text-xs font-medium bg-primary/10 text-primary"><?= sanitize($log['action']) ?></span>
                                </td>
                                <td class="p-4 text-sm text-slate-500"><?= sanitize($log['entity_type'] ?? '—') ?></td>
                                <td class="p-4 text-sm text-slate-500 max-w-xs truncate"><?= sanitize($log['details'] ?? '—') ?></td>
                                <td class="p-4 text-xs font-num text-slate-300"><?= sanitize($log['ip_address'] ?? '') ?></td>
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
