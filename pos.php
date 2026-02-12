<?php
require_once __DIR__ . '/includes/bootstrap.php';
Auth::requireLogin();
$pageTitle = 'نقطة البيع';
$user = Auth::user();
$categories = $db->fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order");
$products = $db->fetchAll("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.is_active = 1 AND p.quantity > 0 ORDER BY p.name");
$settings = getStoreSettings();
include __DIR__ . '/includes/header.php';
?>

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
                    <p class="text-sm text-slate-500"><?= date('l، j F Y') ?></p>
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
                    <?php if (Auth::hasRole(['admin'])): ?>
                    <a href="admin/dashboard.php" class="p-2 rounded-full bg-white text-slate-500 hover:text-primary transition-colors shadow-sm ring-1 ring-slate-200">
                        <span class="material-icons-outlined">settings</span>
                    </a>
                    <?php endif; ?>
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
        </header>

        <!-- Products Grid -->
        <main class="flex-1 overflow-y-auto px-6 pb-6">
            <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4" id="products-grid">
                <?php foreach ($products as $p): ?>
                <div class="product-card group bg-white rounded-2xl p-3 border border-slate-100 shadow-sm hover:shadow-md hover:border-primary/30 transition-all cursor-pointer"
                     data-id="<?= $p['id'] ?>"
                     data-name="<?= sanitize($p['name']) ?>"
                     data-price="<?= $p['price'] ?>"
                     data-category="<?= $p['category_id'] ?>"
                     data-image="<?= sanitize($p['image_url'] ?? '') ?>"
                     data-qty="<?= $p['quantity'] ?>"
                     data-barcode="<?= sanitize($p['barcode'] ?? '') ?>"
                     onclick="addToCart(this)">
                    <div class="aspect-[4/3] rounded-xl overflow-hidden bg-slate-50 mb-3 relative">
                        <?php if ($p['image_url']): ?>
                        <img class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" 
                             src="<?= sanitize($p['image_url']) ?>" alt="<?= sanitize($p['name']) ?>">
                        <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center text-slate-300">
                            <span class="material-icons-outlined text-5xl">inventory_2</span>
                        </div>
                        <?php endif; ?>
                        <?php if ($p['quantity'] <= LOW_STOCK_THRESHOLD): ?>
                        <div class="absolute top-2 left-2 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-md shadow-sm">كمية محدودة</div>
                        <?php endif; ?>
                    </div>
                    <div class="space-y-1">
                        <p class="text-xs font-semibold text-primary uppercase tracking-wide"><?= sanitize($p['category_name'] ?? '') ?></p>
                        <h3 class="font-bold text-slate-800 line-clamp-2 h-12 leading-tight"><?= sanitize($p['name']) ?></h3>
                        <div class="flex items-center justify-between mt-2 pt-2 border-t border-dashed border-slate-200">
                            <span class="font-num font-bold text-lg text-slate-900"><?= number_format($p['price'], 0) ?> <span class="text-xs font-normal text-slate-500"><?= CURRENCY ?></span></span>
                            <button class="w-8 h-8 flex items-center justify-center rounded-lg bg-primary/10 text-primary hover:bg-primary hover:text-white transition-colors">
                                <span class="material-icons-outlined text-lg">add</span>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
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
                <div class="flex justify-between text-slate-600 text-sm" id="tax-row" style="<?= TAX_RATE == 0 ? 'display:none' : '' ?>">
                    <span>الضريبة (<?= TAX_RATE * 100 ?>%)</span>
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
        <div class="flex-1 flex flex-col p-6 md:p-8 bg-white relative z-10 order-1">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 mb-1">إتمام عملية الدفع</h1>
                    <p class="text-slate-500 text-sm">اختر طريقة الدفع وأدخل المبلغ</p>
                </div>
                <button onclick="closePayment()" class="p-2 rounded-full hover:bg-slate-100 text-slate-500 transition-colors">
                    <span class="material-icons-outlined">close</span>
                </button>
            </div>

            <!-- Total -->
            <div class="bg-blue-50 rounded-xl p-6 mb-6 flex justify-between items-center border border-blue-100">
                <span class="text-slate-600 font-medium text-lg">المبلغ الإجمالي</span>
                <div class="text-right">
                    <span class="text-4xl font-bold text-primary font-num" id="modal-total">0.00</span>
                    <span class="text-primary/70 text-sm font-medium mr-1"><?= CURRENCY ?></span>
                </div>
            </div>

            <!-- Payment Methods -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-slate-700 mb-3">طريقة الدفع</label>
                <div class="grid grid-cols-3 gap-3">
                    <button onclick="selectPaymentMethod('cash')" class="pay-method-btn flex flex-col items-center justify-center p-4 rounded-xl border-2 border-primary bg-primary/5 text-primary transition-all duration-200" data-method="cash">
                        <span class="material-icons-outlined text-3xl mb-2">payments</span>
                        <span class="font-bold">نقدي</span>
                    </button>
                    <button onclick="selectPaymentMethod('card')" class="pay-method-btn flex flex-col items-center justify-center p-4 rounded-xl border border-slate-200 hover:border-primary/50 hover:bg-slate-50 text-slate-600 transition-all duration-200" data-method="card">
                        <span class="material-icons-outlined text-3xl mb-2">credit_card</span>
                        <span class="font-medium">بطاقة</span>
                    </button>
                    <button onclick="selectPaymentMethod('transfer')" class="pay-method-btn flex flex-col items-center justify-center p-4 rounded-xl border border-slate-200 hover:border-primary/50 hover:bg-slate-50 text-slate-600 transition-all duration-200" data-method="transfer">
                        <span class="material-icons-outlined text-3xl mb-2">qr_code_scanner</span>
                        <span class="font-medium">تحويل</span>
                    </button>
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
                        <span class="text-green-800 font-bold">المتبقي للعميل</span>
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

            <!-- Confirm button -->
            <div class="mt-6 pt-6 border-t border-slate-100 flex gap-4">
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
                        </div>
                        <div class="text-left font-num" dir="ltr">
                            <p><?= date('Y-m-d') ?></p>
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
                        <div class="flex justify-between text-xs text-slate-600"><span>المجموع الفرعي</span><span class="font-num" id="receipt-subtotal">0.00</span></div>
                        <div class="flex justify-between text-xs text-slate-600"><span>ضريبة القيمة المضافة (<?= TAX_RATE * 100 ?>%)</span><span class="font-num" id="receipt-tax">0.00</span></div>
                    </div>
                    <div class="w-full border-t border-black pt-2 mb-6">
                        <div class="flex justify-between items-center text-lg font-bold"><span>الإجمالي</span><span class="font-num" id="receipt-total">0.00</span></div>
                    </div>
                    <p class="text-xs text-slate-500 font-bold mb-1">شكراً لزيارتكم</p>
                    <p class="text-[10px] text-slate-400">يرجى الاحتفاظ بالإيصال للاستبدال</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Cart state
let cart = [];
let selectedPaymentMethod = 'cash';
let invoiceDiscountType = 'value'; // 'value' or 'percent'
const TAX_RATE = <?= TAX_RATE ?>;
const CURRENCY = '<?= CURRENCY ?>';

function addToCart(el) {
    const id = parseInt(el.dataset.id);
    const name = el.dataset.name;
    const price = parseFloat(el.dataset.price);
    const image = el.dataset.image;
    const maxQty = parseInt(el.dataset.qty);
    
    const existing = cart.find(i => i.id === id);
    if (existing) {
        if (existing.qty < maxQty) existing.qty++;
    } else {
        cart.push({ 
            id, name, price, image, qty: 1, maxQty, 
            discount: 0, 
            discountType: 'value', // 'value' or 'percent'
            discountInput: 0,
            showDiscount: false 
        });
    }
    renderCart();
}

function removeFromCart(id) {
    cart = cart.filter(i => i.id !== id);
    renderCart();
}

function updateQty(id, delta) {
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
    renderCart();
}

function setDiscountType(type) {
    invoiceDiscountType = type;
    document.getElementById('disc-type-value').className = 'disc-type-btn px-3 h-full text-xs ' + (type === 'value' ? 'font-bold bg-green-600 text-white' : 'font-medium text-slate-500') + ' transition-colors';
    document.getElementById('disc-type-percent').className = 'disc-type-btn px-3 h-full text-xs ' + (type === 'percent' ? 'font-bold bg-green-600 text-white' : 'font-medium text-slate-500') + ' transition-colors';
    updateTotals();
}

function renderCart() {
    const listEl = document.getElementById('cart-list');
    const emptyMsg = document.getElementById('cart-empty');
    const payBtn = document.getElementById('pay-btn');
    
    if (cart.length === 0) {
        listEl.innerHTML = '';
        emptyMsg.style.display = 'flex';
        payBtn.disabled = true;
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
                                <button onclick="updateQty(${item.id}, -1)" class="w-7 h-full flex items-center justify-center text-slate-500 hover:text-primary rounded-r-lg transition-colors">
                                    <span class="material-icons-outlined text-sm">remove</span>
                                </button>
                                <span class="w-7 text-center text-xs font-num font-semibold text-slate-800">${item.qty}</span>
                                <button onclick="updateQty(${item.id}, 1)" class="w-7 h-full flex items-center justify-center text-white bg-primary rounded-l-lg hover:bg-primary-dark transition-colors">
                                    <span class="material-icons-outlined text-sm">add</span>
                                </button>
                            </div>
                            <div class="flex items-center gap-2">
                                <button onclick="toggleItemDiscount(${item.id})" class="p-1 rounded text-xs ${hasDisc || item.showDiscount ? 'text-green-600 bg-green-50 font-bold' : 'text-slate-400 hover:text-green-600 hover:bg-green-50'} transition-colors" title="خصم على العنصر">
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
                <div class="${item.showDiscount ? 'flex' : 'hidden'} items-center gap-2 mt-3 pt-3 border-t border-dashed border-slate-200 animate-fade-in">
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

function getCartTotals() {
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
    
    document.getElementById('subtotal').textContent = subtotal.toLocaleString() + ' ' + CURRENCY;
    document.getElementById('tax-amount').textContent = tax.toLocaleString() + ' ' + CURRENCY;
    document.getElementById('total-amount').innerHTML = total.toLocaleString() + ' <span class="text-sm font-normal text-slate-500">' + CURRENCY + '</span>';
    document.getElementById('cart-count').textContent = cart.reduce((s, i) => s + i.qty, 0) + ' \u0639\u0646\u0627\u0635\u0631';
    
    // Discount display
    const discRow = document.getElementById('discount-row');
    if (totalDiscount > 0) {
        discRow.style.display = 'flex';
        document.getElementById('discount-amount').textContent = '-' + totalDiscount.toLocaleString() + ' ' + CURRENCY;
    } else {
        discRow.style.display = 'none';
    }
    
    // Receipt
    document.getElementById('receipt-subtotal').textContent = subtotal.toLocaleString();
    document.getElementById('receipt-tax').textContent = tax.toLocaleString();
    document.getElementById('receipt-total').textContent = total.toLocaleString();
    document.getElementById('modal-total').textContent = total.toLocaleString();
    
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
    
    updateChange();
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

    document.addEventListener('keydown', function(e) {
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
            const card = document.querySelector(`.product-card[data-barcode="${barcodeBuffer}"]`);
            if (card) {
                addToCart(card);
                // Visual feedback
                card.classList.add('ring-2', 'ring-green-400');
                setTimeout(() => card.classList.remove('ring-2', 'ring-green-400'), 600);
            } else {
                // Show not found notification
                alert('لم يتم العثور على منتج بالباركود: ' + barcodeBuffer);
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
    document.getElementById('received-amount').value = '0';
    updateChange();
}

function closePayment() {
    document.getElementById('payment-modal').classList.add('hidden');
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
    const change = received - total;
    document.getElementById('change-amount').textContent = Math.max(0, change).toFixed(2);
}

// Complete Sale
// Complete Sale
async function completeSale() {
    if (cart.length === 0) return;
    const { subtotal, totalDiscount, invoiceDisc, tax, total } = getCartTotals();
    const received = parseFloat(document.getElementById('received-amount').value) || 0;
    
    if (received < total && selectedPaymentMethod === 'cash') {
        alert('المبلغ المستلم أقل من الإجمالي');
        return;
    }
    
    try {
        const response = await fetch('api/orders.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'create',
                items: cart.map(i => ({ product_id: i.id, quantity: i.qty, unit_price: i.price, discount: i.discount || 0 })),
                payment_method: selectedPaymentMethod,
                payment_received: received,
                discount: invoiceDisc
            })
        });
        const data = await response.json();
        if (data.success) {
            // Print receipt before clearing cart
            printReceipt(data.order_number, new Date());
            
            document.getElementById('receipt-number').textContent = '#' + data.order_number;
            // Removed alert to streamline flow
            cart = [];
            document.getElementById('invoice-discount').value = 0;
            renderCart();
            closePayment();
        } else {
            alert('خطأ: ' + (data.error || 'حدث خطأ غير متوقع'));
        }
    } catch (err) {
        alert('خطأ في الاتصال بالخادم');
    }
}

function holdOrder() { alert('تم حفظ الطلب مؤقتاً'); }

function printReceipt(orderNumber = null, orderDate = null) {
    if (cart.length === 0) { alert('السلة فارغة'); return; }
    
    const { subtotal, totalDiscount, invoiceDisc, tax, total } = getCartTotals();
    const now = orderDate || new Date();
    const dateStr = now.toLocaleDateString('ar-EG');
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
    
    const html = `
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
            <tr><td style="color:#666">التاريخ:</td><td style="text-align:left;direction:ltr">${dateStr} ${timeStr}</td></tr>
            <tr><td style="color:#666">الكاشير:</td><td style="text-align:left"><?= sanitize($user['full_name']) ?></td></tr>
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
            <tr>
                <td style="padding:3px 0">المجموع الفرعي</td>
                <td style="text-align:left;direction:ltr">${subtotal.toLocaleString()} <?= CURRENCY ?></td>
            </tr>
            ${totalDiscount > 0 ? `
            <tr style="color:#c00">
                <td style="padding:3px 0">إجمالي الخصم</td>
                <td style="text-align:left;direction:ltr">-${totalDiscount.toLocaleString()} <?= CURRENCY ?></td>
            </tr>` : ''}
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
    
    const printData = { html: html };
    localStorage.setItem('pos_print_data', JSON.stringify(printData));
    
    // Open print window
    const width = 450;
    const height = 600;
    const left = (screen.width - width) / 2;
    const top = (screen.height - height) / 2;
    
    window.open('print_receipt.php', 'PrintReceipt', `width=${width},height=${height},top=${top},left=${left},scrollbars=yes`);
}

// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closePayment();
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
