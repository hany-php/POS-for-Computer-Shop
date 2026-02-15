<?php
require_once __DIR__ . '/includes/bootstrap.php';
Auth::requireLogin();
$pageTitle = 'نقطة البيع';
$user = Auth::user();
$categories = $db->fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order");
$page = max(1, intval($_GET['page'] ?? 1));
$perPageAllowed = [50, 100, 200];
$perPage = intval($_GET['per_page'] ?? 50);
if (!in_array($perPage, $perPageAllowed, true)) {
    $perPage = 50;
}
$totalProducts = intval(($db->fetchOne("SELECT COUNT(*) AS cnt FROM products WHERE is_active = 1")['cnt'] ?? 0));
$totalPages = max(1, (int)ceil($totalProducts / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$products = $db->fetchAll(
    "SELECT p.id, p.name, p.price, p.quantity, p.category_id, p.image_url, p.barcode, COALESCE(c.name, '') as category_name
     FROM products p
     LEFT JOIN categories c ON p.category_id = c.id
     WHERE p.is_active = 1
     ORDER BY CASE WHEN p.quantity <= 0 THEN 1 ELSE 0 END ASC, p.name
     LIMIT $perPage OFFSET $offset"
);
$settings = getStoreSettings();
$taxRate = getTaxRateDecimal();
$taxRatePercentLabel = rtrim(rtrim(number_format($taxRate * 100, 2, '.', ''), '0'), '.');
$showHijriDate = isHijriDateEnabled();

$baseQuery = $_GET;
unset($baseQuery['page']);
function posPageUrl($targetPage, $baseQuery) {
    $q = $baseQuery;
    $q['page'] = $targetPage;
    return 'pos.php?' . http_build_query($q);
}
include __DIR__ . '/includes/header.php';
?>

<style>
/* Per-user adjustable product card sizing */
#products-grid {
    grid-template-columns: repeat(auto-fill, minmax(var(--pc-card-min, 11rem), 1fr));
    gap: var(--pc-gap, 1rem);
}
#products-grid .product-card {
    padding: var(--pc-pad, 0.75rem);
    min-height: var(--pc-card-min-h, 14rem);
    content-visibility: auto;
    contain-intrinsic-size: 260px;
}
#products-grid .product-card h3 {
    font-size: var(--pc-title-size, 1rem);
    line-height: var(--pc-title-lh, 1.25rem);
    height: var(--pc-title-height, 3rem);
}
#products-grid .product-card .aspect-\[4\/3\] {
    aspect-ratio: auto;
    height: var(--pc-media-height, 8.5rem);
}
#products-grid .product-card .text-xs.font-semibold.text-primary.uppercase.tracking-wide {
    font-size: var(--pc-cat-size, 0.75rem);
}
#products-grid .product-card .font-num.font-bold.text-lg.text-slate-900 {
    font-size: var(--pc-price-size, 1.125rem);
}
#products-grid .product-card .w-8.h-8.flex.items-center.justify-center.rounded-lg {
    width: var(--pc-add-size, 2rem);
    height: var(--pc-add-size, 2rem);
}
#products-grid .product-card .product-image {
    transform: scale(var(--pc-image-scale, 1));
    transition: transform 0.25s ease;
}
#products-grid .product-card:hover .product-image {
    transform: scale(calc(var(--pc-image-scale, 1) + 0.03));
}
#products-grid .product-card .product-placeholder-icon {
    font-size: var(--pc-placeholder-size, 2.5rem);
}
</style>

<div class="h-screen overflow-hidden flex flex-col md:flex-row no-print">
    <!-- LEFT SIDE: Product Catalog -->
    <div class="flex-1 flex flex-col h-full overflow-hidden relative border-l border-slate-200">
        <!-- Header -->
        <header class="p-6 pb-2">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <div class="flex items-center gap-3 mb-1">
                        <?php if (!empty($settings['store_logo_url'])): ?>
                        <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center overflow-hidden shadow-sm border border-slate-100">
                            <img src="<?= sanitize($settings['store_logo_url']) ?>" alt="Logo" class="w-full h-full object-contain p-1">
                        </div>
                        <?php endif; ?>
                        <h1 class="text-2xl font-bold text-slate-900"><?= sanitize($settings['store_name']) ?></h1>
                    </div>
                    <p class="text-sm text-slate-600"><?= sanitize(formatGregorianDateArLong()) ?></p>
                    <?php if ($showHijriDate): ?>
                    <p class="text-xs text-slate-500 mt-0.5"><?= sanitize(formatHijriDateArLong()) ?></p>
                    <?php endif; ?>
                </div>
                <!-- Search -->
                <div class="flex-1 max-w-lg mx-6">
                    <div class="relative group">
                        <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-primary transition-colors">
                            <span class="material-icons-outlined">search</span>
                        </div>
                        <input type="text" id="product-search" autofocus
                            class="w-full bg-white border-none rounded-xl py-3 pr-12 pl-4 text-slate-700 shadow-sm ring-1 ring-slate-200 focus:ring-2 focus:ring-primary focus:outline-none transition-all placeholder:text-slate-400"
                            placeholder="ابحث عن منتج، باركود، أو فئة...">
                    </div>
                </div>
                <!-- User -->
                <div class="flex items-center gap-3 no-print">
                    <input type="hidden" id="pos-csrf-token" value="<?= generateCsrfToken() ?>">
                    <div class="hidden xl:flex items-center gap-2 bg-white rounded-full py-1 pl-2 pr-1 shadow-sm ring-1 ring-slate-200">
                        <button type="button" id="card-size-inc" class="w-8 h-8 rounded-full hover:bg-slate-100 text-slate-500 transition-colors" title="تكبير البطاقات">
                            <span class="material-icons-outlined text-[18px]">zoom_in</span>
                        </button>
                        <button type="button" id="card-size-reset" class="w-8 h-8 rounded-full hover:bg-slate-100 text-slate-500 transition-colors" title="الحجم الافتراضي">
                            <span class="material-icons-outlined text-[18px]">restart_alt</span>
                        </button>
                        <button type="button" id="card-size-dec" class="w-8 h-8 rounded-full hover:bg-slate-100 text-slate-500 transition-colors" title="تصغير البطاقات">
                            <span class="material-icons-outlined text-[18px]">zoom_out</span>
                        </button>
                        <input id="card-size-slider" type="range" min="1" max="15" step="1" value="8" class="w-24 accent-primary cursor-pointer" title="تحكم أدق في الحجم">
                        <span id="card-size-label" class="text-xs font-num text-slate-500 min-w-12 text-center">100%</span>
                    </div>
                    <?php if (Auth::hasRole(['admin'])): ?>
                    <a href="admin/dashboard.php" class="p-2 rounded-full bg-white text-slate-500 hover:text-primary transition-colors shadow-sm ring-1 ring-slate-200">
                        <span class="material-icons-outlined">settings</span>
                    </a>
                    <?php endif; ?>
                    <button type="button" onclick="requestCycleClose()" class="flex items-center gap-1.5 bg-white text-slate-600 hover:text-amber-700 border border-slate-200 hover:border-amber-300 rounded-full px-3 py-2 text-xs font-medium transition-colors shadow-sm" title="طلب إقفال دورة المستخدم">
                        <span class="material-icons-outlined text-[18px]">task_alt</span>
                        <span>إقفال الدورة</span>
                    </button>
                    <a href="maintenance.php" class="p-2 rounded-full bg-white text-slate-500 hover:text-primary transition-colors shadow-sm ring-1 ring-slate-200" title="الصيانة">
                        <span class="material-icons-outlined">build</span>
                    </a>
                    <a href="buy-used.php" class="p-2 rounded-full bg-white text-slate-500 hover:text-primary transition-colors shadow-sm ring-1 ring-slate-200" title="شراء مستعمل">
                        <span class="material-icons-outlined">phonelink_setup</span>
                    </a>
                    <div class="flex items-center gap-3 bg-white py-1.5 px-2 pr-1.5 rounded-full shadow-sm ring-1 ring-slate-200">
                        <div class="w-9 h-9 rounded-full bg-primary/10 flex items-center justify-center text-primary text-sm font-bold">
                            <?= mb_substr($user['full_name'], 0, 1) ?>
                        </div>
                        <div class="hidden lg:block pl-2 text-right">
                            <p class="text-sm font-semibold leading-tight"><?= $user['full_name'] ?></p>
                            <p class="text-xs text-slate-400"><?= Auth::getRoleNameAr($user['role']) ?></p>
                        </div>
                        <a href="logout.php" class="material-icons-outlined text-slate-400 text-sm pl-2 hover:text-red-500">logout</a>
                    </div>
                </div>
            </div>
            <!-- Categories -->
            <div class="flex gap-3 overflow-x-auto pb-4 no-scrollbar items-center">
                <button onclick="filterCategory('all')" class="cat-btn flex-shrink-0 px-6 py-2.5 bg-primary text-white rounded-xl shadow-lg shadow-primary/30 font-medium transition-transform active:scale-95" data-category="all">الكل</button>
                <?php foreach ($categories as $cat): ?>
                <button onclick="filterCategory(<?= $cat['id'] ?>)" 
                    class="cat-btn flex-shrink-0 px-6 py-2.5 bg-white text-slate-600 hover:bg-slate-50 border border-slate-200 rounded-xl font-medium transition-all active:scale-95 whitespace-nowrap" 
                    data-category="<?= $cat['id'] ?>"><?= sanitize($cat['name']) ?></button>
                <?php endforeach; ?>
            </div>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-slate-500">المعروض: <span class="font-num font-bold"><?= count($products) ?></span> من <span class="font-num font-bold"><?= $totalProducts ?></span></p>
                <div class="flex items-center gap-2">
                    <form method="GET" class="flex items-center gap-2">
                        <label class="text-xs text-slate-500">عرض</label>
                        <select name="per_page" onchange="this.form.submit()" class="bg-white border border-slate-200 rounded-lg px-2 py-1.5 text-xs font-num">
                            <?php foreach ($perPageAllowed as $pp): ?>
                            <option value="<?= $pp ?>" <?= $pp === $perPage ? 'selected' : '' ?>><?= $pp ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <div class="flex items-center gap-1 text-xs">
                        <?php if ($page > 1): ?>
                        <a href="<?= sanitize(posPageUrl($page - 1, $baseQuery)) ?>" class="px-2 py-1.5 rounded-md border border-slate-200 bg-white hover:bg-slate-50">السابق</a>
                        <?php else: ?><span class="px-2 py-1.5 rounded-md border border-slate-200 bg-slate-100 text-slate-400">السابق</span><?php endif; ?>
                        <span class="font-num text-slate-600 px-2">صفحة <?= $page ?> / <?= $totalPages ?></span>
                        <?php if ($page < $totalPages): ?>
                        <a href="<?= sanitize(posPageUrl($page + 1, $baseQuery)) ?>" class="px-2 py-1.5 rounded-md border border-slate-200 bg-white hover:bg-slate-50">التالي</a>
                        <?php else: ?><span class="px-2 py-1.5 rounded-md border border-slate-200 bg-slate-100 text-slate-400">التالي</span><?php endif; ?>
                    </div>
                </div>
            </div>
        </header>

        <!-- Products Grid -->
        <main class="flex-1 overflow-y-auto px-6 pb-6">
            <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4" id="products-grid">
                <?php if (empty($products)): ?>
                <div class="col-span-full bg-white border border-slate-200 rounded-xl p-6 text-center text-slate-400">لا توجد منتجات متاحة في هذه الصفحة</div>
                <?php endif; ?>
                <?php foreach ($products as $p): ?>
                <div class="product-card group bg-white rounded-2xl p-3 border border-slate-100 shadow-sm hover:shadow-md hover:border-primary/30 transition-all cursor-pointer"
                     data-id="<?= $p['id'] ?>"
                     data-name="<?= sanitize($p['name']) ?>"
                     data-price="<?= $p['price'] ?>"
                     data-category="<?= $p['category_id'] ?>"
                     data-image="<?= sanitize($p['image_url'] ?? '') ?>"
                     data-qty="<?= $p['quantity'] ?>"
                     data-barcode="<?= sanitize($p['barcode'] ?? '') ?>"
                     data-out-of-stock="<?= intval($p['quantity']) <= 0 ? '1' : '0' ?>"
                     onclick="<?= intval($p['quantity']) > 0 ? 'addToCart(this)' : 'return false;' ?>">
                    <div class="aspect-[4/3] rounded-xl overflow-hidden bg-slate-50 mb-3 relative">
                        <?php if ($p['image_url']): ?>
                        <img class="product-image w-full h-full object-contain p-1" 
                             src="<?= sanitize($p['image_url']) ?>" alt="<?= sanitize($p['name']) ?>" loading="lazy" decoding="async" fetchpriority="low">
                        <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center text-slate-300">
                            <span class="product-placeholder-icon material-icons-outlined">inventory_2</span>
                        </div>
                        <?php endif; ?>
                        <?php if (intval($p['quantity']) <= 0): ?>
                        <div class="absolute inset-0 bg-black/40 flex items-center justify-center">
                            <span class="bg-red-600 text-white text-xs font-bold px-3 py-1 rounded-md shadow-sm">منتهي</span>
                        </div>
                        <?php elseif ($p['quantity'] <= LOW_STOCK_THRESHOLD): ?>
                        <div class="absolute top-2 left-2 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-md shadow-sm">كمية محدودة</div>
                        <?php endif; ?>
                    </div>
                    <div class="space-y-1">
                        <p class="text-xs font-semibold text-primary uppercase tracking-wide"><?= sanitize($p['category_name'] ?? '') ?></p>
                        <h3 class="font-bold text-slate-800 line-clamp-2 h-12 leading-tight"><?= sanitize($p['name']) ?></h3>
                        <div class="flex items-center justify-between mt-2 pt-2 border-t border-dashed border-slate-200">
                            <span class="font-num font-bold text-lg text-slate-900"><?= number_format($p['price'], 0) ?> <span class="text-xs font-normal text-slate-500"><?= CURRENCY ?></span></span>
                            <button class="w-8 h-8 flex items-center justify-center rounded-lg transition-colors <?= intval($p['quantity']) > 0 ? 'bg-primary/10 text-primary hover:bg-primary hover:text-white' : 'bg-slate-200 text-slate-400 cursor-not-allowed' ?>" <?= intval($p['quantity']) <= 0 ? 'disabled' : '' ?>>
                                <span class="material-icons-outlined text-lg">add</span>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-5 mb-2 flex flex-wrap items-center justify-between gap-3 text-xs">
                <p class="text-slate-500">المعروض: <span class="font-num font-bold"><?= count($products) ?></span> من <span class="font-num font-bold"><?= $totalProducts ?></span></p>
                <div class="flex items-center gap-1">
                    <?php if ($page > 1): ?>
                    <a href="<?= sanitize(posPageUrl($page - 1, $baseQuery)) ?>" class="px-2 py-1.5 rounded-md border border-slate-200 bg-white hover:bg-slate-50">السابق</a>
                    <?php else: ?><span class="px-2 py-1.5 rounded-md border border-slate-200 bg-slate-100 text-slate-400">السابق</span><?php endif; ?>
                    <span class="font-num text-slate-600 px-2">صفحة <?= $page ?> / <?= $totalPages ?></span>
                    <?php if ($page < $totalPages): ?>
                    <a href="<?= sanitize(posPageUrl($page + 1, $baseQuery)) ?>" class="px-2 py-1.5 rounded-md border border-slate-200 bg-white hover:bg-slate-50">التالي</a>
                    <?php else: ?><span class="px-2 py-1.5 rounded-md border border-slate-200 bg-slate-100 text-slate-400">التالي</span><?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- RIGHT SIDE: Shopping Cart -->
    <div class="w-full md:w-[380px] lg:w-[420px] bg-white flex flex-col shadow-2xl z-10">
        <!-- Cart Header -->
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <div>
                <h2 class="text-xl font-bold text-slate-900">سلة المشتريات</h2>
                <span class="text-sm text-slate-500" id="cart-count">0 عناصر</span>
                <p id="suspended-order-badge" class="hidden mt-1 text-[11px] text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-2 py-1 w-fit"></p>
            </div>
            <button onclick="clearCart()" class="w-9 h-9 flex items-center justify-center rounded-full text-red-500 hover:bg-red-50 transition-colors" title="إفراغ السلة">
                <span class="material-icons-outlined text-xl">delete_outline</span>
            </button>
        </div>

        <!-- Cart Items -->
        <div class="flex-1 overflow-y-auto p-4 space-y-3" id="cart-items">
            <div id="cart-empty" class="h-full flex flex-col items-center justify-center text-slate-400">
                <span class="material-icons-outlined text-6xl mb-4">shopping_cart</span>
                <p class="font-medium">السلة فارغة</p>
                <p class="text-sm">اضغط على المنتجات لإضافتها</p>
            </div>
            <div id="cart-list"></div>
        </div>

        <!-- Footer Totals -->
        <div class="p-6 bg-white border-t border-slate-100 shadow-[0_-5px_15px_-5px_rgba(0,0,0,0.05)] z-20">
            <!-- Invoice Discount -->
            <div class="flex items-center gap-2 mb-3 pb-3 border-b border-dashed border-slate-200">
                <span class="material-icons-outlined text-sm text-green-600">local_offer</span>
                <span class="text-xs font-medium text-slate-600">خصم على الفاتورة</span>
                <div class="flex-1"></div>
                <div class="flex items-center bg-slate-100 rounded-lg overflow-hidden h-8">
                    <button onclick="setDiscountType('value')" id="disc-type-value" class="disc-type-btn px-3 h-full text-xs font-bold bg-green-600 text-white transition-colors">قيمة</button>
                    <button onclick="setDiscountType('percent')" id="disc-type-percent" class="disc-type-btn px-3 h-full text-xs font-medium text-slate-500 transition-colors">نسبة %</button>
                </div>
                <input type="number" id="invoice-discount" value="0" min="0" step="1" dir="ltr"
                    class="w-20 h-8 text-center font-num text-sm bg-green-50 border border-green-200 rounded-lg focus:outline-none focus:border-green-500 text-green-700 font-bold"
                    oninput="updateTotals()">
            </div>
            <div class="space-y-3 mb-6">
                <div class="flex justify-between text-slate-600 text-sm">
                    <span>المجموع الفرعي</span>
                    <span class="font-num font-medium text-slate-800" id="subtotal">0 <?= CURRENCY ?></span>
                </div>
                <div class="flex justify-between text-slate-600 text-sm" id="tax-row" style="<?= $taxRate == 0 ? 'display:none' : '' ?>">
                    <span>الضريبة (<?= $taxRatePercentLabel ?>%)</span>
                    <span class="font-num font-medium text-slate-800" id="tax-amount">0 <?= CURRENCY ?></span>
                </div>
                <div class="flex justify-between text-sm" id="discount-row" style="display:none">
                    <span class="text-green-600">خصم</span>
                    <span class="font-num font-medium text-green-600" id="discount-amount">-0 <?= CURRENCY ?></span>
                </div>
                <div class="h-px border-t border-dashed border-slate-200 my-2"></div>
                <div class="flex justify-between items-end">
                    <span class="text-lg font-bold text-slate-900">الإجمالي</span>
                    <span class="font-num text-2xl font-bold text-primary" id="total-amount">0 <span class="text-sm font-normal text-slate-500"><?= CURRENCY ?></span></span>
                </div>
            </div>
            <!-- Actions -->
            <div class="grid grid-cols-4 gap-3">
                <button onclick="printReceipt()" class="col-span-1 py-4 rounded-xl border border-slate-200 text-slate-500 hover:border-slate-300 hover:text-slate-700 transition-colors flex flex-col items-center justify-center gap-1 group no-print">
                    <span class="material-icons-outlined text-xl group-hover:scale-110 transition-transform">print</span>
                </button>
                <button onclick="holdOrder()" class="col-span-1 py-4 rounded-xl border border-slate-200 text-slate-500 hover:border-slate-300 hover:text-slate-700 transition-colors flex flex-col items-center justify-center gap-1 group no-print">
                    <span class="material-icons-outlined text-xl group-hover:scale-110 transition-transform">save</span>
                </button>
                <button onclick="proceedToPayment()" id="pay-btn" disabled
                    class="col-span-2 py-4 rounded-xl bg-primary text-white font-bold text-lg shadow-lg shadow-primary/30 hover:bg-primary-dark active:scale-[0.98] transition-all flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed no-print">
                    <span>دفع</span>
                    <span class="material-icons-outlined">payments</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div id="payment-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white w-full max-w-[1100px] h-[90vh] rounded-2xl shadow-2xl overflow-hidden flex flex-col md:flex-row border border-slate-100">
        <!-- Payment Zone -->
        <div class="flex-1 flex flex-col p-6 md:p-8 bg-white relative z-10 order-1 overflow-hidden">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 mb-1">إتمام عملية الدفع</h1>
                    <p class="text-slate-500 text-sm">اختر نوع السداد وأدخل المبلغ</p>
                </div>
                <button onclick="closePayment()" class="p-2 rounded-full hover:bg-slate-100 text-slate-500 transition-colors">
                    <span class="material-icons-outlined">close</span>
                </button>
            </div>

            <div class="flex-1 min-h-0 overflow-y-auto pr-1">
            <!-- Total -->
            <div class="bg-blue-50 rounded-xl p-6 mb-6 flex justify-between items-center border border-blue-100">
                <span class="text-slate-600 font-medium text-lg">المبلغ الإجمالي</span>
                <div class="text-right">
                    <span class="text-4xl font-bold text-primary font-num" id="modal-total">0.00</span>
                    <span class="text-primary/70 text-sm font-medium mr-1"><?= CURRENCY ?></span>
                </div>
            </div>

            <!-- Settlement Mode -->
            <div class="mb-6">
                <div class="flex items-center justify-between mb-3">
                    <label class="block text-sm font-medium text-slate-700">نوع السداد</label>
                    <span class="text-xs text-slate-500">طريقة الدفع الحالية: نقدي</span>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <button type="button" onclick="setSettlementMode('full')" id="settlement-full-btn" class="settlement-mode-btn flex items-center justify-center gap-2 p-3 rounded-xl border-2 border-primary bg-primary/5 text-primary transition-all duration-200">
                        <span class="material-icons-outlined text-xl">payments</span>
                        <span class="font-bold">سداد كامل</span>
                    </button>
                    <button type="button" onclick="setSettlementMode('debt')" id="settlement-debt-btn" class="settlement-mode-btn flex items-center justify-center gap-2 p-3 rounded-xl border border-slate-200 hover:border-primary/50 hover:bg-slate-50 text-slate-600 transition-all duration-200">
                        <span class="material-icons-outlined text-xl">account_balance_wallet</span>
                        <span class="font-bold">دين على العميل</span>
                    </button>
                </div>
                <p id="debt-hint" class="hidden mt-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">في وضع الدين: إذا كان المبلغ المستلم أقل من الإجمالي، سيتم تسجيل المتبقي كدين على العميل.</p>
            </div>

            <!-- Optional Customer -->
            <div class="mb-4 bg-slate-50 border border-slate-200 rounded-xl p-3">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">العميل (اختياري)</label>
                        <div id="customer-selected-inline" class="mt-1 text-xs text-slate-600">
                            <span id="customer-selected-inline-name" class="font-semibold text-slate-800">عميل نقدي</span>
                            <span id="customer-selected-inline-phone" class="font-num mr-2 text-slate-500"></span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="openCustomerPicker()" class="bg-white border border-slate-200 text-slate-700 px-3 py-2 rounded-lg text-xs font-medium hover:bg-slate-100">
                            اختيار / إضافة عميل
                        </button>
                        <button type="button" onclick="clearSelectedCustomer()" class="text-xs text-slate-500 hover:text-red-600">إلغاء</button>
                    </div>
                </div>
            </div>

            <!-- Due Invoice Summary -->
            <div id="due-invoice-panel" class="hidden mb-4 bg-amber-50 border border-amber-200 rounded-xl p-3">
                <div class="grid grid-cols-3 gap-2 text-xs mb-2">
                    <div class="bg-white border border-amber-100 rounded-lg px-2 py-1.5">
                        <p class="text-slate-500">إجمالي الفاتورة</p>
                        <p id="due-total-amount" class="font-num font-bold text-slate-900">0.00</p>
                    </div>
                    <div class="bg-white border border-amber-100 rounded-lg px-2 py-1.5">
                        <p class="text-slate-500">إجمالي المدفوع</p>
                        <p id="due-paid-amount" class="font-num font-bold text-emerald-700">0.00</p>
                    </div>
                    <div class="bg-white border border-amber-100 rounded-lg px-2 py-1.5">
                        <p class="text-slate-500">المتبقي</p>
                        <p id="due-remaining-amount" class="font-num font-bold text-amber-700">0.00</p>
                    </div>
                </div>
                <div>
                    <p class="text-xs text-slate-600 mb-1">سجل المدفوعات</p>
                    <div id="due-payments-list" class="max-h-28 overflow-auto divide-y divide-amber-100 bg-white border border-amber-100 rounded-lg">
                        <p class="p-2 text-[11px] text-slate-400 text-center">لا توجد مدفوعات مسجلة</p>
                    </div>
                    <div class="mt-2 flex justify-end">
                        <button type="button" onclick="printDuePaymentsStatement()" class="bg-white border border-amber-300 text-amber-800 px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-amber-100">
                            طباعة كشف المدفوعات
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex gap-6 flex-1">
                <!-- Amount Input -->
                <div class="flex-1 flex flex-col">
                    <div class="mb-4 relative">
                        <label class="block text-sm font-medium text-slate-700 mb-2">المبلغ المستلم</label>
                        <div class="relative">
                            <input type="text" id="received-amount" dir="ltr"
                                class="w-full text-left pl-16 pr-4 py-4 text-2xl font-bold font-num text-slate-900 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary outline-none"
                                value="0">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 font-medium"><?= CURRENCY_EN ?></span>
                        </div>
                    </div>
                    <!-- Quick Amounts -->
                    <div class="flex gap-2 mb-4">
                        <button onclick="addAmount(10)" class="flex-1 py-2 px-3 bg-slate-100 hover:bg-slate-200 rounded-lg text-sm font-num text-slate-700 transition-colors">+ 10</button>
                        <button onclick="addAmount(50)" class="flex-1 py-2 px-3 bg-slate-100 hover:bg-slate-200 rounded-lg text-sm font-num text-slate-700 transition-colors">+ 50</button>
                        <button onclick="addAmount(100)" class="flex-1 py-2 px-3 bg-slate-100 hover:bg-slate-200 rounded-lg text-sm font-num text-slate-700 transition-colors">+ 100</button>
                        <button onclick="addAmount(500)" class="flex-1 py-2 px-3 bg-slate-100 hover:bg-slate-200 rounded-lg text-sm font-num text-slate-700 transition-colors">+ 500</button>
                        <button onclick="setExact()" class="flex-1 py-2 px-3 bg-primary/10 hover:bg-primary/20 rounded-lg text-sm text-primary font-bold transition-colors">المبلغ بالضبط</button>
                    </div>
                    <!-- Change -->
                    <div class="flex justify-between items-center p-4 bg-green-50 rounded-xl border border-green-100 mb-auto">
                    <span id="change-label" class="text-green-800 font-bold">المتبقي للعميل</span>
                        <div class="flex items-baseline gap-1 text-green-700">
                            <span class="text-2xl font-bold font-num" id="change-amount">0.00</span>
                            <span class="text-sm"><?= CURRENCY ?></span>
                        </div>
                    </div>
                </div>
                <!-- Numpad -->
                <div class="w-56 grid grid-cols-3 gap-3">
                    <?php for ($i = 1; $i <= 9; $i++): ?>
                    <button onclick="numpad('<?= $i ?>')" class="h-14 rounded-lg bg-white border border-slate-200 shadow-sm text-xl font-num font-medium hover:bg-slate-50 text-slate-800 transition-colors"><?= $i ?></button>
                    <?php endfor; ?>
                    <button onclick="numpad('.')" class="h-14 rounded-lg bg-white border border-slate-200 shadow-sm text-xl font-num font-medium hover:bg-slate-50 text-slate-800 transition-colors">.</button>
                    <button onclick="numpad('0')" class="h-14 rounded-lg bg-white border border-slate-200 shadow-sm text-xl font-num font-medium hover:bg-slate-50 text-slate-800 transition-colors">0</button>
                    <button onclick="numpad('back')" class="h-14 rounded-lg bg-red-50 border border-red-100 shadow-sm hover:bg-red-100 text-red-600 transition-colors flex items-center justify-center">
                        <span class="material-icons-outlined">backspace</span>
                    </button>
                </div>
            </div>
            </div>

            <!-- Confirm button -->
            <div class="shrink-0 mt-4 pt-4 border-t border-slate-100 bg-white flex gap-4">
                <button onclick="completeSale()" id="complete-sale-btn"
                    class="flex-1 bg-primary hover:bg-primary-dark text-white py-4 rounded-xl font-bold text-lg shadow-lg shadow-blue-500/20 flex items-center justify-center gap-2 transition-transform active:scale-[0.98]">
                    <span class="material-icons-outlined">check_circle</span>
                    إتمام البيع
                </button>
                <button onclick="printReceipt()" class="px-6 py-4 rounded-xl border border-slate-300 text-slate-700 font-semibold hover:bg-slate-50 flex items-center gap-2 transition-colors">
                    <span class="material-icons-outlined">print</span>
                    طباعة
                </button>
            </div>
        </div>

        <!-- Receipt Preview -->
        <div class="w-full md:w-[380px] bg-slate-50 border-r border-slate-200 flex flex-col p-6 order-2 relative overflow-hidden">
            <div class="absolute inset-0 opacity-[0.03] pointer-events-none" style="background-image: radial-gradient(#000 1px, transparent 1px); background-size: 20px 20px;"></div>
            <h2 class="text-sm uppercase tracking-wider text-slate-500 font-semibold mb-4 flex items-center gap-2">
                <span class="material-icons-outlined text-base">receipt_long</span>
                معاينة الإيصال
            </h2>
            <div class="bg-white text-black shadow-lg rounded-sm w-full flex-1 flex flex-col relative overflow-y-auto text-sm leading-tight" id="receipt-preview">
                <div class="h-2 w-full bg-gradient-to-b from-slate-100 to-white"></div>
                <div class="p-5 flex-1 flex flex-col items-center">
                    <?php if (!empty($settings['store_logo_url'])): ?>
                    <div class="w-20 h-20 mb-3 flex items-center justify-center">
                        <img src="<?= sanitize($settings['store_logo_url']) ?>" alt="Logo" class="max-w-full max-h-full object-contain">
                    </div>
                    <?php else: ?>
                    <div class="w-16 h-16 bg-black rounded-full flex items-center justify-center text-white mb-3">
                        <span class="material-icons-outlined text-3xl">computer</span>
                    </div>
                    <?php endif; ?>
                    <h3 class="font-bold text-lg mb-1"><?= sanitize($settings['store_name']) ?></h3>
                    <p class="text-xs text-slate-500 text-center mb-1"><?= sanitize($settings['store_address']) ?></p>
                    <p class="text-xs text-slate-500 mb-4">هاتف: <?= sanitize($settings['store_phone']) ?></p>
                    <div class="w-full border-b border-dashed border-slate-300 mb-4"></div>
                    <div class="w-full flex justify-between text-xs text-slate-600 mb-4">
                        <div class="text-right">
                            <p>رقم الفاتورة: <span class="font-num" id="receipt-number">---</span></p>
                            <p>الكاشير: <?= $user['full_name'] ?></p>
                            <p>العميل: <span id="receipt-customer-name">عميل نقدي</span></p>
                            <p id="receipt-customer-phone-wrap" class="hidden">الهاتف: <span id="receipt-customer-phone" class="font-num"></span></p>
                        </div>
                        <div class="text-left font-num" dir="ltr">
                            <p><?= sanitize(formatGregorianDateArLong()) ?></p>
                            <?php if ($showHijriDate): ?>
                            <p><?= sanitize(formatHijriDateArLong()) ?></p>
                            <?php endif; ?>
                            <p><?= date('H:i') ?></p>
                        </div>
                    </div>
                    <div class="w-full grid grid-cols-4 gap-2 text-xs font-bold border-b border-black pb-2 mb-2">
                        <div class="col-span-2">الصنف</div>
                        <div class="text-center">الكمية</div>
                        <div class="text-left">السعر</div>
                    </div>
                    <div id="receipt-items" class="w-full flex flex-col gap-2 mb-4">
                        <p class="text-xs text-slate-400 text-center py-4">لا توجد عناصر</p>
                    </div>
                    <div class="w-full border-b border-dashed border-slate-300 mb-3"></div>
                    <div class="w-full space-y-1 mb-4">
                        <div id="receipt-subtotal-wrap" class="flex justify-between text-xs text-slate-600"><span>المجموع الفرعي</span><span class="font-num" id="receipt-subtotal">0.00</span></div>
                        <div class="flex justify-between text-xs text-slate-600"><span>ضريبة القيمة المضافة (<?= $taxRatePercentLabel ?>%)</span><span class="font-num" id="receipt-tax">0.00</span></div>
                    </div>
                    <div class="w-full border-t border-black pt-2 mb-6">
                        <div class="flex justify-between items-center text-lg font-bold"><span>الإجمالي</span><span class="font-num" id="receipt-total">0.00</span></div>
                        <div id="receipt-paid-wrap" class="hidden justify-between items-center text-xs text-slate-600 mt-2">
                            <span>المدفوع نقدًا</span>
                            <span class="font-num" id="receipt-paid">0.00</span>
                        </div>
                        <div id="receipt-due-wrap" class="hidden flex justify-between items-center text-xs text-red-600 mt-1">
                            <span>المتبقي دين</span>
                            <span class="font-num" id="receipt-due">0.00</span>
                        </div>
                        <div id="receipt-invoice-totals" class="hidden mt-2 pt-2 border-t border-dashed border-slate-300 text-[11px] space-y-1">
                            <div class="flex justify-between text-slate-600"><span>إجمالي الفاتورة الفعلي</span><span id="receipt-invoice-total" class="font-num">0.00</span></div>
                            <div class="flex justify-between text-emerald-700"><span>إجمالي المدفوع</span><span id="receipt-invoice-paid-total" class="font-num">0.00</span></div>
                            <div class="flex justify-between text-amber-700"><span>إجمالي المتبقي</span><span id="receipt-invoice-remaining-total" class="font-num">0.00</span></div>
                        </div>
                    </div>
                    <p class="text-xs text-slate-500 font-bold mb-1">شكراً لزيارتكم</p>
                    <p class="text-[10px] text-slate-400">يرجى الاحتفاظ بالإيصال للاستبدال</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Customer Picker Modal -->
<div id="customer-picker-modal" class="hidden fixed inset-0 z-[60] bg-black/40 backdrop-blur-sm">
    <div class="w-full h-full flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-xl rounded-2xl border border-slate-200 shadow-2xl overflow-hidden">
            <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-bold text-slate-900">اختيار العميل</h3>
                <button type="button" onclick="closeCustomerPicker()" class="p-2 rounded-lg hover:bg-slate-100 text-slate-500">
                    <span class="material-icons-outlined">close</span>
                </button>
            </div>
            <div class="p-4 space-y-4">
                <div class="relative">
                    <input type="text" id="customer-search" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary" placeholder="ابحث بالاسم أو رقم الهاتف...">
                    <div id="customer-results" class="hidden absolute z-30 mt-1 w-full bg-white border border-slate-200 rounded-lg shadow-lg max-h-52 overflow-auto"></div>
                </div>

                <div class="pt-3 border-t border-dashed border-slate-200">
                    <p class="text-xs text-slate-500 mb-2">إضافة عميل جديد سريعًا</p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <input type="text" id="customer-new-name" class="sm:col-span-2 bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-xs focus:outline-none focus:border-primary" placeholder="اسم العميل">
                        <div class="flex gap-2">
                            <input type="text" id="customer-new-phone" dir="ltr" class="flex-1 bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-xs font-num text-left focus:outline-none focus:border-primary" placeholder="الهاتف">
                            <button type="button" onclick="createCustomerQuick()" class="bg-primary text-white px-3 py-2 rounded-lg text-xs font-medium hover:bg-primary-dark">إضافة</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Suspended Orders Modal -->
<div id="suspended-orders-modal" class="hidden fixed inset-0 z-[60] bg-black/40 backdrop-blur-sm">
    <div class="w-full h-full flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-3xl rounded-2xl border border-slate-200 shadow-2xl overflow-hidden">
            <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-slate-900">إدارة الطلبات والفواتير</h3>
                    <p class="text-xs text-slate-500 mt-0.5">حفظ/حذف طلبات مؤقتة + استدعاء فواتير الدين غير المسددة</p>
                </div>
                <button type="button" onclick="closeSuspendedOrdersModal()" class="p-2 rounded-lg hover:bg-slate-100 text-slate-500">
                    <span class="material-icons-outlined">close</span>
                </button>
            </div>
            <div class="p-4 grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="space-y-3">
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                        <p class="text-xs text-slate-500 mb-2">حفظ السلة الحالية كطلب معلق</p>
                        <div class="flex gap-2">
                            <input type="text" id="suspended-note" class="flex-1 bg-white border border-slate-200 rounded-lg px-3 py-2 text-xs focus:outline-none focus:border-primary" placeholder="ملاحظة (اختيارية)">
                            <button type="button" onclick="saveCurrentAsSuspended()" class="bg-primary text-white px-4 py-2 rounded-lg text-xs font-medium hover:bg-primary-dark">حفظ</button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs text-slate-500 mb-2">الطلبات المحفوظة (يمكن حذفها)</label>
                        <input type="text" id="suspended-search" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary" placeholder="ابحث برقم الطلب أو اسم العميل أو الهاتف...">
                        <div id="suspended-search-results" class="mt-2 border border-slate-200 rounded-lg max-h-80 overflow-auto divide-y divide-slate-100">
                            <p class="p-3 text-xs text-slate-400 text-center">اكتب للبحث عن الطلبات المحفوظة</p>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-2">فواتير الدين غير المسددة</label>
                    <input type="text" id="due-invoice-search" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary" placeholder="ابحث برقم الفاتورة أو اسم العميل أو الهاتف...">
                    <div id="due-invoice-results" class="mt-2 border border-slate-200 rounded-lg max-h-[26rem] overflow-auto divide-y divide-slate-100">
                        <p class="p-3 text-xs text-slate-400 text-center">اكتب للبحث عن فواتير الدين</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Cart state
let cart = [];
let selectedPaymentMethod = 'cash';
let settlementMode = 'full'; // full | debt
let invoiceDiscountType = 'value'; // 'value' or 'percent'
const TAX_RATE = <?= $taxRate ?>;
const CURRENCY = '<?= CURRENCY ?>';
const SHOW_HIJRI_DATES = <?= $showHijriDate ? 'true' : 'false' ?>;
const POS_CARD_SIZE_KEY = 'pos_card_size_level_user_<?= intval($user['id']) ?>';
const POS_CART_KEY = 'pos_cart_state_user_<?= intval($user['id']) ?>';
const CUSTOMER_API_URL = 'api/customers.php';
const SUSPENDED_ORDER_API_URL = 'api/suspended-orders.php';
const DUE_INVOICE_API_URL = 'api/due-invoices.php';
const POS_CARD_SIZE_MIN = 1;
const POS_CARD_SIZE_MAX = 15;
const POS_CARD_SIZE_DEFAULT = 8;
let selectedCustomer = null;
let activeSuspendedOrder = null;
let activeDueInvoice = null; // {id, orderNumber, totalAmount, paidAmount, dueAmount, payments[]}

function escapeHtml(text) {
    return String(text ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function updateReceiptCustomerDisplay() {
    const nameEl = document.getElementById('receipt-customer-name');
    const phoneEl = document.getElementById('receipt-customer-phone');
    const phoneWrap = document.getElementById('receipt-customer-phone-wrap');
    if (!nameEl || !phoneEl || !phoneWrap) return;

    if (selectedCustomer && selectedCustomer.name) {
        nameEl.textContent = selectedCustomer.name;
        const phone = selectedCustomer.phone || '';
        if (phone) {
            phoneEl.textContent = phone;
            phoneWrap.classList.remove('hidden');
        } else {
            phoneEl.textContent = '';
            phoneWrap.classList.add('hidden');
        }
    } else {
        nameEl.textContent = 'عميل نقدي';
        phoneEl.textContent = '';
        phoneWrap.classList.add('hidden');
    }
}

function setSelectedCustomer(customer) {
    selectedCustomer = customer && customer.id ? {
        id: parseInt(customer.id, 10),
        name: String(customer.name || ''),
        phone: String(customer.phone || '')
    } : null;

    const selectedName = document.getElementById('customer-selected-inline-name');
    const selectedPhone = document.getElementById('customer-selected-inline-phone');
    if (selectedName && selectedPhone) {
        if (selectedCustomer) {
            selectedName.textContent = selectedCustomer.name;
            selectedPhone.textContent = selectedCustomer.phone || '';
        } else {
            selectedName.textContent = 'عميل نقدي';
            selectedPhone.textContent = '';
        }
    }
    updateReceiptCustomerDisplay();
}

function setSettlementMode(mode) {
    settlementMode = (mode === 'debt') ? 'debt' : 'full';
    const fullBtn = document.getElementById('settlement-full-btn');
    const debtBtn = document.getElementById('settlement-debt-btn');
    const debtHint = document.getElementById('debt-hint');

    if (fullBtn) {
        fullBtn.className = settlementMode === 'full'
            ? 'settlement-mode-btn flex items-center justify-center gap-2 p-3 rounded-xl border-2 border-primary bg-primary/5 text-primary transition-all duration-200'
            : 'settlement-mode-btn flex items-center justify-center gap-2 p-3 rounded-xl border border-slate-200 hover:border-primary/50 hover:bg-slate-50 text-slate-600 transition-all duration-200';
    }
    if (debtBtn) {
        debtBtn.className = settlementMode === 'debt'
            ? 'settlement-mode-btn flex items-center justify-center gap-2 p-3 rounded-xl border-2 border-amber-500 bg-amber-50 text-amber-700 transition-all duration-200'
            : 'settlement-mode-btn flex items-center justify-center gap-2 p-3 rounded-xl border border-slate-200 hover:border-primary/50 hover:bg-slate-50 text-slate-600 transition-all duration-200';
    }
    if (debtHint) {
        debtHint.classList.toggle('hidden', settlementMode !== 'debt');
    }
    updateChange();
}

function clearSelectedCustomer() {
    setSelectedCustomer(null);
    const search = document.getElementById('customer-search');
    const results = document.getElementById('customer-results');
    if (search) search.value = '';
    if (results) {
        results.innerHTML = '';
        results.classList.add('hidden');
    }
    closeCustomerPicker();
}

function openCustomerPicker() {
    const modal = document.getElementById('customer-picker-modal');
    const input = document.getElementById('customer-search');
    if (modal) modal.classList.remove('hidden');
    if (input) setTimeout(() => input.focus(), 50);
}

function closeCustomerPicker() {
    const modal = document.getElementById('customer-picker-modal');
    if (modal) modal.classList.add('hidden');
}

let customerSearchTimer = null;
async function searchCustomers(query) {
    const results = document.getElementById('customer-results');
    if (!results) return;
    const q = String(query || '').trim();
    if (q.length < 1) {
        results.innerHTML = '';
        results.classList.add('hidden');
        return;
    }

    try {
        const res = await fetch(CUSTOMER_API_URL + '?q=' + encodeURIComponent(q));
        const data = await res.json();
        if (!data || !data.success || !Array.isArray(data.customers) || data.customers.length === 0) {
            results.innerHTML = '<div class="px-3 py-2 text-xs text-slate-400">لا يوجد عميل مطابق</div>';
            results.classList.remove('hidden');
            return;
        }
        results.innerHTML = data.customers.map(c => `
            <button type="button" class="w-full text-right px-3 py-2 text-xs hover:bg-slate-50 border-b last:border-b-0 border-slate-100"
                    data-role="customer-result-item">
                <span class="font-semibold text-slate-800">${escapeHtml(c.name || '')}</span>
                <span class="font-num text-slate-500 mr-2">${escapeHtml(c.phone || '')}</span>
            </button>
        `).join('');

        // bind click safely (avoid inline JSON escaping complexity)
        Array.from(results.querySelectorAll('button')).forEach((btn, idx) => {
            btn.onclick = () => {
                setSelectedCustomer(data.customers[idx]);
                results.classList.add('hidden');
                closeCustomerPicker();
            };
        });
        results.classList.remove('hidden');
    } catch (_) {
        results.innerHTML = '<div class="px-3 py-2 text-xs text-red-500">تعذر البحث الآن</div>';
        results.classList.remove('hidden');
    }
}

async function createCustomerQuick() {
    const nameEl = document.getElementById('customer-new-name');
    const phoneEl = document.getElementById('customer-new-phone');
    if (!nameEl || !phoneEl) return;
    const name = nameEl.value.trim();
    const phone = phoneEl.value.trim();
    if (!name) {
        alert('اسم العميل مطلوب');
        nameEl.focus();
        return;
    }

    try {
        const res = await fetch(CUSTOMER_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'create', name, phone })
        });
        const data = await res.json();
        if (!data || !data.success || !data.customer) {
            alert((data && data.error) ? data.error : 'تعذر إضافة العميل');
            return;
        }
        setSelectedCustomer(data.customer);
        nameEl.value = '';
        phoneEl.value = '';
        const results = document.getElementById('customer-results');
        if (results) {
            results.innerHTML = '';
            results.classList.add('hidden');
        }
        closeCustomerPicker();
    } catch (_) {
        alert('تعذر الاتصال بالخادم');
    }
}

function saveCartState() {
    try {
        const payload = {
            cart: cart.map(i => ({
                id: parseInt(i.id, 10),
                name: i.name || '',
                price: parseFloat(i.price || 0),
                image: i.image || '',
                qty: parseInt(i.qty || 0, 10),
                maxQty: parseInt(i.maxQty || 0, 10),
                discount: parseFloat(i.discount || 0),
                discountType: (i.discountType === 'percent') ? 'percent' : 'value',
                discountInput: parseFloat(i.discountInput || 0),
                showDiscount: !!i.showDiscount
            })).filter(i => i.id > 0 && i.qty > 0),
            invoiceDiscountType: invoiceDiscountType === 'percent' ? 'percent' : 'value',
            invoiceDiscountValue: parseFloat(document.getElementById('invoice-discount')?.value || 0),
            selectedCustomer: selectedCustomer ? { id: selectedCustomer.id, name: selectedCustomer.name, phone: selectedCustomer.phone } : null,
            activeDueInvoice: activeDueInvoice ? {
                id: activeDueInvoice.id,
                orderNumber: activeDueInvoice.orderNumber,
                totalAmount: parseFloat(activeDueInvoice.totalAmount || 0),
                paidAmount: parseFloat(activeDueInvoice.paidAmount || 0),
                dueAmount: parseFloat(activeDueInvoice.dueAmount || 0),
                payments: Array.isArray(activeDueInvoice.payments) ? activeDueInvoice.payments : []
            } : null
        };
        localStorage.setItem(POS_CART_KEY, JSON.stringify(payload));
    } catch (_) {}
}

function clearCartState() {
    try { localStorage.removeItem(POS_CART_KEY); } catch (_) {}
}

function loadCartState() {
    try {
        const raw = localStorage.getItem(POS_CART_KEY);
        if (!raw) return;
        const parsed = JSON.parse(raw);
        if (!parsed || !Array.isArray(parsed.cart)) return;
        cart = parsed.cart
            .map(i => ({
                id: parseInt(i.id, 10),
                name: String(i.name || ''),
                price: parseFloat(i.price || 0),
                image: String(i.image || ''),
                qty: Math.max(1, parseInt(i.qty || 1, 10)),
                maxQty: Math.max(1, parseInt(i.maxQty || i.qty || 1, 10)),
                discount: Math.max(0, parseFloat(i.discount || 0)),
                discountType: i.discountType === 'percent' ? 'percent' : 'value',
                discountInput: Math.max(0, parseFloat(i.discountInput || 0)),
                showDiscount: !!i.showDiscount
            }))
            .filter(i => i.id > 0 && i.price >= 0 && i.qty > 0);

        invoiceDiscountType = parsed.invoiceDiscountType === 'percent' ? 'percent' : 'value';
        const invoiceInput = document.getElementById('invoice-discount');
        if (invoiceInput) {
            invoiceInput.value = String(Math.max(0, parseFloat(parsed.invoiceDiscountValue || 0)));
        }
        if (parsed.selectedCustomer && parsed.selectedCustomer.id) {
            setSelectedCustomer(parsed.selectedCustomer);
        }
        if (parsed.activeDueInvoice && parsed.activeDueInvoice.id) {
            activeDueInvoice = {
                id: parseInt(parsed.activeDueInvoice.id, 10),
                orderNumber: String(parsed.activeDueInvoice.orderNumber || ''),
                totalAmount: parseFloat(parsed.activeDueInvoice.totalAmount || 0),
                paidAmount: parseFloat(parsed.activeDueInvoice.paidAmount || 0),
                dueAmount: parseFloat(parsed.activeDueInvoice.dueAmount || 0),
                payments: Array.isArray(parsed.activeDueInvoice.payments) ? parsed.activeDueInvoice.payments : []
            };
            setSettlementMode('debt');
        }
        updateSuspendedBadge();
        renderDueInvoicePanel();
        updateInvoiceTotalsInReceipt();
    } catch (_) {}
}

function getProductCardPreset(level) {
    const normalized = normalizeCardSizeLevel(level);
    const ratio = (normalized - POS_CARD_SIZE_MIN) / (POS_CARD_SIZE_MAX - POS_CARD_SIZE_MIN);
    const toRem = (v) => `${v.toFixed(2)}rem`;

    return {
        cardMin: toRem(8.80 + ratio * 8.20),
        cardMinH: toRem(11.80 + ratio * 9.40),
        gap: toRem(0.45 + ratio * 0.75),
        pad: toRem(0.35 + ratio * 0.95),
        titleSize: toRem(0.70 + ratio * 0.65),
        titleLh: toRem(1.00 + ratio * 0.90),
        titleHeight: toRem(2.00 + ratio * 2.20),
        mediaHeight: toRem(5.80 + ratio * 7.20),
        catSize: toRem(0.58 + ratio * 0.34),
        priceSize: toRem(0.84 + ratio * 0.70),
        addSize: toRem(1.45 + ratio * 1.25),
        placeholderSize: toRem(1.80 + ratio * 2.40),
        imageScale: (0.72 + ratio * 0.78).toFixed(3),
        pct: Math.round(45 + ratio * 155)
    };
}

function normalizeCardSizeLevel(level) {
    const parsed = parseInt(level, 10);
    if (Number.isNaN(parsed)) return POS_CARD_SIZE_DEFAULT;
    return Math.min(POS_CARD_SIZE_MAX, Math.max(POS_CARD_SIZE_MIN, parsed));
}

function applyProductCardSize(level) {
    const grid = document.getElementById('products-grid');
    if (!grid) return;
    const normalized = normalizeCardSizeLevel(level);
    const preset = getProductCardPreset(normalized);

    grid.style.setProperty('--pc-card-min', preset.cardMin);
    grid.style.setProperty('--pc-card-min-h', preset.cardMinH);
    grid.style.setProperty('--pc-gap', preset.gap);
    grid.style.setProperty('--pc-pad', preset.pad);
    grid.style.setProperty('--pc-title-size', preset.titleSize);
    grid.style.setProperty('--pc-title-lh', preset.titleLh);
    grid.style.setProperty('--pc-title-height', preset.titleHeight);
    grid.style.setProperty('--pc-media-height', preset.mediaHeight);
    grid.style.setProperty('--pc-cat-size', preset.catSize);
    grid.style.setProperty('--pc-price-size', preset.priceSize);
    grid.style.setProperty('--pc-add-size', preset.addSize);
    grid.style.setProperty('--pc-placeholder-size', preset.placeholderSize);
    grid.style.setProperty('--pc-image-scale', preset.imageScale);

    const slider = document.getElementById('card-size-slider');
    if (slider) slider.value = String(normalized);
    const label = document.getElementById('card-size-label');
    if (label) label.textContent = preset.pct + '%';

    localStorage.setItem(POS_CARD_SIZE_KEY, String(normalized));
}

function getProductCardSize() {
    return normalizeCardSizeLevel(localStorage.getItem(POS_CARD_SIZE_KEY) ?? POS_CARD_SIZE_DEFAULT);
}

function stepProductCardSize(direction) {
    const current = getProductCardSize();
    applyProductCardSize(current + direction);
}

function addProductToCartData(product) {
    if (activeDueInvoice) {
        alert('لا يمكن تعديل سلة تحصيل دين. قم بإفراغ السلة أولاً.');
        return;
    }
    const id = parseInt(product.id);
    const name = product.name;
    const price = parseFloat(product.price);
    const image = product.image_url || '';
    const maxQty = parseInt(product.quantity);

    const existing = cart.find(i => i.id === id);
    if (existing) {
        if (existing.qty < maxQty) existing.qty++;
    } else {
        cart.push({
            id, name, price, image, qty: 1, maxQty,
            discount: 0,
            discountType: 'value',
            discountInput: 0,
            showDiscount: false
        });
    }
    renderCart();
}

function addToCart(el) {
    if (activeDueInvoice) {
        alert('لا يمكن إضافة منتجات أثناء تحصيل فاتورة دين');
        return;
    }
    if (!el || el.dataset.outOfStock === '1' || parseInt(el.dataset.qty || '0', 10) <= 0) {
        return;
    }
    addProductToCartData({
        id: el.dataset.id,
        name: el.dataset.name,
        price: el.dataset.price,
        image_url: el.dataset.image || '',
        quantity: el.dataset.qty
    });
}

function removeFromCart(id) {
    if (activeDueInvoice) return;
    cart = cart.filter(i => i.id !== id);
    renderCart();
}

function updateQty(id, delta) {
    if (activeDueInvoice) return;
    const item = cart.find(i => i.id === id);
    if (item) {
        item.qty += delta;
        if (item.qty <= 0) { removeFromCart(id); return; }
        if (item.qty > item.maxQty) item.qty = item.maxQty;
        
        // Update discount amount if percentage type because total price changed
        if (item.discountType === 'percent') {
            const lineTotal = item.price * item.qty;
            item.discount = lineTotal * (Math.min(item.discountInput, 100) / 100);
        } else {
            // Keep value but cap it at max
            const lineTotal = item.price * item.qty;
            item.discount = Math.min(item.discountInput, lineTotal);
        }
    }
    renderCart();
}

function toggleItemDiscount(id) {
    const item = cart.find(i => i.id === id);
    if (!item) return;
    item.showDiscount = !item.showDiscount;
    renderCart();
}

function setItemDiscountDetails(id, type, value) {
    const item = cart.find(i => i.id === id);
    if (!item) return;
    
    // If type changed, update type
    if (type !== null) item.discountType = type;
    
    // If value changed, update input
    if (value !== null) item.discountInput = parseFloat(value) || 0;
    
    // Calculate actual discount amount
    const lineTotal = item.price * item.qty;
    if (item.discountType === 'percent') {
        item.discount = lineTotal * (Math.min(item.discountInput, 100) / 100);
    } else {
        item.discount = Math.min(item.discountInput, lineTotal);
    }
    
    renderCart();
}

function clearCart() {
    cart = [];
    document.getElementById('invoice-discount').value = 0;
    setSelectedCustomer(null);
    activeSuspendedOrder = null;
    activeDueInvoice = null;
    updateSuspendedBadge();
    renderDueInvoicePanel();
    updateInvoiceTotalsInReceipt();
    clearCartState();
    renderCart();
}

function setDiscountType(type) {
    if (activeDueInvoice) return;
    invoiceDiscountType = type;
    document.getElementById('disc-type-value').className = 'disc-type-btn px-3 h-full text-xs ' + (type === 'value' ? 'font-bold bg-green-600 text-white' : 'font-medium text-slate-500') + ' transition-colors';
    document.getElementById('disc-type-percent').className = 'disc-type-btn px-3 h-full text-xs ' + (type === 'percent' ? 'font-bold bg-green-600 text-white' : 'font-medium text-slate-500') + ' transition-colors';
    updateTotals();
}

function renderCart() {
    const listEl = document.getElementById('cart-list');
    const emptyMsg = document.getElementById('cart-empty');
    const payBtn = document.getElementById('pay-btn');
    const readOnlyDue = !!activeDueInvoice;
    
    if (cart.length === 0) {
        listEl.innerHTML = '';
        emptyMsg.style.display = 'flex';
        payBtn.disabled = true;
        if (!readOnlyDue && selectedCustomer) {
            setSelectedCustomer(null);
        }
    } else {
        emptyMsg.style.display = 'none';
        payBtn.disabled = false;
        listEl.innerHTML = cart.map(item => {
            const lineTotal = item.price * item.qty;
            const hasDisc = item.discount > 0;
            const finalPrice = lineTotal - item.discount;
            
            return `
            <div class="p-3 rounded-xl bg-slate-50 border border-transparent hover:border-slate-200 transition-colors mb-3">
                <div class="flex gap-3">
                    <div class="w-14 h-14 rounded-lg bg-white overflow-hidden flex-shrink-0 flex items-center justify-center">
                        ${item.image ? `<img class="w-full h-full object-cover" src="${item.image}" alt="">` : `<span class="material-icons-outlined text-slate-300 text-2xl">inventory_2</span>`}
                    </div>
                    <div class="flex-1 flex flex-col justify-between min-w-0">
                        <div>
                            <h4 class="font-bold text-slate-800 text-sm leading-tight mb-0.5 truncate">${item.name}</h4>
                            <p class="text-xs text-slate-500 font-num">${item.price.toLocaleString()} ${CURRENCY} / وحدة</p>
                        </div>
                        <div class="flex items-center justify-between mt-1.5">
                            <div class="flex items-center bg-white rounded-lg border border-slate-200 shadow-sm h-7">
                                <button onclick="${readOnlyDue ? 'return false;' : `updateQty(${item.id}, -1)`}" class="w-7 h-full flex items-center justify-center ${readOnlyDue ? 'text-slate-300 cursor-not-allowed' : 'text-slate-500 hover:text-primary'} rounded-r-lg transition-colors" ${readOnlyDue ? 'disabled' : ''}>
                                    <span class="material-icons-outlined text-sm">remove</span>
                                </button>
                                <span class="w-7 text-center text-xs font-num font-semibold text-slate-800">${item.qty}</span>
                                <button onclick="${readOnlyDue ? 'return false;' : `updateQty(${item.id}, 1)`}" class="w-7 h-full flex items-center justify-center ${readOnlyDue ? 'text-slate-300 bg-slate-100 cursor-not-allowed' : 'text-white bg-primary hover:bg-primary-dark'} rounded-l-lg transition-colors" ${readOnlyDue ? 'disabled' : ''}>
                                    <span class="material-icons-outlined text-sm">add</span>
                                </button>
                            </div>
                            <div class="flex items-center gap-2">
                                <button onclick="${readOnlyDue ? 'return false;' : `removeFromCart(${item.id})`}" class="p-1 rounded text-xs ${readOnlyDue ? 'text-slate-300 cursor-not-allowed' : 'text-red-500 hover:bg-red-50 hover:text-red-600'} transition-colors" title="حذف الصنف من السلة" ${readOnlyDue ? 'disabled' : ''}>
                                    <span class="material-icons-outlined text-sm">delete</span>
                                </button>
                                <button onclick="${readOnlyDue ? 'return false;' : `toggleItemDiscount(${item.id})`}" class="p-1 rounded text-xs ${readOnlyDue ? 'text-slate-300 cursor-not-allowed' : (hasDisc || item.showDiscount ? 'text-green-600 bg-green-50 font-bold' : 'text-slate-400 hover:text-green-600 hover:bg-green-50')} transition-colors" title="خصم على العنصر" ${readOnlyDue ? 'disabled' : ''}>
                                    <span class="material-icons-outlined text-sm">local_offer</span>
                                </button>
                                <div class="text-left leading-tight">
                                    ${hasDisc ? 
                                        `<span class="block text-[10px] text-slate-400 font-num line-through decoration-slate-400/50">${lineTotal.toLocaleString()}</span>
                                         <span class="font-num font-bold text-green-600 text-sm">${finalPrice.toLocaleString()}</span>` 
                                        : 
                                        `<span class="font-num font-bold text-slate-900 text-sm">${lineTotal.toLocaleString()}</span>`
                                    }
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Discount Panel -->
                <div class="${(!readOnlyDue && item.showDiscount) ? 'flex' : 'hidden'} items-center gap-2 mt-3 pt-3 border-t border-dashed border-slate-200 animate-fade-in">
                    <span class="text-[10px] font-medium text-slate-500">خصم:</span>
                    <div class="flex items-center bg-white border border-slate-200 rounded-lg overflow-hidden h-7">
                        <button onclick="setItemDiscountDetails(${item.id}, 'value', null)" class="px-2 h-full text-[10px] font-bold transition-colors ${item.discountType === 'value' ? 'bg-green-500 text-white' : 'text-slate-500 hover:bg-slate-50'}">قيمة</button>
                        <button onclick="setItemDiscountDetails(${item.id}, 'percent', null)" class="px-2 h-full text-[10px] font-bold transition-colors ${item.discountType === 'percent' ? 'bg-green-500 text-white' : 'text-slate-500 hover:bg-slate-50'}">نسبة %</button>
                    </div>
                    <div class="flex-1 relative">
                        <input type="number" value="${item.discountInput}" min="0" step="any" dir="ltr"
                            class="w-full h-7 text-center font-num text-xs bg-white border border-slate-200 rounded-lg focus:outline-none focus:border-green-500 text-slate-700 font-bold px-1"
                            oninput="setItemDiscountDetails(${item.id}, null, this.value)">
                        <span class="absolute right-8 top-1/2 -translate-y-1/2 text-[10px] text-slate-400 pointer-events-none pr-1">
                            ${item.discountType === 'value' ? CURRENCY : '%'}
                        </span>
                    </div>
                </div>
            </div>`;
        }).join('');
    }
    updateTotals();
}

// Restore persisted cart for this user (survives refresh/closing tab).
loadCartState();
setDiscountType(invoiceDiscountType);
updateReceiptCustomerDisplay();
renderCart();

function getCartTotals() {
    if (activeDueInvoice) {
        const due = Math.max(0, Number(activeDueInvoice.dueAmount || 0));
        return { subtotal: due, totalDiscount: 0, invoiceDisc: 0, tax: 0, total: due };
    }
    const subtotal = cart.reduce((sum, i) => sum + i.price * i.qty, 0);
    const itemDiscounts = cart.reduce((sum, i) => sum + i.discount, 0);
    const afterItemDisc = subtotal - itemDiscounts;
    // Invoice-level discount
    const discInput = parseFloat(document.getElementById('invoice-discount').value) || 0;
    let invoiceDisc = 0;
    if (invoiceDiscountType === 'percent') {
        invoiceDisc = afterItemDisc * (Math.min(discInput, 100) / 100);
    } else {
        invoiceDisc = Math.min(discInput, afterItemDisc);
    }
    const totalDiscount = itemDiscounts + invoiceDisc;
    const afterAllDisc = Math.max(0, subtotal - totalDiscount);
    const tax = afterAllDisc * TAX_RATE;
    const total = afterAllDisc + tax;
    return { subtotal, totalDiscount, invoiceDisc, tax, total };
}

function updateTotals() {
    const { subtotal, totalDiscount, tax, total } = getCartTotals();
    const dueMode = !!activeDueInvoice;
    
    document.getElementById('subtotal').textContent = subtotal.toLocaleString() + ' ' + CURRENCY;
    document.getElementById('tax-amount').textContent = tax.toLocaleString() + ' ' + CURRENCY;
    document.getElementById('total-amount').innerHTML = total.toLocaleString() + ' <span class="text-sm font-normal text-slate-500">' + CURRENCY + '</span>';
    document.getElementById('cart-count').textContent = cart.reduce((s, i) => s + i.qty, 0) + ' \u0639\u0646\u0627\u0635\u0631';
    const taxRow = document.getElementById('tax-row');
    if (taxRow) {
        taxRow.style.display = dueMode ? 'none' : 'flex';
    }
    
    // Discount display
    const discRow = document.getElementById('discount-row');
    if (!dueMode && totalDiscount > 0) {
        discRow.style.display = 'flex';
        document.getElementById('discount-amount').textContent = '-' + totalDiscount.toLocaleString() + ' ' + CURRENCY;
    } else {
        discRow.style.display = 'none';
    }
    const invoiceDiscInput = document.getElementById('invoice-discount');
    if (invoiceDiscInput) {
        invoiceDiscInput.disabled = dueMode;
        if (dueMode) invoiceDiscInput.value = '0';
    }
    const discTypeValue = document.getElementById('disc-type-value');
    const discTypePercent = document.getElementById('disc-type-percent');
    if (discTypeValue) discTypeValue.disabled = dueMode;
    if (discTypePercent) discTypePercent.disabled = dueMode;
    
    // Receipt
    document.getElementById('receipt-subtotal').textContent = subtotal.toLocaleString();
    document.getElementById('receipt-tax').textContent = tax.toLocaleString();
    document.getElementById('receipt-total').textContent = total.toLocaleString();
    document.getElementById('modal-total').textContent = total.toLocaleString();
    const subtotalWrap = document.getElementById('receipt-subtotal-wrap');
    if (subtotalWrap) {
        subtotalWrap.classList.toggle('hidden', dueMode);
    }
    
    const receiptItems = document.getElementById('receipt-items');
    if (cart.length === 0) {
        receiptItems.innerHTML = '<p class="text-xs text-slate-400 text-center py-4">\u0644\u0627 \u062a\u0648\u062c\u062f \u0639\u0646\u0627\u0635\u0631</p>';
    } else {
        receiptItems.innerHTML = cart.map(item => `
            <div class="grid grid-cols-4 gap-2 text-xs">
                <div class="col-span-2"><p class="font-bold">${item.name}</p>${item.discount > 0 ? '<p class="text-green-600" style="font-size:10px">\u062e\u0635\u0645: -' + item.discount.toLocaleString() + '</p>' : ''}</div>
                <div class="text-center font-num">${item.qty}</div>
                <div class="text-left font-num">${(item.price * item.qty - item.discount).toLocaleString()}</div>
            </div>
        `).join('');
    }
    if (dueMode) {
        const paidWrap = document.getElementById('receipt-paid-wrap');
        const paidEl = document.getElementById('receipt-paid');
        const dueEl = document.getElementById('receipt-due');
        const dueWrap = document.getElementById('receipt-due-wrap');
        if (paidWrap) paidWrap.classList.add('hidden');
        if (paidEl) paidEl.textContent = Number(activeDueInvoice.paidAmount || 0).toLocaleString();
        if (dueEl) dueEl.textContent = Number(activeDueInvoice.dueAmount || 0).toLocaleString();
        if (dueWrap) dueWrap.classList.remove('hidden');
    } else {
        const paidWrap = document.getElementById('receipt-paid-wrap');
        const dueWrap = document.getElementById('receipt-due-wrap');
        if (paidWrap) paidWrap.classList.add('hidden');
        if (dueWrap) dueWrap.classList.add('hidden');
    }
    renderDueInvoicePanel();
    updateInvoiceTotalsInReceipt();
    
    updateChange();
    saveCartState();
}

// Search (includes barcode matching)
document.getElementById('product-search').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.product-card').forEach(card => {
        const name = card.dataset.name.toLowerCase();
        const barcode = (card.dataset.barcode || '').toLowerCase();
        card.style.display = (name.includes(q) || barcode.includes(q)) ? '' : 'none';
    });
});

// Barcode Scanner Detection
(function() {
    let barcodeBuffer = '';
    let lastKeyTime = 0;
    const SCAN_THRESHOLD = 50; // ms between keypresses for scanner
    const SCAN_MIN_LENGTH = 4; // minimum barcode length

    async function lookupAndAddByBarcode(barcode) {
        try {
            const res = await fetch('api/products.php?search=' + encodeURIComponent(barcode));
            const data = await res.json();
            if (!data || !data.success || !Array.isArray(data.products)) return false;
            const p = data.products.find(x => (x.barcode || '') === barcode && parseInt(x.quantity || 0, 10) > 0);
            if (!p) return false;
            addProductToCartData(p);
            return true;
        } catch (err) {
            return false;
        }
    }

    document.addEventListener('keydown', async function(e) {
        // Ignore if user is typing in an input (except the search field)
        const active = document.activeElement;
        if (active && active.tagName === 'INPUT' && active.id !== 'product-search') return;
        if (active && active.tagName === 'TEXTAREA') return;
        if (e.ctrlKey || e.altKey || e.metaKey) return;

        const now = Date.now();
        if (now - lastKeyTime > 300) barcodeBuffer = ''; // Reset if too slow

        if (e.key === 'Enter' && barcodeBuffer.length >= SCAN_MIN_LENGTH) {
            e.preventDefault();
            // Find product by barcode
            const scanned = barcodeBuffer;
            const card = document.querySelector(`.product-card[data-barcode="${scanned}"]`);
            if (card) {
                addToCart(card);
                // Visual feedback
                card.classList.add('ring-2', 'ring-green-400');
                setTimeout(() => card.classList.remove('ring-2', 'ring-green-400'), 600);
            } else {
                const added = await lookupAndAddByBarcode(scanned);
                if (!added) {
                    alert('لم يتم العثور على منتج متاح بالباركود: ' + scanned);
                }
            }
            barcodeBuffer = '';
            document.getElementById('product-search').value = '';
            return;
        }

        if (e.key.length === 1) {
            if (now - lastKeyTime < SCAN_THRESHOLD) {
                barcodeBuffer += e.key;
            } else {
                barcodeBuffer = e.key;
            }
            lastKeyTime = now;
        }
    });
})();

// Category filter
function filterCategory(catId) {
    document.querySelectorAll('.cat-btn').forEach(btn => {
        if ((catId === 'all' && btn.dataset.category === 'all') || btn.dataset.category == catId) {
            btn.className = btn.className.replace(/bg-white text-slate-600 hover:bg-slate-50 border border-slate-200/, 'bg-primary text-white shadow-lg shadow-primary/30');
        } else {
            btn.className = btn.className.replace(/bg-primary text-white shadow-lg shadow-primary\/30/, 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200');
        }
    });
    
    document.querySelectorAll('.product-card').forEach(card => {
        if (catId === 'all' || card.dataset.category == catId) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}

// Payment
function proceedToPayment() {
    if (cart.length === 0) return;
    document.getElementById('payment-modal').classList.remove('hidden');
    selectedPaymentMethod = 'cash';
    setSettlementMode(activeDueInvoice ? 'debt' : 'full');
    document.getElementById('received-amount').value = '0';
    updateChange();
}

function closePayment() {
    document.getElementById('payment-modal').classList.add('hidden');
    closeCustomerPicker();
}

function selectPaymentMethod(method) {
    selectedPaymentMethod = method;
    document.querySelectorAll('.pay-method-btn').forEach(btn => {
        if (btn.dataset.method === method) {
            btn.className = 'pay-method-btn flex flex-col items-center justify-center p-4 rounded-xl border-2 border-primary bg-primary/5 text-primary transition-all duration-200';
        } else {
            btn.className = 'pay-method-btn flex flex-col items-center justify-center p-4 rounded-xl border border-slate-200 hover:border-primary/50 hover:bg-slate-50 text-slate-600 transition-all duration-200';
        }
    });
    if (method !== 'cash') {
        setExact();
    }
}

function numpad(val) {
    const input = document.getElementById('received-amount');
    if (val === 'back') {
        input.value = input.value.slice(0, -1) || '0';
    } else {
        if (input.value === '0' && val !== '.') input.value = '';
        input.value += val;
    }
    updateChange();
}

function addAmount(n) {
    const input = document.getElementById('received-amount');
    input.value = (parseFloat(input.value) || 0) + n;
    updateChange();
}

function setExact() {
    const { total } = getCartTotals();
    document.getElementById('received-amount').value = total.toFixed(2);
    updateChange();
}

function updateChange() {
    const { total } = getCartTotals();
    const received = parseFloat(document.getElementById('received-amount').value) || 0;
    const labelEl = document.getElementById('change-label');
    const amountEl = document.getElementById('change-amount');
    if (settlementMode === 'debt' && received < total) {
        if (labelEl) labelEl.textContent = 'المتبقي على العميل';
        if (amountEl) amountEl.textContent = (total - received).toFixed(2);
    } else {
        if (labelEl) labelEl.textContent = 'المتبقي للعميل';
        if (amountEl) amountEl.textContent = Math.max(0, received - total).toFixed(2);
    }
    updatePaymentPreview(total, received);
}

function updatePaymentPreview(total = null, received = null) {
    const totals = getCartTotals();
    const finalTotal = total === null ? totals.total : total;
    const receivedValue = received === null ? (parseFloat(document.getElementById('received-amount').value) || 0) : received;
    const paid = Math.min(finalTotal, Math.max(0, receivedValue));
    const due = Math.max(0, finalTotal - paid);

    const paidEl = document.getElementById('receipt-paid');
    const dueEl = document.getElementById('receipt-due');
    const dueWrap = document.getElementById('receipt-due-wrap');
    if (paidEl) paidEl.textContent = paid.toLocaleString();
    if (dueEl) dueEl.textContent = due.toLocaleString();
    if (dueWrap) {
        dueWrap.classList.toggle('hidden', !(settlementMode === 'debt' && due > 0));
    }
}

// Complete Sale
// Complete Sale
async function completeSale() {
    if (cart.length === 0) return;
    const { subtotal, totalDiscount, invoiceDisc, tax, total } = getCartTotals();
    const received = parseFloat(document.getElementById('received-amount').value) || 0;

    if (activeDueInvoice) {
        await collectDueInvoicePayment(total, received);
        return;
    }

    if (received < 0) {
        alert('المبلغ المستلم غير صالح');
        return;
    }

    const dueAmount = Math.max(0, total - received);
    if (settlementMode === 'full' && dueAmount > 0) {
        alert('في وضع السداد الكامل يجب أن يكون المبلغ المستلم مساويًا أو أكبر من الإجمالي');
        return;
    }
    if (settlementMode === 'debt' && dueAmount > 0 && !selectedCustomer) {
        alert('يجب اختيار عميل عند تسجيل دين');
        return;
    }
    
    try {
        const response = await fetch('api/orders.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'create',
                items: cart.map(i => ({ product_id: i.id, quantity: i.qty, unit_price: i.price, discount: i.discount || 0 })),
                payment_method: 'cash',
                settlement_mode: settlementMode,
                payment_received: received,
                discount: invoiceDisc,
                customer_id: selectedCustomer ? selectedCustomer.id : null,
                suspended_order_id: activeSuspendedOrder ? activeSuspendedOrder.id : null
            })
        });
        const data = await response.json();
        if (data.success) {
            // Print receipt before clearing cart
            printReceipt(data.order_number, new Date(), received, (typeof data.due_amount !== 'undefined' ? data.due_amount : dueAmount));
            
            document.getElementById('receipt-number').textContent = '#' + data.order_number;
            // Clear local cart state then force a page refresh to reload live stock/out-of-stock state.
            cart = [];
            document.getElementById('invoice-discount').value = 0;
            clearCartState();
            clearSelectedCustomer();
            activeSuspendedOrder = null;
            activeDueInvoice = null;
            updateSuspendedBadge();
            renderCart();
            closePayment();
            setTimeout(() => {
                window.location.reload();
            }, 300);
        } else {
            alert('خطأ: ' + (data.error || 'حدث خطأ غير متوقع'));
        }
    } catch (err) {
        alert('خطأ في الاتصال بالخادم');
    }
}

async function collectDueInvoicePayment(currentDue, received) {
    if (!activeDueInvoice || !activeDueInvoice.id) {
        alert('لا توجد فاتورة دين نشطة');
        return;
    }
    if (received <= 0) {
        alert('ادخل مبلغ سداد صالح');
        return;
    }
    if (settlementMode === 'full' && received + 0.00001 < currentDue) {
        alert('في وضع السداد الكامل يجب سداد كامل المتبقي');
        return;
    }
    try {
        const response = await fetch(DUE_INVOICE_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'collect',
                order_id: activeDueInvoice.id,
                amount: received,
                payment_method: 'cash'
            })
        });
        const data = await response.json();
        if (!data || !data.success) {
            alert('خطأ: ' + ((data && data.error) ? data.error : 'تعذر تنفيذ السداد'));
            return;
        }

        const collected = Number(data.collected_amount || 0);
        const afterDue = Number(data.after_due || 0);
        const beforePaid = Number(activeDueInvoice.paidAmount || 0);
        activeDueInvoice.paidAmount = beforePaid + collected;
        activeDueInvoice.dueAmount = afterDue;
        if (!Array.isArray(activeDueInvoice.payments)) activeDueInvoice.payments = [];
        if (data.payment_row) {
            activeDueInvoice.payments.unshift(data.payment_row);
        }

        document.getElementById('receipt-number').textContent = '#' + (data.order_number || activeDueInvoice.orderNumber || '---');
        printReceipt(data.order_number || activeDueInvoice.orderNumber, new Date(), collected, afterDue);

        if (afterDue <= 0.00001) {
            alert('تم سداد الفاتورة بالكامل');
            clearCart();
            closePayment();
        } else {
            alert('تم تسجيل السداد بنجاح. المتبقي: ' + afterDue.toLocaleString());
            setSettlementMode('debt');
            document.getElementById('received-amount').value = '0';
            updateSuspendedBadge();
            updateTotals();
        }
    } catch (_) {
        alert('تعذر الاتصال بالخادم');
    }
}

function updateSuspendedBadge() {
    const badge = document.getElementById('suspended-order-badge');
    if (!badge) return;
    if (activeDueInvoice && activeDueInvoice.orderNumber) {
        badge.textContent = 'فاتورة ' + activeDueInvoice.orderNumber + ' | الإجمالي: ' + Number(activeDueInvoice.totalAmount || 0).toLocaleString() + ' | المدفوع: ' + Number(activeDueInvoice.paidAmount || 0).toLocaleString() + ' | المتبقي: ' + Number(activeDueInvoice.dueAmount || 0).toLocaleString();
        badge.classList.remove('hidden');
    } else if (activeSuspendedOrder && activeSuspendedOrder.number) {
        badge.textContent = 'طلب محفوظ: ' + activeSuspendedOrder.number;
        badge.classList.remove('hidden');
    } else {
        badge.textContent = '';
        badge.classList.add('hidden');
    }
}

function getPaymentMethodArUi(method) {
    const m = String(method || '').toLowerCase();
    if (m === 'cash') return 'نقدي';
    if (m === 'card') return 'بطاقة';
    if (m === 'transfer') return 'تحويل';
    return m || '—';
}

function renderDueInvoicePanel() {
    const panel = document.getElementById('due-invoice-panel');
    const totalEl = document.getElementById('due-total-amount');
    const paidEl = document.getElementById('due-paid-amount');
    const remEl = document.getElementById('due-remaining-amount');
    const listEl = document.getElementById('due-payments-list');
    if (!panel || !totalEl || !paidEl || !remEl || !listEl) return;

    if (!activeDueInvoice) {
        panel.classList.add('hidden');
        return;
    }

    totalEl.textContent = Number(activeDueInvoice.totalAmount || 0).toLocaleString();
    paidEl.textContent = Number(activeDueInvoice.paidAmount || 0).toLocaleString();
    remEl.textContent = Number(activeDueInvoice.dueAmount || 0).toLocaleString();

    const payments = Array.isArray(activeDueInvoice.payments) ? activeDueInvoice.payments : [];
    if (payments.length === 0) {
        listEl.innerHTML = '<p class="p-2 text-[11px] text-slate-400 text-center">لا توجد مدفوعات مسجلة</p>';
    } else {
        listEl.innerHTML = payments.map((p) => `
            <div class="px-2 py-1.5 flex items-center justify-between text-[11px]">
                <div>
                    <p class="text-slate-700">${escapeHtml(getPaymentMethodArUi(p.payment_method))} - <span class="font-num">${escapeHtml(String(p.created_at || ''))}</span></p>
                    <p class="text-slate-400">${escapeHtml(String(p.created_by_name || 'System'))}</p>
                </div>
                <p class="font-num font-bold text-emerald-700">${Number(p.amount || 0).toLocaleString()}</p>
            </div>
        `).join('');
    }
    panel.classList.remove('hidden');
}

function updateInvoiceTotalsInReceipt() {
    const wrap = document.getElementById('receipt-invoice-totals');
    const totalEl = document.getElementById('receipt-invoice-total');
    const paidEl = document.getElementById('receipt-invoice-paid-total');
    const remainingEl = document.getElementById('receipt-invoice-remaining-total');
    if (!wrap || !totalEl || !paidEl || !remainingEl) return;

    if (!activeDueInvoice) {
        wrap.classList.add('hidden');
        return;
    }

    totalEl.textContent = Number(activeDueInvoice.totalAmount || 0).toLocaleString();
    paidEl.textContent = Number(activeDueInvoice.paidAmount || 0).toLocaleString();
    remainingEl.textContent = Number(activeDueInvoice.dueAmount || 0).toLocaleString();
    wrap.classList.remove('hidden');
}

function printDuePaymentsStatement() {
    if (!activeDueInvoice || !activeDueInvoice.id) {
        alert('لا توجد فاتورة دين نشطة');
        return;
    }
    const customerName = selectedCustomer && selectedCustomer.name ? selectedCustomer.name : '—';
    const customerPhone = selectedCustomer && selectedCustomer.phone ? selectedCustomer.phone : '';
    const now = new Date();
    const dateStr = now.toLocaleDateString('ar-EG', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    const timeStr = now.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });
    const payments = Array.isArray(activeDueInvoice.payments) ? activeDueInvoice.payments : [];

    const rows = payments.length > 0
        ? payments.map((p, idx) => `
            <tr style="border-bottom:1px solid #eee">
                <td style="padding:8px 6px;text-align:center">${idx + 1}</td>
                <td style="padding:8px 6px;text-align:center;font-family:'Cairo'">${escapeHtml(getPaymentMethodArUi(p.payment_method))}</td>
                <td style="padding:8px 6px;text-align:center;font-family:'Cairo'">${escapeHtml(String(p.created_at || ''))}</td>
                <td style="padding:8px 6px;text-align:center">${escapeHtml(String(p.created_by_name || 'System'))}</td>
                <td style="padding:8px 6px;text-align:left;font-family:'Cairo';font-weight:bold">${Number(p.amount || 0).toLocaleString()} <?= CURRENCY ?></td>
            </tr>
        `).join('')
        : `<tr><td colspan="5" style="padding:12px;text-align:center;color:#888">لا توجد مدفوعات مسجلة</td></tr>`;

    const html = `
    <div style="font-family:'Cairo',sans-serif;direction:rtl;color:#000;padding:20px;font-size:13px;width:100%;max-width:210mm;margin:0 auto">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border-bottom:2px solid #333;padding-bottom:12px">
            <div>
                <h2 style="margin:0 0 4px 0;font-size:22px;font-weight:800">كشف مدفوعات الفاتورة</h2>
                <p style="margin:2px 0;color:#555">رقم الفاتورة: <span style="font-weight:bold">${escapeHtml(activeDueInvoice.orderNumber || '')}</span></p>
                <p style="margin:2px 0;color:#555">العميل: ${escapeHtml(customerName)} ${customerPhone ? (' - ' + escapeHtml(customerPhone)) : ''}</p>
            </div>
            <div style="text-align:left;color:#666">
                <p style="margin:2px 0">${dateStr}</p>
                <p style="margin:2px 0">${timeStr}</p>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:18px">
            <div style="border:1px solid #ddd;border-radius:8px;padding:8px">
                <p style="margin:0;color:#64748b;font-size:12px">إجمالي الفاتورة</p>
                <p style="margin:4px 0 0 0;font-size:16px;font-weight:800;font-family:'Cairo'">${Number(activeDueInvoice.totalAmount || 0).toLocaleString()} <?= CURRENCY ?></p>
            </div>
            <div style="border:1px solid #ddd;border-radius:8px;padding:8px">
                <p style="margin:0;color:#64748b;font-size:12px">إجمالي المدفوع</p>
                <p style="margin:4px 0 0 0;font-size:16px;font-weight:800;color:#047857;font-family:'Cairo'">${Number(activeDueInvoice.paidAmount || 0).toLocaleString()} <?= CURRENCY ?></p>
            </div>
            <div style="border:1px solid #ddd;border-radius:8px;padding:8px">
                <p style="margin:0;color:#64748b;font-size:12px">إجمالي المتبقي</p>
                <p style="margin:4px 0 0 0;font-size:16px;font-weight:800;color:#b45309;font-family:'Cairo'">${Number(activeDueInvoice.dueAmount || 0).toLocaleString()} <?= CURRENCY ?></p>
            </div>
        </div>

        <table style="width:100%;border-collapse:collapse">
            <thead>
                <tr style="background:#f8fafc;border-bottom:1px solid #ddd">
                    <th style="padding:8px 6px;text-align:center">#</th>
                    <th style="padding:8px 6px;text-align:center">طريقة الدفع</th>
                    <th style="padding:8px 6px;text-align:center">التاريخ</th>
                    <th style="padding:8px 6px;text-align:center">بواسطة</th>
                    <th style="padding:8px 6px;text-align:left">المبلغ</th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
        </table>
    </div>`;

    localStorage.setItem('pos_print_data', JSON.stringify({ html }));
    const width = 900;
    const height = 900;
    const left = (screen.width - width) / 2;
    const top = (screen.height - height) / 2;
    window.open('print_receipt.php', 'PrintReceipt', `width=${width},height=${height},top=${top},left=${left},scrollbars=yes`);
}

function holdOrder() {
    openSuspendedOrdersModal();
}

function openSuspendedOrdersModal() {
    const modal = document.getElementById('suspended-orders-modal');
    if (modal) modal.classList.remove('hidden');
    const suspendedInput = document.getElementById('suspended-search');
    const dueInput = document.getElementById('due-invoice-search');
    if (suspendedInput) suspendedInput.value = '';
    if (dueInput) dueInput.value = '';
    setTimeout(() => suspendedInput?.focus(), 40);
    searchSuspendedOrders('');
    searchDueInvoices('');
}

function closeSuspendedOrdersModal() {
    const modal = document.getElementById('suspended-orders-modal');
    if (modal) modal.classList.add('hidden');
}

async function saveCurrentAsSuspended() {
    if (activeDueInvoice) {
        alert('لا يمكن حفظ سلة تحصيل دين كطلب معلق');
        return;
    }
    if (cart.length === 0) {
        alert('لا يوجد عناصر لحفظها');
        return;
    }
    const note = (document.getElementById('suspended-note')?.value || '').trim();
    const totals = getCartTotals();
    const state = {
        cart: cart,
        invoiceDiscountType: invoiceDiscountType,
        invoiceDiscountValue: parseFloat(document.getElementById('invoice-discount')?.value || 0),
        selectedCustomer: selectedCustomer,
        settlementMode: settlementMode
    };

    try {
        const res = await fetch(SUSPENDED_ORDER_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'create',
                state: state,
                total: totals.total,
                notes: note
            })
        });
        const data = await res.json();
        if (!data || !data.success) {
            alert((data && data.error) ? data.error : 'تعذر حفظ الطلب');
            return;
        }
        activeSuspendedOrder = { id: parseInt(data.id, 10), number: String(data.suspend_number || '') };
        updateSuspendedBadge();
        const noteEl = document.getElementById('suspended-note');
        if (noteEl) noteEl.value = '';
        alert('تم حفظ الطلب برقم: ' + activeSuspendedOrder.number);
        searchSuspendedOrders('');
    } catch (_) {
        alert('تعذر الاتصال بالخادم');
    }
}

async function searchSuspendedOrders(query) {
    const box = document.getElementById('suspended-search-results');
    if (!box) return;
    const q = String(query || '').trim();
    try {
        const res = await fetch(SUSPENDED_ORDER_API_URL + '?q=' + encodeURIComponent(q));
        const data = await res.json();
        if (!data || !data.success || !Array.isArray(data.orders) || data.orders.length === 0) {
            box.innerHTML = '<p class="p-3 text-xs text-slate-400 text-center">لا توجد طلبات مطابقة</p>';
            return;
        }
        box.innerHTML = data.orders.map((o, idx) => `
            <div class="p-3 hover:bg-slate-50 transition-colors">
                <div class="flex items-center justify-between gap-2">
                    <button type="button" class="text-right flex-1" data-role="load-suspended" data-row-idx="${idx}">
                        <p class="text-xs font-semibold text-slate-800 font-num">${escapeHtml(o.suspend_number || '')}</p>
                        <p class="text-[11px] text-slate-500">${escapeHtml(o.customer_name || 'عميل نقدي')} ${o.customer_phone ? (' - ' + escapeHtml(o.customer_phone)) : ''}</p>
                    </button>
                    <button type="button" class="p-1.5 rounded-lg text-red-500 hover:bg-red-50" data-role="delete-suspended" data-row-idx="${idx}" title="حذف الطلب المحفوظ">
                        <span class="material-icons-outlined text-[18px]">delete</span>
                    </button>
                </div>
                <div class="flex items-center justify-between mt-1">
                    <p class="text-[10px] text-slate-400">${escapeHtml(o.created_at || '')}</p>
                    <p class="text-xs font-num font-bold text-primary">${Number(o.total || 0).toLocaleString()}</p>
                </div>
            </div>
        `).join('');
        Array.from(box.querySelectorAll('button[data-role="load-suspended"]')).forEach((btn, idx) => {
            btn.onclick = () => loadSuspendedOrder(data.orders[idx].id);
        });
        Array.from(box.querySelectorAll('button[data-role="delete-suspended"]')).forEach((btn, idx) => {
            btn.onclick = () => cancelSuspendedOrder(data.orders[idx].id, data.orders[idx].suspend_number);
        });
    } catch (_) {
        box.innerHTML = '<p class="p-3 text-xs text-red-500 text-center">تعذر تحميل البيانات</p>';
    }
}

async function cancelSuspendedOrder(id, number) {
    const orderId = parseInt(id, 10);
    if (!orderId) return;
    if (!confirm('حذف الطلب المحفوظ ' + (number || '') + ' ؟')) return;
    try {
        const res = await fetch(SUSPENDED_ORDER_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'cancel', id: orderId })
        });
        const data = await res.json();
        if (!data || !data.success) {
            alert((data && data.error) ? data.error : 'تعذر حذف الطلب');
            return;
        }
        if (activeSuspendedOrder && activeSuspendedOrder.id === orderId) {
            activeSuspendedOrder = null;
            updateSuspendedBadge();
        }
        searchSuspendedOrders(document.getElementById('suspended-search')?.value || '');
    } catch (_) {
        alert('تعذر الاتصال بالخادم');
    }
}

async function searchDueInvoices(query) {
    const box = document.getElementById('due-invoice-results');
    if (!box) return;
    const q = String(query || '').trim();
    try {
        const res = await fetch(DUE_INVOICE_API_URL + '?q=' + encodeURIComponent(q));
        const data = await res.json();
        if (!data || !data.success || !Array.isArray(data.orders) || data.orders.length === 0) {
            box.innerHTML = '<p class="p-3 text-xs text-slate-400 text-center">لا توجد فواتير دين مطابقة</p>';
            return;
        }
        box.innerHTML = data.orders.map((o, idx) => `
            <button type="button" class="w-full text-right p-3 hover:bg-slate-50 transition-colors" data-row-idx="${idx}">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <p class="text-xs font-semibold text-slate-800 font-num">${escapeHtml(o.order_number || '')}</p>
                        <p class="text-[11px] text-slate-500">${escapeHtml(o.customer_name || '—')} ${o.customer_phone ? (' - ' + escapeHtml(o.customer_phone)) : ''}</p>
                    </div>
                    <div class="text-left">
                        <p class="text-[11px] text-slate-500">الإجمالي: <span class="font-num font-semibold text-slate-700">${Number(o.total || 0).toLocaleString()}</span></p>
                        <p class="text-[11px] text-slate-500">مدفوع: <span class="font-num font-semibold text-emerald-700">${Number(o.payment_received || 0).toLocaleString()}</span></p>
                        <p class="text-[11px] text-amber-700">متبقي: <span class="font-num font-bold">${Number(o.due_amount || 0).toLocaleString()}</span></p>
                    </div>
                </div>
            </button>
        `).join('');
        Array.from(box.querySelectorAll('button')).forEach((btn, idx) => {
            btn.onclick = () => loadDueInvoice(data.orders[idx].id);
        });
    } catch (_) {
        box.innerHTML = '<p class="p-3 text-xs text-red-500 text-center">تعذر تحميل البيانات</p>';
    }
}

async function loadSuspendedOrder(id) {
    const orderId = parseInt(id, 10);
    if (!orderId) return;
    if (cart.length > 0 && !confirm('سيتم استبدال السلة الحالية بالطلب المحفوظ. متابعة؟')) return;

    try {
        const res = await fetch(SUSPENDED_ORDER_API_URL + '?id=' + encodeURIComponent(orderId));
        const data = await res.json();
        if (!data || !data.success || !data.state) {
            alert((data && data.error) ? data.error : 'تعذر استدعاء الطلب');
            return;
        }
        const state = data.state;
        if (!Array.isArray(state.cart) || state.cart.length === 0) {
            alert('هذا الطلب لا يحتوي عناصر');
            return;
        }

        cart = state.cart.map(i => ({
            id: parseInt(i.id, 10),
            name: String(i.name || ''),
            price: parseFloat(i.price || 0),
            image: String(i.image || ''),
            qty: Math.max(1, parseInt(i.qty || 1, 10)),
            maxQty: Math.max(1, parseInt(i.maxQty || i.qty || 1, 10)),
            discount: Math.max(0, parseFloat(i.discount || 0)),
            discountType: i.discountType === 'percent' ? 'percent' : 'value',
            discountInput: Math.max(0, parseFloat(i.discountInput || 0)),
            showDiscount: !!i.showDiscount
        })).filter(i => i.id > 0 && i.qty > 0);

        invoiceDiscountType = (state.invoiceDiscountType === 'percent') ? 'percent' : 'value';
        setDiscountType(invoiceDiscountType);
        const invoiceInput = document.getElementById('invoice-discount');
        if (invoiceInput) invoiceInput.value = String(Math.max(0, parseFloat(state.invoiceDiscountValue || 0)));

        if (state.selectedCustomer && state.selectedCustomer.id) {
            setSelectedCustomer(state.selectedCustomer);
        } else {
            clearSelectedCustomer();
        }

        setSettlementMode(state.settlementMode === 'debt' ? 'debt' : 'full');
        activeSuspendedOrder = {
            id: parseInt(data.order.id, 10),
            number: String(data.order.suspend_number || '')
        };
        activeDueInvoice = null;
        updateSuspendedBadge();
        renderCart();
        closeSuspendedOrdersModal();
        alert('تم استدعاء الطلب: ' + activeSuspendedOrder.number);
    } catch (_) {
        alert('تعذر الاتصال بالخادم');
    }
}

async function loadDueInvoice(id) {
    const invoiceId = parseInt(id, 10);
    if (!invoiceId) return;
    if (cart.length > 0 && !confirm('سيتم استبدال السلة الحالية بالفاتورة المطلوبة. متابعة؟')) return;

    try {
        const res = await fetch(DUE_INVOICE_API_URL + '?id=' + encodeURIComponent(invoiceId));
        const data = await res.json();
        if (!data || !data.success || !data.order || !Array.isArray(data.items)) {
            alert((data && data.error) ? data.error : 'تعذر استدعاء الفاتورة');
            return;
        }
        const order = data.order;
        const due = Number(order.due_amount || 0);
        if (due <= 0) {
            alert('هذه الفاتورة مسددة بالكامل');
            return;
        }
        cart = data.items.map((i) => ({
            id: parseInt(i.product_id || i.id || 0, 10),
            name: String(i.product_name || ''),
            price: parseFloat(i.unit_price || 0),
            image: '',
            qty: Math.max(1, parseInt(i.quantity || 1, 10)),
            maxQty: Math.max(1, parseInt(i.quantity || 1, 10)),
            discount: parseFloat(i.discount || 0),
            discountType: 'value',
            discountInput: parseFloat(i.discount || 0),
            showDiscount: false
        })).filter(i => i.id > 0 || i.name !== '');

        const paid = Math.min(Number(order.total || 0), Number(order.payment_received || 0));
        const customerId = parseInt(order.customer_id || 0, 10);
        if (customerId > 0) {
            setSelectedCustomer({
                id: customerId,
                name: String(order.customer_name || ''),
                phone: String(order.customer_phone || '')
            });
        } else {
            clearSelectedCustomer();
        }
        document.getElementById('invoice-discount').value = '0';
        setDiscountType('value');
        activeDueInvoice = {
            id: parseInt(order.id, 10),
            orderNumber: String(order.order_number || ''),
            totalAmount: Number(order.total || 0),
            paidAmount: paid,
            dueAmount: due,
            payments: Array.isArray(data.payments) ? data.payments : []
        };
        activeSuspendedOrder = null;
        setSettlementMode('debt');
        updateSuspendedBadge();
        renderCart();
        closeSuspendedOrdersModal();
        alert('تم استدعاء فاتورة الدين: ' + activeDueInvoice.orderNumber);
    } catch (_) {
        alert('تعذر الاتصال بالخادم');
    }
}

async function requestCycleClose() {
    const note = prompt('ملاحظة الإقفال (اختياري):', '') || '';
    const csrf = document.getElementById('pos-csrf-token')?.value || '';
    try {
        const body = new URLSearchParams();
        body.append('csrf_token', csrf);
        body.append('action', 'close_request');
        body.append('note', note);
        const res = await fetch('api/cashier-cycle.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body: body.toString()
        });
        const data = await res.json();
        if (data && data.success) {
            alert(data.message || 'تم إرسال الطلب بنجاح');
        } else {
            alert((data && data.error) ? data.error : 'تعذر تنفيذ الطلب');
        }
    } catch (e) {
        alert('تعذر الاتصال بالخادم');
    }
}

function isPaymentModalOpen() {
    return !document.getElementById('payment-modal').classList.contains('hidden');
}

function isTypingContext() {
    const active = document.activeElement;
    if (!active) return false;
    const tag = active.tagName;
    return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || active.isContentEditable;
}

// Keyboard shortcuts:
// F2 = focus product search
// F4 = open payment
// F8 = clear cart
// Ctrl+Enter = complete sale (inside payment modal)
document.addEventListener('keydown', (e) => {
    if (e.key === 'F2') {
        e.preventDefault();
        const search = document.getElementById('product-search');
        if (search) {
            search.focus();
            search.select();
        }
        return;
    }

    if (e.key === 'F4') {
        e.preventDefault();
        if (!isPaymentModalOpen()) proceedToPayment();
        return;
    }

    if (e.key === 'F8') {
        e.preventDefault();
        if (cart.length > 0 && confirm('هل تريد إفراغ السلة؟')) {
            clearCart();
        }
        return;
    }

    if (e.ctrlKey && e.key === 'Enter') {
        if (isPaymentModalOpen()) {
            e.preventDefault();
            completeSale();
        }
    }
});

// Product card size controls (per-user in this browser)
(() => {
    applyProductCardSize(getProductCardSize());
    document.getElementById('card-size-dec')?.addEventListener('click', () => stepProductCardSize(-1));
    document.getElementById('card-size-inc')?.addEventListener('click', () => stepProductCardSize(1));
    document.getElementById('card-size-reset')?.addEventListener('click', () => applyProductCardSize(POS_CARD_SIZE_DEFAULT));
    document.getElementById('card-size-slider')?.addEventListener('input', (e) => applyProductCardSize(e.target.value));
})();

(() => {
    const search = document.getElementById('customer-search');
    const results = document.getElementById('customer-results');
    const modal = document.getElementById('customer-picker-modal');
    if (search) {
        search.addEventListener('input', () => {
            clearTimeout(customerSearchTimer);
            customerSearchTimer = setTimeout(() => searchCustomers(search.value), 220);
        });
        search.addEventListener('focus', () => {
            if (search.value.trim() !== '') searchCustomers(search.value);
        });
    }
    document.addEventListener('click', (e) => {
        if (!results) return;
        const target = e.target;
        if (target === search || results.contains(target)) return;
        results.classList.add('hidden');
    });
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeCustomerPicker();
        });
    }
})();

(() => {
    const modal = document.getElementById('suspended-orders-modal');
    const suspendedSearch = document.getElementById('suspended-search');
    const dueSearch = document.getElementById('due-invoice-search');
    if (suspendedSearch) {
        let timer = null;
        suspendedSearch.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => searchSuspendedOrders(suspendedSearch.value), 220);
        });
    }
    if (dueSearch) {
        let timer2 = null;
        dueSearch.addEventListener('input', () => {
            clearTimeout(timer2);
            timer2 = setTimeout(() => searchDueInvoices(dueSearch.value), 220);
        });
    }
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeSuspendedOrdersModal();
        });
    }
})();

function printReceipt(orderNumber = null, orderDate = null, paymentReceived = null, dueAmount = null) {
    if (cart.length === 0) { alert('السلة فارغة'); return; }
    
    const { subtotal, totalDiscount, invoiceDisc, tax, total } = getCartTotals();
    const now = orderDate || new Date();
    const receivedAmount = paymentReceived === null
        ? (parseFloat(document.getElementById('received-amount').value) || 0)
        : (parseFloat(paymentReceived) || 0);
    const paidAmount = Math.min(total, Math.max(0, receivedAmount));
    const remainingDue = dueAmount === null ? Math.max(0, total - paidAmount) : Math.max(0, parseFloat(dueAmount) || 0);
    const invoiceActualTotal = activeDueInvoice ? Number(activeDueInvoice.totalAmount || 0) : total;
    const invoicePaidTotal = activeDueInvoice ? Number(activeDueInvoice.paidAmount || 0) : Math.min(invoiceActualTotal, Math.max(0, receivedAmount));
    const invoiceRemainingTotal = activeDueInvoice ? Number(activeDueInvoice.dueAmount || 0) : Math.max(0, invoiceActualTotal - invoicePaidTotal);
    const isDueInvoice = !!activeDueInvoice;
    const customerName = selectedCustomer && selectedCustomer.name ? selectedCustomer.name : 'عميل نقدي';
    const customerPhone = selectedCustomer && selectedCustomer.phone ? selectedCustomer.phone : '';
    const dateStr = now.toLocaleDateString('ar-EG', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    const hijriDateStr = SHOW_HIJRI_DATES
        ? (now.toLocaleDateString('ar-SA-u-ca-islamic', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) + ' هـ')
        : '';
    const timeStr = now.toLocaleTimeString('ar-EG', {hour:'2-digit',minute:'2-digit'});
    
    let itemsHtml = cart.map(item => {
        const lineTotal = item.price * item.qty;
        const finalPrice = lineTotal - item.discount;
        return `
        <tr>
            <td style="padding:5px 0;border-bottom:1px dashed #eee">
                <span style="font-weight:bold;display:block">${item.name}</span>
                <div style="display:flex;justify-content:space-between;font-size:11px;color:#666">
                    <span>${item.qty} x ${item.price.toLocaleString()}</span>
                    <span>${lineTotal.toLocaleString()}</span>
                </div>
                ${item.discount > 0 ? `
                <div style="display:flex;justify-content:space-between;font-size:10px;color:#c00">
                    <span>خصم</span>
                    <span>-${item.discount.toLocaleString()}</span>
                </div>` : ''}
            </td>
            <td style="padding:5px 0;border-bottom:1px dashed #eee;text-align:left;vertical-align:bottom;font-weight:bold">
                ${finalPrice.toLocaleString()}
            </td>
        </tr>`;
    }).join('');
    
    // Use receipt-preview style for normal invoices (compact layout only)
    const printType = 'thermal';
    let html = '';

    if (printType === 'a4') {
        html = `
        <div style="font-family:'Cairo',sans-serif;direction:rtl;color:#000;padding:20px;font-size:14px;width:100%;max-width:210mm;margin:0 auto">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:30px;border-bottom:2px solid #333;padding-bottom:20px">
                <div>
                    <?php if (!empty($settings['store_logo_url'])): ?>
                    <img src="<?= sanitize($settings['store_logo_url']) ?>" style="max-height:80px;display:block">
                    <?php else: ?>
                    <div style="width:50px;height:50px;background:#000;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;margin-bottom:10px"><span class="material-icons-outlined" style="font-size:30px">computer</span></div>
                    <?php endif; ?>
                    <h1 style="margin:5px 0;font-size:24px;font-weight:800"><?= sanitize($settings['store_name']) ?></h1>
                </div>
                <div style="text-align:left">
                    <h2 style="font-size:28px;color:#999;margin:0">فاتورة مبيعات</h2>
                    <p style="margin:5px 0;font-weight:bold">رقم: ${orderNumber ? '#' + orderNumber : '---'}</p>
                    <p style="margin:5px 0">${dateStr} - ${timeStr}</p>
                    ${SHOW_HIJRI_DATES ? `<p style="margin:0;color:#666">${hijriDateStr}</p>` : ``}
                </div>
            </div>

            <div style="display:grid;grid-template-cols: 1fr 1fr; gap: 40px; margin-bottom: 30px">
                <div>
                    <h3 style="border-bottom:1px solid #eee;padding-bottom:5px;margin-bottom:10px;font-size:16px">بيانات المتجر</h3>
                    <?php if(!empty($settings['store_address'])): ?><p style="margin:3px 0;color:#555">${itemLabel = 'العنوان'}: <?= sanitize($settings['store_address']) ?></p><?php endif; ?>
                    <?php if(!empty($settings['store_phone'])): ?><p style="margin:3px 0;color:#555;direction:ltr;text-align:right">${itemLabel = 'التليفون'}: <?= sanitize($settings['store_phone']) ?></p><?php endif; ?>
                    <p style="margin:3px 0;color:#555">الكاشير: <?= sanitize($user['full_name']) ?></p>
                    <p style="margin:3px 0;color:#555">العميل: ${escapeHtml(customerName)}</p>
                    ${customerPhone ? `<p style="margin:3px 0;color:#555;direction:ltr;text-align:right">هاتف العميل: ${escapeHtml(customerPhone)}</p>` : ``}
                </div>
                <div style="text-align:left">
                    <!-- Placeholder for client data if added later -->
                </div>
            </div>

            <table style="width:100%;border-collapse:collapse;margin-bottom:30px">
                <thead>
                    <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0">
                        <th style="padding:12px 10px;text-align:right">الصنف</th>
                        <th style="padding:12px 10px;text-align:center">الكمية</th>
                        <th style="padding:12px 10px;text-align:center">السعر</th>
                        <th style="padding:12px 10px;text-align:center">الخصم</th>
                        <th style="padding:12px 10px;text-align:left">الإجمالي</th>
                    </tr>
                </thead>
                <tbody>
                    ${cart.map(item => `
                    <tr style="border-bottom:1px solid #f1f5f9">
                        <td style="padding:12px 10px;font-weight:bold">${item.name}</td>
                        <td style="padding:12px 10px;text-align:center;font-family:'Cairo'">${item.qty}</td>
                        <td style="padding:12px 10px;text-align:center;font-family:'Cairo'">${item.price.toLocaleString()}</td>
                        <td style="padding:12px 10px;text-align:center;font-family:'Cairo';color:#c00">${item.discount > 0 ? '-' + item.discount.toLocaleString() : '0'}</td>
                        <td style="padding:12px 10px;text-align:left;font-weight:bold;font-family:'Cairo'">${((item.price * item.qty) - item.discount).toLocaleString()}</td>
                    </tr>`).join('')}
                </tbody>
            </table>

            <div style="display:flex;justify-content:flex-end">
                <div style="width:300px">
                    ${!isDueInvoice ? `
                    <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9">
                        <span style="color:#64748b">المجموع الفرعي:</span>
                        <span style="font-weight:bold">${subtotal.toLocaleString()} <?= CURRENCY ?></span>
                    </div>` : ``}
                    ${totalDiscount > 0 ? `
                    <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;color:#c00">
                        <span>إجمالي الخصم:</span>
                        <span>-${totalDiscount.toLocaleString()} <?= CURRENCY ?></span>
                    </div>` : ''}
                    ${isDueInvoice ? `
                    ${remainingDue > 0 ? `
                    <div style="display:flex;justify-content:space-between;padding:6px 0;color:#b45309">
                        <span>المتبقي دين:</span>
                        <span style="font-weight:bold">${remainingDue.toLocaleString()} <?= CURRENCY ?></span>
                    </div>` : ``}
                    <div style="display:flex;justify-content:space-between;padding:6px 0;color:#475569;border-top:1px dashed #e2e8f0;margin-top:6px">
                        <span>إجمالي الفاتورة الفعلي:</span>
                        <span style="font-weight:bold">${invoiceActualTotal.toLocaleString()} <?= CURRENCY ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:6px 0;color:#047857">
                        <span>إجمالي المدفوع:</span>
                        <span style="font-weight:bold">${invoicePaidTotal.toLocaleString()} <?= CURRENCY ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:6px 0;color:#b45309">
                        <span>إجمالي المتبقي:</span>
                        <span style="font-weight:bold">${invoiceRemainingTotal.toLocaleString()} <?= CURRENCY ?></span>
                    </div>
                    ` : ``}
                    <div style="display:flex;justify-content:space-between;padding:15px 0;margin-top:5px;border-top:1px dashed #e2e8f0">
                        <span style="font-size:20px;font-weight:800;color:primary">الإجمالي النهائي:</span>
                        <span style="font-size:24px;font-weight:800;color:primary">${total.toLocaleString()} <?= CURRENCY ?></span>
                    </div>
                </div>
            </div>

            <div style="margin-top:60px;padding-top:20px;border-top:1px solid #eee;text-align:center">
                <p style="font-size:14px;font-weight:bold;margin-bottom:10px"><?= sanitize($settings['receipt_footer']) ?></p>
                <div style="display:flex;justify-content:center;gap:40px;margin-top:40px">
                    <div style="border-top:1px solid #333;width:150px;padding-top:5px">توقيع المستلم</div>
                    <div style="border-top:1px solid #333;width:150px;padding-top:5px">توقيع المتجر</div>
                </div>
            </div>
        </div>
        `;
    } else {
        html = `
        <div style="font-family:'Cairo',sans-serif;direction:rtl;color:#000;padding:10px;font-size:12px;width:100%;max-width:80mm;margin:0 auto">
            <div style="text-align:center;margin-bottom:15px">
                <?php if (!empty($settings['store_logo_url'])): ?>
                <img src="<?= sanitize($settings['store_logo_url']) ?>" style="max-height:60px;margin:0 auto 5px;display:block">
                <?php else: ?>
                <div style="width:40px;height:40px;background:#000;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 5px"><span class="material-icons-outlined" style="font-size:24px">computer</span></div>
                <?php endif; ?>
                <h2 style="margin:5px 0;font-size:16px;font-weight:800"><?= sanitize($settings['store_name']) ?></h2>
                <?php if(!empty($settings['store_address'])): ?><p style="margin:2px 0;font-size:11px;color:#666"><?= sanitize($settings['store_address']) ?></p><?php endif; ?>
                <?php if(!empty($settings['store_phone'])): ?><p style="margin:2px 0;font-size:11px;color:#666;direction:ltr"><?= sanitize($settings['store_phone']) ?></p><?php endif; ?>
            </div>
            
            <div style="border-top:1px dashed #999;margin:10px 0"></div>
            
            <table style="width:100%;font-size:11px;margin-bottom:10px">
                <tr><td style="color:#666">التاريخ:</td><td style="text-align:left">${dateStr} - ${timeStr}${SHOW_HIJRI_DATES ? `<br><span style="color:#666">${hijriDateStr}</span>` : ``}</td></tr>
                <tr><td style="color:#666">الكاشير:</td><td style="text-align:left"><?= sanitize($user['full_name']) ?></td></tr>
                <tr><td style="color:#666">العميل:</td><td style="text-align:left">${escapeHtml(customerName)}</td></tr>
                ${customerPhone ? `<tr><td style="color:#666">الهاتف:</td><td style="text-align:left;direction:ltr">${escapeHtml(customerPhone)}</td></tr>` : ``}
                <tr><td style="color:#666">رقم الفاتورة:</td><td style="text-align:left;direction:ltr;font-weight:bold">${orderNumber ? '#' + orderNumber : '---'}</td></tr>
            </table>
            
            <div style="border-top:1px dashed #999;margin:10px 0"></div>
            
            <table style="width:100%;border-collapse:collapse;margin-bottom:15px">
                <thead>
                    <tr style="border-bottom:1px solid #000">
                        <th style="padding:5px 0;text-align:right">الصنف</th>
                        <th style="padding:5px 0;text-align:left">الإجمالي</th>
                    </tr>
                </thead>
                <tbody>
                    ${itemsHtml}
                </tbody>
            </table>
            
            <div style="border-top:1px solid #000;margin:10px 0"></div>
            
            <table style="width:100%;font-size:12px">
                ${!isDueInvoice ? `
                <tr>
                    <td style="padding:3px 0">المجموع الفرعي</td>
                    <td style="text-align:left;direction:ltr">${subtotal.toLocaleString()} <?= CURRENCY ?></td>
                </tr>` : ``}
                ${totalDiscount > 0 ? `
                <tr style="color:#c00">
                    <td style="padding:3px 0">إجمالي الخصم</td>
                    <td style="text-align:left;direction:ltr">-${totalDiscount.toLocaleString()} <?= CURRENCY ?></td>
                </tr>` : ''}
                ${isDueInvoice ? `
                ${remainingDue > 0 ? `
                <tr style="color:#b45309">
                    <td style="padding:3px 0">المتبقي دين</td>
                    <td style="text-align:left;direction:ltr">${remainingDue.toLocaleString()} <?= CURRENCY ?></td>
                </tr>` : ``}
                <tr>
                    <td style="padding:3px 0">إجمالي الفاتورة الفعلي</td>
                    <td style="text-align:left;direction:ltr">${invoiceActualTotal.toLocaleString()} <?= CURRENCY ?></td>
                </tr>
                <tr style="color:#047857">
                    <td style="padding:3px 0">إجمالي المدفوع</td>
                    <td style="text-align:left;direction:ltr">${invoicePaidTotal.toLocaleString()} <?= CURRENCY ?></td>
                </tr>
                <tr style="color:#b45309">
                    <td style="padding:3px 0">إجمالي المتبقي</td>
                    <td style="text-align:left;direction:ltr">${invoiceRemainingTotal.toLocaleString()} <?= CURRENCY ?></td>
                </tr>
                ` : ``}
                <tr style="font-weight:bold;font-size:16px">
                    <td style="padding:8px 0">الإجمالي النهائي</td>
                    <td style="text-align:left;direction:ltr">${total.toLocaleString()} <?= CURRENCY ?></td>
                </tr>
            </table>
            
            <div style="border-top:1px dashed #999;margin:15px 0"></div>
            
            <p style="text-align:center;font-size:11px;color:#666"><?= sanitize($settings['receipt_footer']) ?></p>
            <p style="text-align:center;font-size:10px;color:#aaa;margin-top:5px">يرجى الاحتفاظ بالإيصال للاستبدال</p>
        </div>
        `;
    }
    
    const printData = { html: html };
    localStorage.setItem('pos_print_data', JSON.stringify(printData));
    
    // Open print window
    const width = printType === 'a4' ? 900 : 450;
    const height = printType === 'a4' ? 1000 : 600;
    const left = (screen.width - width) / 2;
    const top = (screen.height - height) / 2;
    
    window.open('print_receipt.php', 'PrintReceipt', `width=${width},height=${height},top=${top},left=${left},scrollbars=yes`);
}

// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const suspended = document.getElementById('suspended-orders-modal');
        if (suspended && !suspended.classList.contains('hidden')) {
            closeSuspendedOrdersModal();
            return;
        }
        const picker = document.getElementById('customer-picker-modal');
        if (picker && !picker.classList.contains('hidden')) {
            closeCustomerPicker();
            return;
        }
        closePayment();
    }
    if (e.key === 'F12') { e.preventDefault(); proceedToPayment(); }
});
</script>



<!-- Hidden Print Area -->
<div id="pos-receipt-print" class="hidden"></div>
<style>
@media print {
    body > *:not(#pos-receipt-print) { display: none !important; }
    #pos-receipt-print { display: block !important; visibility: visible !important; }
    #pos-receipt-print * { visibility: visible !important; }
    #pos-receipt-print {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0;
        padding: 0;
        background: white;
        z-index: 9999;
    }
    @page { size: auto; margin: 0; }
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
