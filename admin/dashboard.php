<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole(['admin']);
$pageTitle = 'لوحة التحكم';
$user = Auth::user();
$includeChartJS = true;

// Stats
$todaySales = $db->fetchOne("SELECT COALESCE(SUM(total),0) as total, COUNT(*) as cnt FROM orders WHERE DATE(created_at) = DATE('now') AND status = 'completed'");
$monthSales = $db->fetchOne("SELECT COALESCE(SUM(total),0) as total, COUNT(*) as cnt FROM orders WHERE strftime('%Y-%m', created_at) = strftime('%Y-%m', 'now') AND status = 'completed'");
$totalProducts = $db->fetchOne("SELECT COUNT(*) as cnt FROM products WHERE is_active = 1")['cnt'];
$lowStock = $db->fetchOne("SELECT COUNT(*) as cnt FROM products WHERE is_active = 1 AND quantity <= " . LOW_STOCK_THRESHOLD)['cnt'];
$activeMaintenance = $db->fetchOne("SELECT COUNT(*) as cnt FROM maintenance_tickets WHERE status NOT IN ('delivered','cancelled')")['cnt'];

// Recent orders
$recentOrders = $db->fetchAll("SELECT o.*, u.full_name as cashier_name FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 10");

// Last 7 days sales chart data
$last7Days = $db->fetchAll("SELECT DATE(created_at) as sale_date, SUM(total) as daily_total FROM orders WHERE created_at >= date('now','-7 days') AND status='completed' GROUP BY DATE(created_at) ORDER BY sale_date ASC");
$chartLabels = array_map(fn($d) => date('m/d', strtotime($d['sale_date'])), $last7Days);
$chartData = array_map(fn($d) => round($d['daily_total'], 2), $last7Days);
$totalLast7Days = array_sum($chartData);

// Top products
$topProducts = $db->fetchAll("SELECT oi.product_name, SUM(oi.quantity) as total_qty, SUM(oi.total_price) as total_revenue FROM order_items oi INNER JOIN orders o ON oi.order_id = o.id WHERE o.status='completed' AND o.created_at >= date('now','-30 days') GROUP BY oi.product_name ORDER BY total_qty DESC LIMIT 5");

include __DIR__ . '/../includes/header.php';
?>

<div class="flex h-screen overflow-hidden">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-background-light">
        <!-- Page Header -->
        <header class="bg-white border-b border-slate-200 px-6 py-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">لوحة التحكم</h1>
                    <p class="text-sm text-slate-500 mt-1">مرحباً <?= sanitize($user['full_name']) ?>! إليك ملخص أداء المتجر</p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-sm font-num text-slate-500 bg-slate-100 px-4 py-2 rounded-lg"><?= date('Y/m/d') ?></span>
                </div>
            </div>
        </header>

        <div class="p-6 space-y-6">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4">
                <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-start justify-between mb-3">
                        <div class="p-2.5 bg-green-50 text-green-600 rounded-lg"><span class="material-icons-outlined">trending_up</span></div>
                    </div>
                    <h3 class="text-xs font-medium text-slate-500 mb-1">مبيعات اليوم</h3>
                    <p class="text-2xl font-bold font-num text-slate-900"><?= number_format($todaySales['total']) ?> <span class="text-xs text-slate-400 font-normal"><?= CURRENCY_EN ?></span></p>
                    <p class="text-xs text-slate-400 font-num mt-1"><?= $todaySales['cnt'] ?> طلبات</p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-start justify-between mb-3">
                        <div class="p-2.5 bg-blue-50 text-blue-600 rounded-lg"><span class="material-icons-outlined">calendar_month</span></div>
                    </div>
                    <h3 class="text-xs font-medium text-slate-500 mb-1">مبيعات الشهر</h3>
                    <p class="text-2xl font-bold font-num text-slate-900"><?= number_format($monthSales['total']) ?> <span class="text-xs text-slate-400 font-normal"><?= CURRENCY_EN ?></span></p>
                    <p class="text-xs text-slate-400 font-num mt-1"><?= $monthSales['cnt'] ?> طلبات</p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-start justify-between mb-3">
                        <div class="p-2.5 bg-purple-50 text-purple-600 rounded-lg"><span class="material-icons-outlined">inventory_2</span></div>
                    </div>
                    <h3 class="text-xs font-medium text-slate-500 mb-1">إجمالي المنتجات</h3>
                    <p class="text-2xl font-bold font-num text-slate-900"><?= $totalProducts ?></p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm hover:shadow-md transition-shadow <?= $lowStock > 0 ? 'ring-1 ring-red-200' : '' ?>">
                    <div class="flex items-start justify-between mb-3">
                        <div class="p-2.5 bg-red-50 text-red-600 rounded-lg"><span class="material-icons-outlined">warning</span></div>
                        <?php if ($lowStock > 0): ?>
                        <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
                        <?php endif; ?>
                    </div>
                    <h3 class="text-xs font-medium text-slate-500 mb-1">مخزون منخفض</h3>
                    <p class="text-2xl font-bold font-num text-<?= $lowStock > 0 ? 'red-600' : 'slate-900' ?>"><?= $lowStock ?></p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-start justify-between mb-3">
                        <div class="p-2.5 bg-amber-50 text-amber-600 rounded-lg"><span class="material-icons-outlined">build</span></div>
                    </div>
                    <h3 class="text-xs font-medium text-slate-500 mb-1">صيانة نشطة</h3>
                    <p class="text-2xl font-bold font-num text-slate-900"><?= $activeMaintenance ?></p>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <!-- Sales Chart -->
                <div class="xl:col-span-2 bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-bold text-slate-800 flex items-center gap-2">
                            <span class="w-2 h-6 bg-primary rounded-full"></span>
                            المبيعات (آخر 7 أيام)
                        </h3>
                    </div>
                    <div class="relative h-[250px] flex items-center justify-center">
                        <canvas id="salesChart"></canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                            <span class="text-sm text-slate-400 font-medium">الإجمالي</span>
                            <span class="text-2xl font-bold font-num text-slate-800"><?= number_format($totalLast7Days) ?></span>
                            <span class="text-xs text-slate-400"><?= CURRENCY ?></span>
                        </div>
                    </div>
                </div>

                <!-- Top Products -->
                <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-bold text-slate-800 flex items-center gap-2">
                            <span class="w-2 h-6 bg-yellow-400 rounded-full"></span>
                            الأكثر مبيعاً
                        </h3>
                    </div>
                    <div class="space-y-4">
                        <?php if (empty($topProducts)): ?>
                        <p class="text-center text-slate-400 py-6">لا توجد مبيعات بعد</p>
                        <?php endif; ?>
                        <?php foreach ($topProducts as $i => $tp): ?>
                        <div class="flex items-center gap-4">
                            <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-primary font-num font-bold text-sm flex-shrink-0"><?= $i + 1 ?></div>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-sm text-slate-800 truncate"><?= sanitize($tp['product_name']) ?></p>
                                <p class="text-xs text-slate-500 font-num"><?= $tp['total_qty'] ?> وحدة</p>
                            </div>
                            <span class="font-num font-bold text-sm text-slate-900"><?= number_format($tp['total_revenue']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex justify-between items-center">
                    <h3 class="font-bold text-slate-800 flex items-center gap-2">
                        <span class="w-2 h-6 bg-primary rounded-full"></span>
                        آخر الطلبات
                    </h3>
                    <a href="sales.php" class="text-sm text-primary hover:text-primary-dark font-medium transition-colors">عرض الكل ←</a>
                </div>
                <div class="overflow-auto">
                    <table class="w-full text-right">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="p-4 text-xs font-semibold text-slate-500">رقم الطلب</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">التاريخ</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">الكاشير</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">الدفع</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">الإجمالي</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">الحالة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($recentOrders)): ?>
                            <tr><td colspan="6" class="p-8 text-center text-slate-400">لا توجد طلبات بعد</td></tr>
                            <?php endif; ?>
                            <?php foreach ($recentOrders as $o): ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="p-4 text-sm font-num font-medium"><?= $o['order_number'] ?></td>
                                <td class="p-4 text-sm text-slate-500 font-num"><?= formatDateTimeAr($o['created_at']) ?></td>
                                <td class="p-4 text-sm"><?= sanitize($o['cashier_name'] ?? '—') ?></td>
                                <td class="p-4 text-sm"><?= getPaymentMethodAr($o['payment_method']) ?></td>
                                <td class="p-4 text-sm font-num font-bold"><?= number_format($o['total'], 2) ?> <?= CURRENCY ?></td>
                                <td class="p-4">
                                    <?php if ($o['status'] === 'completed'): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-100"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>مكتمل</span>
                                    <?php elseif ($o['status'] === 'cancelled'): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-red-50 text-red-700 border border-red-100">ملغي</span>
                                    <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-yellow-50 text-yellow-700 border border-yellow-100">مسترجع</span>
                                    <?php endif; ?>
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

<script>
// Sales Chart
new Chart(document.getElementById('salesChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [{
            label: 'المبيعات (<?= CURRENCY_EN ?>)',
            data: <?= json_encode($chartData) ?>,
            backgroundColor: [
                '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#6366f1'
            ],
            borderColor: '#ffffff',
            borderWidth: 2,
            hoverOffset: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'right',
                labels: {
                    font: { family: 'Cairo' },
                    usePointStyle: true,
                    boxWidth: 8
                }
            }
        },
        cutout: '60%',
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
