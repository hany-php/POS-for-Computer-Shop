<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole(['admin']);
$pageTitle = 'إعدادات المتجر';
$user = Auth::user();

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['store_name', 'store_address', 'store_phone', 'store_email', 'store_logo_url', 'tax_rate', 'currency', 'low_stock_threshold', 'receipt_footer', 'print_type'];
    foreach ($fields as $key) {
        if (isset($_POST[$key])) {
            $db->query("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)", [$key, trim($_POST[$key])]);
        }
    }
    setFlash('success', 'تم حفظ الإعدادات بنجاح');
    header('Location: settings.php');
    exit;
}

// Load all settings
$settingsRaw = $db->fetchAll("SELECT key, value FROM settings");
$settings = [];
foreach ($settingsRaw as $s) $settings[$s['key']] = $s['value'];

include __DIR__ . '/../includes/header.php';
?>

<div class="flex h-screen overflow-hidden">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-background-light">
        <header class="bg-white border-b border-slate-200 px-6 py-5">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">إعدادات المتجر</h1>
                    <p class="text-sm text-slate-500 mt-1">إدارة بيانات المتجر التي تظهر على الفواتير والإيصالات</p>
                </div>
            </div>
        </header>

        <div class="p-6">
            <form method="POST" class="max-w-2xl space-y-6">
                <!-- Store Info -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="p-5 border-b border-slate-100 bg-slate-50">
                        <h3 class="font-bold text-slate-800 flex items-center gap-2">
                            <span class="material-icons-outlined text-primary">store</span>
                            بيانات المتجر
                        </h3>
                        <p class="text-xs text-slate-500 mt-1">هذه البيانات ستظهر على الفواتير والإيصالات</p>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">اسم المتجر *</label>
                            <input type="text" name="store_name" value="<?= sanitize($settings['store_name'] ?? STORE_NAME) ?>" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all" placeholder="مثال: تك ستور للإلكترونيات">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">العنوان</label>
                            <input type="text" name="store_address" value="<?= sanitize($settings['store_address'] ?? '') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all" placeholder="مثال: شارع التحرير، القاهرة">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">رقم التليفون</label>
                                <input type="tel" name="store_phone" value="<?= sanitize($settings['store_phone'] ?? STORE_PHONE) ?>" dir="ltr" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-sm font-num focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all text-left" placeholder="01xxxxxxxxx">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">البريد الإلكتروني</label>
                                <input type="email" name="store_email" value="<?= sanitize($settings['store_email'] ?? '') ?>" dir="ltr" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-sm font-num focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all text-left" placeholder="info@store.com">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">رابط الشعار (Logo URL)</label>
                            <input type="url" name="store_logo_url" value="<?= sanitize($settings['store_logo_url'] ?? '') ?>" dir="ltr" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-sm font-num focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all text-left" placeholder="https://...">
                        </div>
                    </div>
                </div>

                <!-- Receipt Settings -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="p-5 border-b border-slate-100 bg-slate-50">
                        <h3 class="font-bold text-slate-800 flex items-center gap-2">
                            <span class="material-icons-outlined text-primary">receipt_long</span>
                            إعدادات الفاتورة
                        </h3>
                    </div>
                    <div class="p-6 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">العملة</label>
                                <input type="text" name="currency" value="<?= sanitize($settings['currency'] ?? CURRENCY) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all" placeholder="ج.م">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">نسبة الضريبة (%)</label>
                                <input type="number" name="tax_rate" value="<?= sanitize($settings['tax_rate'] ?? (TAX_RATE * 100)) ?>" step="0.1" dir="ltr" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-sm font-num focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all text-left" placeholder="0">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">حد المخزون المنخفض</label>
                            <input type="number" name="low_stock_threshold" value="<?= sanitize($settings['low_stock_threshold'] ?? LOW_STOCK_THRESHOLD) ?>" dir="ltr" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-sm font-num focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all text-left max-w-xs" placeholder="5">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">تذييل الفاتورة</label>
                            <textarea name="receipt_footer" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all resize-none" placeholder="مثال: شكراً لزيارتكم - نتمنى لكم يوماً سعيداً"><?= sanitize($settings['receipt_footer'] ?? 'شكراً لتعاملكم معنا') ?></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">نوع الطباعة الافتراضي</label>
                            <select name="print_type" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all">
                                <option value="thermal" <?= ($settings['print_type'] ?? 'thermal') === 'thermal' ? 'selected' : '' ?>>حراري (80mm)</option>
                                <option value="a4" <?= ($settings['print_type'] ?? '') === 'a4' ? 'selected' : '' ?>>صفحة كاملة (A4)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Invoice Preview -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="p-5 border-b border-slate-100 bg-slate-50">
                        <h3 class="font-bold text-slate-800 flex items-center gap-2">
                            <span class="material-icons-outlined text-primary">preview</span>
                            معاينة رأس الفاتورة
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="border-2 border-dashed border-slate-200 rounded-lg p-6 text-center">
                            <?php if (!empty($settings['store_logo_url'])): ?>
                            <img src="<?= sanitize($settings['store_logo_url']) ?>" class="h-12 mx-auto mb-2" alt="Logo">
                            <?php endif; ?>
                            <h2 class="text-xl font-bold"><?= sanitize($settings['store_name'] ?? STORE_NAME) ?></h2>
                            <?php if (!empty($settings['store_address'])): ?>
                            <p class="text-sm text-slate-500 mt-1"><?= sanitize($settings['store_address']) ?></p>
                            <?php endif; ?>
                            <p class="text-sm text-slate-500 font-num"><?= sanitize($settings['store_phone'] ?? STORE_PHONE) ?></p>
                            <?php if (!empty($settings['store_email'])): ?>
                            <p class="text-sm text-slate-500 font-num"><?= sanitize($settings['store_email']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <button type="submit" class="bg-primary hover:bg-primary-dark text-white font-bold py-3.5 px-8 rounded-xl shadow-lg shadow-primary/30 transition-all flex items-center gap-2">
                    <span class="material-icons-outlined">save</span>
                    حفظ الإعدادات
                </button>
            </form>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
