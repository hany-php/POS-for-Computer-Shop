<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole(['admin']);
$pageTitle = 'سجل المبيعات';
$user = Auth::user();

// Handle return
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCsrfTokenOrFail();
    if ($_POST['action'] === 'return') {
        $orderId = intval($_POST['order_id']);
        $order = $db->fetchOne("SELECT id, status FROM orders WHERE id = ?", [$orderId]);
        if (!$order) {
            setFlash('error', 'الطلب غير موجود');
            header('Location: sales.php');
            exit;
        }
        if ($order['status'] !== 'completed') {
            setFlash('error', 'لا يمكن إرجاع فاتورة غير مكتملة أو مُرجعة مسبقاً');
            header('Location: sales.php');
            exit;
        }
        
        // Ensure column exists (Migration check for safety)
        try {
            $cols = $db->fetchAll("PRAGMA table_info(orders)");
            $hasCol = false;
            foreach ($cols as $col) {
                if ($col['name'] === 'return_invoice_number') { $hasCol = true; break; }
            }
            if (!$hasCol) {
                $db->query("ALTER TABLE orders ADD COLUMN return_invoice_number TEXT DEFAULT NULL");
            }
        } catch (Exception $e) { }

        $returnNumber = 'RET-' . date('Ymd') . '-' . str_pad($orderId, 4, '0', STR_PAD_LEFT);

        $items = $db->fetchAll("SELECT product_id, quantity FROM order_items WHERE order_id = ?", [$orderId]);
        foreach ($items as $item) {
            $db->query("UPDATE products SET quantity = quantity + ? WHERE id = ?", [$item['quantity'], $item['product_id']]);
        }
        $updated = $db->query(
            "UPDATE orders SET status = 'refunded', return_invoice_number = ? WHERE id = ? AND status = 'completed'",
            [$returnNumber, $orderId]
        )->rowCount();
        if ($updated !== 1) {
            setFlash('error', 'تعذر تنفيذ المرتجع، قد تكون الفاتورة تم إرجاعها بالفعل');
            header('Location: sales.php');
            exit;
        }
        setFlash('success', 'تم عمل فاتورة مرتجع بنجاح. رقم المرتجع: ' . $returnNumber);
        header('Location: sales.php');
        exit;
    }
}

// Fetch orders with optional date filter
$dateFilter = $_GET['date'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;
$sql = "SELECT
            o.*,
            u.full_name as cashier_name,
            (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count,
            CASE WHEN o.status='completed' AND o.total > o.payment_received THEN (o.total - o.payment_received) ELSE 0 END AS due_amount
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE 1=1";
$params = [];

if ($dateFilter) {
    $sql .= " AND DATE(o.created_at) = ?";
    $params[] = $dateFilter;
}
if ($statusFilter) {
    $sql .= " AND o.status = ?";
    $params[] = $statusFilter;
}

$countSql = "SELECT COUNT(*) as cnt FROM orders o WHERE 1=1";
$countParams = [];
if ($dateFilter) {
    $countSql .= " AND DATE(o.created_at) = ?";
    $countParams[] = $dateFilter;
}
if ($statusFilter) {
    $countSql .= " AND o.status = ?";
    $countParams[] = $statusFilter;
}
$totalRows = intval(($db->fetchOne($countSql, $countParams)['cnt'] ?? 0));
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$sql .= " ORDER BY o.created_at DESC LIMIT $perPage OFFSET $offset";

$orders = $db->fetchAll($sql, $params);

$baseQuery = $_GET;
unset($baseQuery['page']);
function salesPageUrl($targetPage, $baseQuery) {
    $q = $baseQuery;
    $q['page'] = $targetPage;
    return 'sales.php?' . http_build_query($q);
}

// Summary stats
$sqlStats = "SELECT COALESCE(SUM(total),0) as total, COUNT(*) as cnt FROM orders WHERE status = 'completed'";
$statsParams = [];
if ($dateFilter) {
    $sqlStats .= " AND DATE(created_at) = ?";
    $statsParams[] = $dateFilter;
}
$stats = $db->fetchOne($sqlStats, $statsParams);
$sqlDebtStats = "SELECT
                    COALESCE(SUM(CASE WHEN total > payment_received THEN (total - payment_received) ELSE 0 END),0) AS due_total,
                    COALESCE(SUM(CASE WHEN total > payment_received THEN (CASE WHEN payment_received > total THEN total ELSE payment_received END) ELSE total END),0) AS collected_total,
                    COUNT(CASE WHEN total > payment_received THEN 1 END) AS due_cnt
                 FROM orders
                 WHERE status = 'completed'";
$debtStatsParams = [];
if ($dateFilter) {
    $sqlDebtStats .= " AND DATE(created_at) = ?";
    $debtStatsParams[] = $dateFilter;
}
$debtStats = $db->fetchOne($sqlDebtStats, $debtStatsParams);

include __DIR__ . '/../includes/header.php';
?>

<div class="flex h-screen overflow-hidden">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-background-light">
        <header class="bg-white border-b border-slate-200 px-6 py-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">سجل المبيعات</h1>
                    <p class="text-sm text-slate-500 mt-1">تفاصيل الطلبات والفواتير</p>
                </div>
            </div>
        </header>

        <div class="p-6 space-y-6">
            <!-- Filters -->
            <div class="flex flex-wrap gap-4 items-center bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <form method="GET" class="flex flex-wrap gap-3 items-center">
                    <div class="relative">
                        <span class="material-icons-outlined absolute right-3 top-2 text-slate-400 text-sm">calendar_today</span>
                        <input type="date" name="date" value="<?= sanitize($dateFilter) ?>" class="bg-slate-50 border border-slate-200 rounded-lg pr-10 pl-4 py-2 text-sm font-num focus:outline-none focus:border-primary">
                    </div>
                    <select name="status" class="bg-slate-50 border border-slate-200 rounded-lg px-4 py-2 text-sm focus:outline-none focus:border-primary">
                        <option value="">كل الحالات</option>
                        <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>مكتمل</option>
                        <option value="refunded" <?= $statusFilter === 'refunded' ? 'selected' : '' ?>>مرتجع</option>
                    </select>
                    <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors">تصفية</button>
                    <a href="sales.php" class="text-slate-500 text-sm hover:text-primary transition-colors">إعادة تعيين</a>
                </form>
                <div class="mr-auto flex gap-4">
                    <div class="text-left">
                        <p class="text-xs text-slate-500">إجمالي المبيعات</p>
                        <p class="text-lg font-bold font-num text-primary"><?= number_format($stats['total'], 2) ?> <span class="text-xs text-slate-400"><?= CURRENCY_EN ?></span></p>
                    </div>
                    <div class="text-left border-r border-slate-200 pr-4">
                        <p class="text-xs text-slate-500">عدد الطلبات</p>
                        <p class="text-lg font-bold font-num"><?= $stats['cnt'] ?></p>
                    </div>
                    <div class="text-left border-r border-slate-200 pr-4">
                        <p class="text-xs text-slate-500">إجمالي الدين</p>
                        <p class="text-lg font-bold font-num text-amber-700"><?= number_format(floatval($debtStats['due_total'] ?? 0), 2) ?> <span class="text-xs text-slate-400"><?= CURRENCY_EN ?></span></p>
                        <p class="text-[11px] text-slate-400 font-num"><?= intval($debtStats['due_cnt'] ?? 0) ?> فواتير دين</p>
                    </div>
                    <div class="text-left border-r border-slate-200 pr-4">
                        <p class="text-xs text-slate-500">المحصل</p>
                        <p class="text-lg font-bold font-num text-emerald-700"><?= number_format(floatval($debtStats['collected_total'] ?? 0), 2) ?> <span class="text-xs text-slate-400"><?= CURRENCY_EN ?></span></p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between text-sm">
                <p class="text-slate-500">إجمالي الفواتير: <span class="font-num font-bold"><?= $totalRows ?></span></p>
                <div class="flex items-center gap-2">
                    <?php if ($page > 1): ?>
                    <a href="<?= sanitize(salesPageUrl($page - 1, $baseQuery)) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700">السابق</a>
                    <?php else: ?>
                    <span class="px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-100 text-slate-400">السابق</span>
                    <?php endif; ?>
                    <span class="font-num text-slate-600">صفحة <?= $page ?> / <?= $totalPages ?></span>
                    <?php if ($page < $totalPages): ?>
                    <a href="<?= sanitize(salesPageUrl($page + 1, $baseQuery)) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700">التالي</a>
                    <?php else: ?>
                    <span class="px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-100 text-slate-400">التالي</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-auto">
                    <table class="w-full text-right">
                        <thead class="bg-slate-50 sticky top-0">
                            <tr>
                                <th class="p-4 text-xs font-semibold text-slate-500">رقم الفاتورة</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">التاريخ</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">الكاشير</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">العناصر</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">الدفع</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">الدين</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">المجموع</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">الضريبة</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">الإجمالي</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">الحالة</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($orders)): ?>
                            <tr><td colspan="11" class="p-8 text-center text-slate-400">لا توجد طلبات</td></tr>
                            <?php endif; ?>
                            <?php foreach ($orders as $o): ?>
                            <?php $isReturned = $o['status'] === 'refunded'; ?>
                            <tr class="hover:bg-slate-50 transition-colors group <?= $isReturned ? 'line-through decoration-slate-400 decoration-2 opacity-75' : '' ?>">
                                <td class="p-4 text-sm font-medium">
                                    <span class="font-num text-primary block"><?= $o['order_number'] ?></span>
                                    <?php if ($isReturned): ?>
                                    <span class="text-xs text-red-500 font-bold block mt-1 no-underline font-num" style="text-decoration: none !important; display: inline-block;">مرتجع: #<?= $o['return_invoice_number'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 text-sm text-slate-500 font-num whitespace-nowrap"><?= formatDateTimeAr($o['created_at']) ?></td>
                                <td class="p-4 text-sm"><?= sanitize($o['cashier_name'] ?? '—') ?></td>
                                <td class="p-4 text-sm font-num text-center"><?= $o['item_count'] ?></td>
                                <td class="p-4 text-sm">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-600">
                                        <span class="material-icons-outlined text-xs"><?= $o['payment_method'] === 'cash' ? 'payments' : ($o['payment_method'] === 'card' ? 'credit_card' : 'qr_code_scanner') ?></span>
                                        <?= getPaymentMethodAr($o['payment_method']) ?>
                                    </span>
                                </td>
                                <td class="p-4 text-sm font-num">
                                    <?php $dueAmount = floatval($o['due_amount'] ?? 0); ?>
                                    <?php if ($o['status'] === 'completed' && $dueAmount > 0): ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">
                                        <?= number_format($dueAmount, 2) ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-emerald-600 text-xs font-medium">مسدد</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 text-sm font-num"><?= number_format($o['subtotal'], 2) ?></td>
                                <td class="p-4 text-sm font-num text-slate-500"><?= number_format($o['tax_amount'], 2) ?></td>
                                <td class="p-4 text-sm font-num font-bold text-slate-900"><?= number_format($o['total'], 2) ?></td>
                                <td class="p-4">
                                    <?php if ($o['status'] === 'completed'): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-100"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>مكتمل</span>
                                    <?php elseif ($o['status'] === 'refunded'): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-red-50 text-red-700 border border-red-100" style="text-decoration: none !important;">مرتجع</span>
                                    <?php else: ?>
                                    <span><?= $o['status'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4">
                                    <?php if ($o['status'] === 'completed'): ?>
                                    <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button onclick="viewOrder(<?= $o['id'] ?>)" class="p-1.5 hover:bg-slate-100 rounded-lg text-slate-500 hover:text-primary transition-colors" title="تفاصيل">
                                            <span class="material-icons-outlined text-[18px]">visibility</span>
                                        </button>
                                        <form method="POST" onsubmit="return confirm('هل أنت متأكد من عمل مرتجع لهذا الطلب؟ سيتم استرجاع المخزون.')">
                                            <input type="hidden" name="action" value="return">
                                            <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                            <?php csrfInput(); ?>
                                            <button type="submit" class="p-1.5 hover:bg-red-50 rounded-lg text-slate-500 hover:text-red-500 transition-colors" title="مرتجع">
                                                <span class="material-icons-outlined text-[18px]">assignment_return</span>
                                            </button>
                                        </form>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="flex items-center justify-between text-sm">
                <p class="text-slate-500">إجمالي الفواتير: <span class="font-num font-bold"><?= $totalRows ?></span></p>
                <div class="flex items-center gap-2">
                    <?php if ($page > 1): ?>
                    <a href="<?= sanitize(salesPageUrl($page - 1, $baseQuery)) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700">السابق</a>
                    <?php else: ?>
                    <span class="px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-100 text-slate-400">السابق</span>
                    <?php endif; ?>
                    <span class="font-num text-slate-600">صفحة <?= $page ?> / <?= $totalPages ?></span>
                    <?php if ($page < $totalPages): ?>
                    <a href="<?= sanitize(salesPageUrl($page + 1, $baseQuery)) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700">التالي</a>
                    <?php else: ?>
                    <span class="px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-100 text-slate-400">التالي</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Order Details Modal -->
<div id="order-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden border border-slate-100">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h2 class="text-lg font-bold">تفاصيل الطلب</h2>
            <button onclick="document.getElementById('order-modal').classList.add('hidden')" class="p-2 rounded-full hover:bg-slate-100 text-slate-500"><span class="material-icons-outlined">close</span></button>
        </div>
        <div id="order-details" class="p-6">
            <p class="text-center text-slate-400">جاري التحميل...</p>
        </div>
    </div>
</div>

<script>
const SHOW_HIJRI_DATES = <?= isHijriDateEnabled() ? 'true' : 'false' ?>;

function formatDateTimeArHijri(dateInput) {
    const d = new Date(dateInput);
    if (isNaN(d.getTime())) return dateInput;
    const g = d.toLocaleString('ar-EG', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    if (!SHOW_HIJRI_DATES) {
        return g;
    }
    const h = d.toLocaleDateString('ar-SA-u-ca-islamic', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    }) + ' هـ';
    return `${g} | ${h}`;
}

async function viewOrder(id) {
    document.getElementById('order-modal').classList.remove('hidden');
    try {
        const res = await fetch('../api/order-details.php?id=' + id);
        const data = await res.json();
        if (data.success) {
            const o = data.order;
            const items = data.items;
            let html = `
                <div class="space-y-4">
                    <div class="flex justify-between text-sm"><span class="text-slate-500">رقم الفاتورة</span><span class="font-num font-bold text-primary">${o.order_number}</span></div>
                    <div class="flex justify-between text-sm"><span class="text-slate-500">التاريخ</span><span>${formatDateTimeArHijri(o.created_at)}</span></div>
                    <div class="flex justify-between text-sm"><span class="text-slate-500">طريقة الدفع</span><span>${o.payment_method}</span></div>
                    <div class="border-t border-dashed border-slate-200 pt-4">
                        <table class="w-full text-sm">
                            <thead><tr class="text-slate-500"><td class="pb-2">الصنف</td><td class="pb-2 text-center">الكمية</td><td class="pb-2 text-left">السعر</td></tr></thead>
                            <tbody>${items.map(i => `<tr class="border-t border-slate-100"><td class="py-2">${i.product_name}</td><td class="py-2 text-center font-num">${i.quantity}</td><td class="py-2 text-left font-num">${parseFloat(i.total_price).toFixed(2)}</td></tr>`).join('')}</tbody>
                        </table>
                    </div>
                    <div class="border-t border-slate-200 pt-3 space-y-1">
                        <div class="flex justify-between text-sm"><span class="text-slate-500">المجموع</span><span class="font-num">${parseFloat(o.subtotal).toFixed(2)}</span></div>
                        <div class="flex justify-between text-sm"><span class="text-slate-500">الضريبة</span><span class="font-num">${parseFloat(o.tax_amount).toFixed(2)}</span></div>
                        <div class="flex justify-between text-lg font-bold border-t border-slate-200 pt-2 mt-2"><span>الإجمالي</span><span class="font-num text-primary">${parseFloat(o.total).toFixed(2)} <?= CURRENCY ?></span></div>
                    </div>
                </div>`;
            document.getElementById('order-details').innerHTML = html;
        }
    } catch (e) {
        document.getElementById('order-details').innerHTML = '<p class="text-red-500 text-center">خطأ في تحميل التفاصيل</p>';
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
