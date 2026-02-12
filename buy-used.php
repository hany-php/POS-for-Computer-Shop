<?php
require_once __DIR__ . '/includes/bootstrap.php';
Auth::requireLogin();
$pageTitle = 'شراء أجهزة مستعملة';
$user = Auth::user();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'purchase') {
        $transactionNumber = $db->generateTransactionNumber();
        $purchasePrice = floatval($_POST['purchase_price']);
        $addToInventory = isset($_POST['add_to_inventory']) ? 1 : 0;
        $productId = null;
        
        // Add to inventory if checked
        if ($addToInventory) {
            $productName = $_POST['device_brand'] . ' ' . $_POST['device_model'] . ' (مستعمل)';
            $productId = $db->insert(
                "INSERT INTO products (name, description, category_id, price, cost_price, quantity, serial_number, is_used, image_url) VALUES (?, ?, ?, ?, ?, 1, ?, 1, '')",
                [$productName, 'حالة: ' . getConditionAr($_POST['device_condition']), $_POST['category_id'] ?? 2, $purchasePrice * 1.3, $purchasePrice, $_POST['serial_number'] ?? '']
            );
        }
        
        $db->insert(
            "INSERT INTO used_device_purchases (transaction_number, seller_name, seller_phone, seller_id_number, device_type, device_brand, device_model, serial_number, device_condition, condition_notes, purchase_price, added_to_inventory, product_id, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$transactionNumber, $_POST['seller_name'], $_POST['seller_phone'] ?? '', $_POST['seller_id'] ?? '', $_POST['device_type'], $_POST['device_brand'] ?? '', $_POST['device_model'] ?? '', $_POST['serial_number'] ?? '', $_POST['device_condition'], $_POST['condition_notes'] ?? '', $purchasePrice, $addToInventory, $productId, $_SESSION['user_id']]
        );
        
        setFlash('success', 'تم تسجيل عملية الشراء بنجاح: ' . $transactionNumber);
        header('Location: buy-used.php');
        exit;
    }
}

$purchases = $db->fetchAll("SELECT udp.*, u.full_name as employee_name FROM used_device_purchases udp LEFT JOIN users u ON udp.user_id = u.id ORDER BY udp.created_at DESC LIMIT 50");
$categories = $db->fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order");

include __DIR__ . '/includes/header.php';
?>

<div class="min-h-screen flex flex-col">
    <!-- Header -->
    <header class="bg-white border-b border-slate-200 h-16 flex items-center px-6 shadow-sm z-10 sticky top-0">
        <div class="flex items-center gap-4 w-full">
            <div class="flex items-center gap-3">
                <div class="bg-primary/10 p-2 rounded-lg text-primary">
                    <span class="material-icons-outlined">phonelink_setup</span>
                </div>
                <div>
                    <h1 class="font-bold text-lg leading-tight">شراء أجهزة مستعملة</h1>
                    <p class="text-xs text-slate-500 font-num">Used Devices Purchase</p>
                </div>
            </div>
            <div class="flex-grow"></div>
            <div class="flex items-center gap-3">
                <div class="hidden md:flex items-center bg-slate-100 px-3 py-1.5 rounded-lg border border-slate-200">
                    <span class="material-icons-outlined text-slate-400 text-sm ml-2">store</span>
                    <span class="text-sm font-medium">الفرع الرئيسي</span>
                </div>
                <a href="pos.php" class="flex items-center gap-2 bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    <span class="material-icons-outlined text-base">point_of_sale</span>
                    <span>نقطة البيع</span>
                </a>
            </div>
        </div>
    </header>

    <main class="flex-grow p-4 md:p-6 lg:h-[calc(100vh-4rem)] overflow-hidden">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 h-full">
            <!-- RIGHT: Purchase Form -->
            <div class="lg:col-span-4 xl:col-span-3 h-full flex flex-col gap-4 overflow-y-auto pr-1 pb-20 lg:pb-0">
                <form method="POST">
                    <input type="hidden" name="action" value="purchase">
                    
                    <!-- Seller Info -->
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 mb-4">
                        <h2 class="text-base font-bold mb-4 flex items-center gap-2 text-primary">
                            <span class="material-icons-outlined text-sm">person</span>
                            بيانات البائع
                        </h2>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-500 mb-1">الاسم الكامل</label>
                                <input type="text" name="seller_name" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all" placeholder="مثال: أحمد محمد">
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1">رقم الهوية</label>
                                    <input type="text" name="seller_id" dir="ltr" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-num focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all text-left" placeholder="10xxxxxxxx">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1">رقم الجوال</label>
                                    <input type="tel" name="seller_phone" dir="ltr" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-num focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all text-left" placeholder="05xxxxxxxx">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Device Details -->
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 mb-4">
                        <h2 class="text-base font-bold mb-4 flex items-center gap-2 text-primary">
                            <span class="material-icons-outlined text-sm">smartphone</span>
                            تفاصيل الجهاز
                        </h2>
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1">التصنيف</label>
                                    <select name="device_type" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all">
                                        <option value="لابتوب">لابتوب</option>
                                        <option value="كمبيوتر مكتبي">كمبيوتر مكتبي</option>
                                        <option value="شاشة">شاشة</option>
                                        <option value="طابعة">طابعة</option>
                                        <option value="هاتف ذكي">هاتف ذكي</option>
                                        <option value="جهاز لوحي">جهاز لوحي</option>
                                        <option value="إكسسوارات">إكسسوارات</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1">الماركة</label>
                                    <input type="text" name="device_brand" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all" placeholder="Apple, Dell...">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500 mb-1">الموديل</label>
                                <input type="text" name="device_model" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all" placeholder="مثال: MacBook Air M1">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500 mb-1">الرقم التسلسلي (S/N)</label>
                                <input type="text" name="serial_number" dir="ltr" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-num focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all text-left" placeholder="Serial Number">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500 mb-1">الفئة في المخزون</label>
                                <select name="category_id" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all">
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= sanitize($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Condition & Price -->
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                        <h2 class="text-base font-bold mb-4 flex items-center gap-2 text-primary">
                            <span class="material-icons-outlined text-sm">verified</span>
                            الحالة والسعر
                        </h2>
                        <div class="mb-4">
                            <label class="block text-xs font-medium text-slate-500 mb-2">حالة الجهاز</label>
                            <div class="grid grid-cols-4 gap-2">
                                <label class="cursor-pointer">
                                    <input type="radio" name="device_condition" value="excellent" class="peer sr-only">
                                    <div class="text-center py-2 border border-slate-200 rounded-lg peer-checked:bg-green-50 peer-checked:border-green-500 peer-checked:text-green-700 transition-all">
                                        <span class="text-xs font-bold">ممتاز</span>
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="device_condition" value="very_good" class="peer sr-only" checked>
                                    <div class="text-center py-2 border border-slate-200 rounded-lg peer-checked:bg-primary/10 peer-checked:border-primary peer-checked:text-primary transition-all">
                                        <span class="text-xs font-bold">جيد جداً</span>
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="device_condition" value="acceptable" class="peer sr-only">
                                    <div class="text-center py-2 border border-slate-200 rounded-lg peer-checked:bg-yellow-50 peer-checked:border-yellow-500 peer-checked:text-yellow-700 transition-all">
                                        <span class="text-xs font-bold">مقبول</span>
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="device_condition" value="damaged" class="peer sr-only">
                                    <div class="text-center py-2 border border-slate-200 rounded-lg peer-checked:bg-red-50 peer-checked:border-red-500 peer-checked:text-red-700 transition-all">
                                        <span class="text-xs font-bold">تالف</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="block text-xs font-medium text-slate-500 mb-1">ملاحظات / عيوب</label>
                            <textarea name="condition_notes" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all resize-none"></textarea>
                        </div>
                        <div class="border-t border-dashed border-slate-200 pt-4">
                            <label class="flex items-center justify-between mb-4 cursor-pointer">
                                <span class="text-sm font-medium text-slate-700">إضافة للمخزون تلقائياً</span>
                                <div class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="add_to_inventory" checked class="sr-only peer">
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-primary/30 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary" dir="ltr"></div>
                                </div>
                            </label>
                            <div class="relative mb-4">
                                <label class="block text-xs font-medium text-slate-500 mb-1">سعر الشراء النهائي</label>
                                <div class="relative">
                                    <input type="number" name="purchase_price" required step="0.01" dir="ltr" class="w-full bg-white border-2 border-primary/30 focus:border-primary rounded-lg px-4 py-3 text-2xl font-bold font-num text-primary focus:outline-none transition-all pl-16 text-left" placeholder="0.00">
                                    <span class="absolute left-4 top-4 text-slate-400 font-bold font-num"><?= CURRENCY_EN ?></span>
                                </div>
                            </div>
                            <button type="submit" class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-3.5 px-4 rounded-xl shadow-lg shadow-primary/30 transition-all flex items-center justify-center gap-2 group">
                                <span>إتمام الشراء</span>
                                <span class="material-icons-outlined group-hover:-translate-x-1 transition-transform">arrow_back</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- LEFT: Recent Transactions -->
            <div class="lg:col-span-8 xl:col-span-9 h-full flex flex-col gap-4">
                <!-- Search -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div class="relative w-full sm:max-w-md">
                        <span class="material-icons-outlined absolute right-3 top-2.5 text-slate-400">search</span>
                        <input type="text" id="search-purchases" class="w-full bg-slate-50 border border-slate-200 rounded-lg pr-10 pl-4 py-2 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all" placeholder="بحث برقم العملية، اسم البائع، أو السيريال...">
                    </div>
                </div>

                <!-- Table -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 flex-grow overflow-hidden flex flex-col">
                    <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                        <h3 class="font-bold text-slate-800 flex items-center gap-2">
                            <span class="w-2 h-6 bg-primary rounded-full"></span>
                            العمليات الأخيرة
                        </h3>
                        <span class="bg-primary/10 text-primary text-xs font-num font-bold px-2 py-1 rounded-full"><?= count($purchases) ?> عملية</span>
                    </div>
                    <div class="overflow-auto flex-grow">
                        <table class="w-full text-right">
                            <thead class="bg-slate-50 sticky top-0 z-10">
                                <tr>
                                    <th class="p-4 text-xs font-semibold text-slate-500 whitespace-nowrap">رقم العملية</th>
                                    <th class="p-4 text-xs font-semibold text-slate-500 whitespace-nowrap">التاريخ</th>
                                    <th class="p-4 text-xs font-semibold text-slate-500 whitespace-nowrap">البائع</th>
                                    <th class="p-4 text-xs font-semibold text-slate-500 whitespace-nowrap">الجهاز</th>
                                    <th class="p-4 text-xs font-semibold text-slate-500 whitespace-nowrap">الرقم التسلسلي</th>
                                    <th class="p-4 text-xs font-semibold text-slate-500 whitespace-nowrap">الحالة</th>
                                    <th class="p-4 text-xs font-semibold text-slate-500 whitespace-nowrap">السعر</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (empty($purchases)): ?>
                                <tr><td colspan="7" class="p-8 text-center text-slate-400">لا توجد عمليات شراء بعد</td></tr>
                                <?php endif; ?>
                                <?php foreach ($purchases as $p): ?>
                                <tr class="purchase-row hover:bg-slate-50 transition-colors">
                                    <td class="p-4 font-num text-sm font-medium text-slate-900"><?= $p['transaction_number'] ?></td>
                                    <td class="p-4 text-sm text-slate-500 font-num"><?= formatDateTimeAr($p['created_at']) ?></td>
                                    <td class="p-4 text-sm">
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-full bg-slate-200 flex items-center justify-center text-xs font-bold text-slate-500"><?= mb_substr($p['seller_name'], 0, 1) ?></div>
                                            <div>
                                                <p class="font-medium"><?= sanitize($p['seller_name']) ?></p>
                                                <p class="text-xs text-slate-500 font-num"><?= sanitize($p['seller_phone']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-4 text-sm">
                                        <div class="flex items-center gap-2">
                                            <span class="material-icons-outlined text-slate-400 text-sm">laptop</span>
                                            <span><?= sanitize($p['device_brand'] . ' ' . $p['device_model']) ?></span>
                                        </div>
                                    </td>
                                    <td class="p-4 text-sm text-slate-500 font-num"><?= sanitize($p['serial_number'] ?: '—') ?></td>
                                    <td class="p-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= getConditionColor($p['device_condition']) ?>">
                                            <?= getConditionAr($p['device_condition']) ?>
                                        </span>
                                    </td>
                                    <td class="p-4 font-num font-bold text-slate-900"><?= number_format($p['purchase_price'], 0) ?> <?= CURRENCY ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <?php
                    $todayCount = $db->fetchOne("SELECT COUNT(*) as cnt FROM used_device_purchases WHERE DATE(created_at) = DATE('now')")['cnt'];
                    $todayTotal = $db->fetchOne("SELECT COALESCE(SUM(purchase_price),0) as total FROM used_device_purchases WHERE DATE(created_at) = DATE('now')")['total'];
                    $inStock = $db->fetchOne("SELECT COUNT(*) as cnt FROM products WHERE is_used = 1 AND is_active = 1")['cnt'];
                    ?>
                    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4">
                        <div class="p-3 bg-blue-50 text-blue-600 rounded-lg">
                            <span class="material-icons-outlined">shopping_bag</span>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 mb-0.5">مشتريات اليوم</p>
                            <h4 class="text-xl font-bold font-num"><?= $todayCount ?></h4>
                        </div>
                    </div>
                    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4">
                        <div class="p-3 bg-green-50 text-green-600 rounded-lg">
                            <span class="material-icons-outlined">payments</span>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 mb-0.5">إجمالي المدفوع</p>
                            <h4 class="text-xl font-bold font-num"><?= number_format($todayTotal) ?> <?= CURRENCY_EN ?></h4>
                        </div>
                    </div>
                    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4 bg-gradient-to-l from-primary/5 to-transparent">
                        <div class="p-3 bg-primary/20 text-primary rounded-lg">
                            <span class="material-icons-outlined">inventory_2</span>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 mb-0.5">في المخزون</p>
                            <h4 class="text-xl font-bold font-num"><?= $inStock ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
document.getElementById('search-purchases')?.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.purchase-row').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
