<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole(['admin']);
$pageTitle = 'التقارير';
$user = Auth::user();

// Date range with session persistence
[$from, $to] = resolveDateRange('admin_reports', date('Y-m-01'), date('Y-m-d'));

// Revenue, Cost, Profit
$salesData = $db->fetchOne(
    "SELECT COUNT(*) as count, COALESCE(SUM(total),0) as revenue FROM orders WHERE status='completed' AND date(created_at) BETWEEN ? AND ?",
    [$from, $to]
);
$costData = $db->fetchOne(
    "SELECT COALESCE(SUM(oi.quantity * p.cost_price),0) as total_cost 
     FROM order_items oi 
     JOIN orders o ON oi.order_id = o.id 
     LEFT JOIN products p ON oi.product_id = p.id 
     WHERE o.status='completed' AND date(o.created_at) BETWEEN ? AND ?",
    [$from, $to]
);
$revenue = $salesData['revenue'];
$cost = $costData['total_cost'];
$profit = $revenue - $cost;
$ordersCount = $salesData['count'];
$avgOrderValue = $ordersCount > 0 ? ($revenue / $ordersCount) : 0;

// Refunded orders
$refundedData = $db->fetchOne(
    "SELECT COUNT(*) as count, COALESCE(SUM(total),0) as total FROM orders WHERE status='refunded' AND date(created_at) BETWEEN ? AND ?",
    [$from, $to]
);
$refundRate = $ordersCount > 0 ? (intval($refundedData['count'] ?? 0) / $ordersCount) * 100 : 0;

// Top selling products
$topProducts = $db->fetchAll(
    "SELECT oi.product_name, SUM(oi.quantity) as total_qty, SUM(oi.total_price) as total_sales
     FROM order_items oi
     JOIN orders o ON oi.order_id = o.id
     WHERE o.status='completed' AND date(o.created_at) BETWEEN ? AND ?
     GROUP BY oi.product_name
     ORDER BY total_qty DESC LIMIT 10",
    [$from, $to]
);

// Sales by category
$categoryData = $db->fetchAll(
    "SELECT COALESCE(c.name,'بدون فئة') as cat_name, SUM(oi.total_price) as total_sales, SUM(oi.quantity) as total_qty
     FROM order_items oi
     JOIN orders o ON oi.order_id = o.id
     LEFT JOIN products p ON oi.product_id = p.id
     LEFT JOIN categories c ON p.category_id = c.id
     WHERE o.status='completed' AND date(o.created_at) BETWEEN ? AND ?
     GROUP BY c.name
     ORDER BY total_sales DESC",
    [$from, $to]
);

// Daily trend for chart
$dailyTrend = $db->fetchAll(
    "SELECT date(created_at) as day, COALESCE(SUM(total),0) as total, COUNT(*) as count
     FROM orders WHERE status='completed' AND date(created_at) BETWEEN ? AND ?
     GROUP BY date(created_at) ORDER BY day ASC",
    [$from, $to]
);

// Low stock products
$lowStock = $db->fetchAll(
    "SELECT name, quantity FROM products WHERE is_active=1 AND quantity <= 5 ORDER BY quantity ASC LIMIT 10"
);
$deliveredMaintenance = $db->fetchOne(
    "SELECT COUNT(*) AS cnt, AVG((julianday(updated_at) - julianday(created_at))) AS avg_days
     FROM maintenance_tickets
     WHERE status = 'delivered' AND date(updated_at) BETWEEN ? AND ?",
    [$from, $to]
);
$deliveredMaintenanceCount = intval($deliveredMaintenance['cnt'] ?? 0);
$avgMaintenanceDays = floatval($deliveredMaintenance['avg_days'] ?? 0);

include __DIR__ . '/../includes/header.php';
?>

<div class="flex h-screen overflow-hidden">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-background-light">
        <header class="bg-white border-b border-slate-200 px-6 py-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">التقارير</h1>
                    <p class="text-sm text-slate-500 mt-1">تحليل تفصيلي للمبيعات والأرباح</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="advanced-reports.php" class="bg-white border border-slate-200 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors flex items-center gap-2">
                        <span class="material-icons-outlined text-base">analytics</span>
                        التقارير المتقدمة
                    </a>
                    <form method="GET" class="flex items-center gap-3">
                        <input type="date" name="from" value="<?= $from ?>" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-num focus:outline-none focus:border-primary">
                        <span class="text-slate-400">←</span>
                        <input type="date" name="to" value="<?= $to ?>" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-num focus:outline-none focus:border-primary">
                        <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors">عرض</button>
                    </form>
                </div>
            </div>
        </header>

        <div class="p-6 space-y-6">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
                <div class="bg-white rounded-xl border border-slate-200 p-5">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-medium text-slate-500">الإيرادات</span>
                        <span class="material-icons-outlined text-green-500 text-xl">trending_up</span>
                    </div>
                    <p class="text-2xl font-bold text-slate-900 font-num"><?= number_format($revenue, 2) ?></p>
                    <p class="text-xs text-slate-400 mt-1"><?= $ordersCount ?> طلب</p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-5">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-medium text-slate-500">التكلفة</span>
                        <span class="material-icons-outlined text-red-500 text-xl">trending_down</span>
                    </div>
                    <p class="text-2xl font-bold text-slate-900 font-num"><?= number_format($cost, 2) ?></p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-5">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-medium text-slate-500">صافي الربح</span>
                        <span class="material-icons-outlined text-<?= $profit >= 0 ? 'emerald' : 'red' ?>-500 text-xl"><?= $profit >= 0 ? 'savings' : 'money_off' ?></span>
                    </div>
                    <p class="text-2xl font-bold <?= $profit >= 0 ? 'text-emerald-600' : 'text-red-600' ?> font-num"><?= number_format($profit, 2) ?></p>
                    <?php if ($revenue > 0): ?>
                    <p class="text-xs text-slate-400 mt-1">هامش الربح: <?= number_format(($profit / $revenue) * 100, 1) ?>%</p>
                    <?php endif; ?>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-5">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-medium text-slate-500">المرتجعات</span>
                        <span class="material-icons-outlined text-orange-500 text-xl">undo</span>
                    </div>
                    <p class="text-2xl font-bold text-slate-900 font-num"><?= number_format($refundedData['total'], 2) ?></p>
                    <p class="text-xs text-slate-400 mt-1"><?= $refundedData['count'] ?> مرتجع</p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-5">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-medium text-slate-500">متوسط الفاتورة</span>
                        <span class="material-icons-outlined text-sky-500 text-xl">receipt_long</span>
                    </div>
                    <p class="text-2xl font-bold text-slate-900 font-num"><?= number_format($avgOrderValue, 2) ?></p>
                    <p class="text-xs text-slate-400 mt-1">نسبة مرتجع <?= number_format($refundRate, 1) ?>%</p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-5">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-medium text-slate-500">صيانة منجزة</span>
                        <span class="material-icons-outlined text-indigo-500 text-xl">engineering</span>
                    </div>
                    <p class="text-2xl font-bold text-slate-900 font-num"><?= $deliveredMaintenanceCount ?></p>
                    <p class="text-xs text-slate-400 mt-1">متوسط الإنجاز <?= number_format($avgMaintenanceDays, 1) ?> يوم</p>
                </div>
            </div>

            <!-- Daily Sales Chart -->
            <div class="bg-white rounded-xl border border-slate-200 p-6">
                <h3 class="font-bold text-slate-900 mb-4">المبيعات اليومية</h3>
                <div style="height: 250px;">
                    <canvas id="dailyChart"></canvas>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Top Products -->
                <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                    <div class="p-4 border-b border-slate-100">
                        <h3 class="font-bold text-slate-900">أكثر المنتجات مبيعاً</h3>
                    </div>
                    <div class="overflow-auto max-h-80">
                        <table class="w-full text-right text-sm">
                            <thead class="bg-slate-50 sticky top-0">
                                <tr>
                                    <th class="p-3 text-xs font-semibold text-slate-500">#</th>
                                    <th class="p-3 text-xs font-semibold text-slate-500">المنتج</th>
                                    <th class="p-3 text-xs font-semibold text-slate-500">الكمية</th>
                                    <th class="p-3 text-xs font-semibold text-slate-500">الإجمالي</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($topProducts as $i => $tp): ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="p-3 font-num text-slate-400"><?= $i + 1 ?></td>
                                    <td class="p-3 font-medium"><?= sanitize($tp['product_name']) ?></td>
                                    <td class="p-3 font-num"><?= $tp['total_qty'] ?></td>
                                    <td class="p-3 font-num"><?= number_format($tp['total_sales'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($topProducts)): ?>
                                <tr><td colspan="4" class="p-6 text-center text-slate-400">لا توجد بيانات</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Sales by Category -->
                <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                    <div class="p-4 border-b border-slate-100">
                        <h3 class="font-bold text-slate-900">المبيعات حسب الفئة</h3>
                    </div>
                    <div class="overflow-auto max-h-80">
                        <table class="w-full text-right text-sm">
                            <thead class="bg-slate-50 sticky top-0">
                                <tr>
                                    <th class="p-3 text-xs font-semibold text-slate-500">الفئة</th>
                                    <th class="p-3 text-xs font-semibold text-slate-500">الكمية</th>
                                    <th class="p-3 text-xs font-semibold text-slate-500">الإجمالي</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($categoryData as $cd): ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="p-3 font-medium"><?= sanitize($cd['cat_name']) ?></td>
                                    <td class="p-3 font-num"><?= $cd['total_qty'] ?></td>
                                    <td class="p-3 font-num"><?= number_format($cd['total_sales'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($categoryData)): ?>
                                <tr><td colspan="3" class="p-6 text-center text-slate-400">لا توجد بيانات</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Low Stock Alert -->
            <?php if (!empty($lowStock)): ?>
            <div class="bg-white rounded-xl border border-orange-200 overflow-hidden">
                <div class="p-4 border-b border-orange-100 flex items-center gap-2">
                    <span class="material-icons-outlined text-orange-500">warning</span>
                    <h3 class="font-bold text-orange-700">تنبيه: منتجات قاربت على النفاذ</h3>
                </div>
                <div class="p-4 flex flex-wrap gap-2">
                    <?php foreach ($lowStock as $ls): ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-50 text-orange-700 rounded-full text-sm font-medium border border-orange-100">
                        <?= sanitize($ls['name']) ?>
                        <span class="bg-orange-200 text-orange-800 text-xs font-bold px-1.5 py-0.5 rounded-full font-num"><?= $ls['quantity'] ?></span>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const dailyData = <?= json_encode($dailyTrend) ?>;
new Chart(document.getElementById('dailyChart'), {
    type: 'line',
    data: {
        labels: dailyData.map(d => d.day),
        datasets: [{
            label: 'المبيعات',
            data: dailyData.map(d => d.total),
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99, 102, 241, 0.1)',
            fill: true,
            tension: 0.4,
            pointRadius: 4,
            pointBackgroundColor: '#6366f1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { font: { family: 'monospace' } } },
            x: { ticks: { font: { family: 'monospace', size: 10 } } }
        }
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
