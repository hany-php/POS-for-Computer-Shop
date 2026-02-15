
<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole(['admin']);
$pageTitle = 'التقارير المتقدمة';
$user = Auth::user();

// Date range with session persistence
[$from, $to] = resolveDateRange('admin_advanced_reports', date('Y-m-01'), date('Y-m-d'));

// 1. Technician Performance
$techPerformance = $db->fetchAll(
    "SELECT u.full_name as tech_name, 
            COUNT(mt.id) as total_tickets,
            SUM(CASE WHEN mt.status = 'delivered' THEN 1 ELSE 0 END) as completed_tickets,
            SUM(mt.actual_cost) as total_revenue
     FROM users u
     LEFT JOIN maintenance_tickets mt ON u.id = mt.technician_id
     WHERE u.role = 'technician' AND (mt.created_at BETWEEN ? AND ? OR mt.id IS NULL)
     GROUP BY u.id",
    [$from . ' 00:00:00', $to . ' 23:59:59']
);

// 2. Inventory Valuation
$inventoryValuation = $db->fetchOne(
    "SELECT SUM(quantity * cost_price) as total_cost,
            SUM(quantity * price) as total_potential_revenue
     FROM products
     WHERE is_active = 1"
);

// 3. Used Devices Summary
$usedDevicesSummary = $db->fetchOne(
    "SELECT COUNT(*) as total_purchases,
            SUM(purchase_price) as total_spent
     FROM used_device_purchases
     WHERE created_at BETWEEN ? AND ?",
    [$from . ' 00:00:00', $to . ' 23:59:59']
);

$inventoryTotalCost = floatval($inventoryValuation['total_cost'] ?? 0);
$inventoryTotalPotential = floatval($inventoryValuation['total_potential_revenue'] ?? 0);
$inventoryPotentialProfit = $inventoryTotalPotential - $inventoryTotalCost;
$usedDevicesTotalPurchases = intval($usedDevicesSummary['total_purchases'] ?? 0);
$usedDevicesTotalSpent = floatval($usedDevicesSummary['total_spent'] ?? 0);

// Maintenance turnaround KPI
$maintenanceKpi = $db->fetchOne(
    "SELECT
        COUNT(*) AS delivered_count,
        AVG((julianday(updated_at) - julianday(created_at))) AS avg_days,
        COALESCE(SUM(actual_cost - discount), 0) AS net_maintenance_revenue
     FROM maintenance_tickets
     WHERE status = 'delivered' AND date(updated_at) BETWEEN ? AND ?",
    [$from, $to]
);
$deliveredCount = intval($maintenanceKpi['delivered_count'] ?? 0);
$avgDays = floatval($maintenanceKpi['avg_days'] ?? 0);
$maintenanceNetRevenue = floatval($maintenanceKpi['net_maintenance_revenue'] ?? 0);

include __DIR__ . '/../includes/header.php';
?>

<div class="flex h-screen overflow-hidden">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-background-light">
        <header class="bg-white border-b border-slate-200 px-6 py-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">التقارير المتقدمة</h1>
                    <p class="text-sm text-slate-500 mt-1">إحصائيات متعمقة وتحليل المخزون</p>
                </div>
                <form method="GET" class="flex items-center gap-3">
                    <input type="date" name="from" value="<?= $from ?>" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-num focus:outline-none focus:border-primary">
                    <span class="text-slate-400">←</span>
                    <input type="date" name="to" value="<?= $to ?>" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-num focus:outline-none focus:border-primary">
                    <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors">عرض</button>
                </form>
            </div>
        </header>

        <div class="p-6 space-y-6">
            <!-- KPI Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="p-3 bg-indigo-50 text-indigo-600 rounded-lg">
                            <span class="material-icons-outlined">schedule</span>
                        </div>
                        <h3 class="font-bold text-slate-800 text-lg">سرعة إنجاز الصيانة</h3>
                    </div>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500">التذاكر المسلمة</span>
                            <span class="text-xl font-bold font-num"><?= $deliveredCount ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500">متوسط مدة الإنجاز</span>
                            <span class="text-xl font-bold font-num"><?= number_format($avgDays, 1) ?> يوم</span>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="p-3 bg-blue-50 text-blue-600 rounded-lg">
                            <span class="material-icons-outlined">inventory_2</span>
                        </div>
                        <h3 class="font-bold text-slate-800 text-lg">تقييم المخزون</h3>
                    </div>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500">إجمالي التكلفة بالمخزن</span>
                            <span class="text-xl font-bold font-num"><?= number_format($inventoryTotalCost, 2) ?> <?= CURRENCY ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500">القيمة البيعية المتوقعة</span>
                            <span class="text-xl font-bold font-num"><?= number_format($inventoryTotalPotential, 2) ?> <?= CURRENCY ?></span>
                        </div>
                        <div class="pt-4 border-t border-dashed border-slate-100 flex justify-between items-center">
                            <span class="text-emerald-600 font-bold">إجمالي الربح المحتمل</span>
                            <span class="text-2xl font-bold text-emerald-600 font-num"><?= number_format($inventoryPotentialProfit, 2) ?> <?= CURRENCY ?></span>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="p-3 bg-amber-50 text-amber-600 rounded-lg">
                            <span class="material-icons-outlined">phonelink_setup</span>
                        </div>
                        <h3 class="font-bold text-slate-800 text-lg">شراء المستعمل</h3>
                    </div>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500">عدد الأجهزة المشتراة</span>
                            <span class="text-xl font-bold font-num"><?= $usedDevicesTotalPurchases ?> جهاز</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500">إجمالي المبالغ المدفوعة</span>
                            <span class="text-xl font-bold font-num"><?= number_format($usedDevicesTotalSpent, 2) ?> <?= CURRENCY ?></span>
                        </div>
                        <div class="pt-4 border-t border-dashed border-slate-100 flex items-center justify-center">
                            <p class="text-xs text-slate-400">صافي إيراد الصيانة للفترة: <span class="font-num font-bold text-slate-700"><?= number_format($maintenanceNetRevenue, 2) ?> <?= CURRENCY ?></span></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Technician Performance Table -->
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
                <div class="p-4 border-b border-slate-100">
                    <h3 class="font-bold text-slate-900 flex items-center gap-2">
                        <span class="material-icons-outlined text-primary">engineering</span>
                        أداء الفنيين
                    </h3>
                </div>
                <div class="overflow-auto">
                    <table class="w-full text-right text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="p-4 font-semibold text-slate-600">اسم الفني</th>
                                <th class="p-4 font-semibold text-slate-600 text-center">إجمالي التذاكر</th>
                                <th class="p-4 font-semibold text-slate-600 text-center">المنجزة</th>
                                <th class="p-4 font-semibold text-slate-600 text-center">نسبة الإنجاز</th>
                                <th class="p-4 font-semibold text-slate-600">الإيرادات المحققة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($techPerformance as $tp): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="p-4 font-medium"><?= sanitize($tp['tech_name']) ?></td>
                                <td class="p-4 font-num text-center"><?= $tp['total_tickets'] ?></td>
                                <td class="p-4 font-num text-center text-emerald-600"><?= $tp['completed_tickets'] ?></td>
                                <td class="p-4 text-center">
                                    <div class="w-full bg-slate-100 rounded-full h-2 max-w-[100px] mx-auto overflow-hidden">
                                        <?php $perc = ($tp['total_tickets'] > 0) ? ($tp['completed_tickets'] / $tp['total_tickets']) * 100 : 0; ?>
                                        <div class="bg-emerald-500 h-full rounded-full" style="width: <?= $perc ?>%"></div>
                                    </div>
                                    <span class="text-[10px] text-slate-400 font-num"><?= round($perc, 1) ?>%</span>
                                </td>
                                <td class="p-4 font-num font-bold text-slate-900"><?= number_format(floatval($tp['total_revenue'] ?? 0), 2) ?></td>
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
