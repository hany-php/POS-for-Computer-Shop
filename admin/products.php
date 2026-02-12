<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole(['admin']);
$pageTitle = 'إدارة المخزون';
$user = Auth::user();

// Handle product actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $imageUrl = '';
        
        // Handle Image Upload
        $uploadDir = __DIR__ . '/../uploads/products/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
            $filename = 'prod_' . time() . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['image_file']['tmp_name'], $uploadDir . $filename)) {
                $imageUrl = 'uploads/products/' . $filename;
            }
        } elseif (!empty($_POST['image_url'])) {
            // Download from URL
            $url = $_POST['image_url'];
            $content = @file_get_contents($url);
            if ($content) {
                $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                $filename = 'prod_url_' . time() . '_' . uniqid() . '.' . $ext;
                file_put_contents($uploadDir . $filename, $content);
                $imageUrl = 'uploads/products/' . $filename;
            } else {
                $imageUrl = $_POST['image_url']; // Fallback to URL if download fails
            }
        }

        $db->insert(
            "INSERT INTO products (name, description, category_id, price, cost_price, quantity, barcode, serial_number, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$_POST['name'], $_POST['description'] ?? '', $_POST['category_id'], floatval($_POST['price']), floatval($_POST['cost_price'] ?? 0), intval($_POST['quantity'] ?? 0), $_POST['barcode'] ?? '', $_POST['serial_number'] ?? '', $imageUrl]
        );
        setFlash('success', 'تم إضافة المنتج بنجاح');
        logActivity('إضافة منتج', 'product', null, $_POST['name']);
        header('Location: products.php');
        exit;
    }
    if ($_POST['action'] === 'update') {
        $imageUrl = $_POST['image_url_original'] ?? ''; // Default to existing
        
        $uploadDir = __DIR__ . '/../uploads/products/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
            $filename = 'prod_' . time() . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['image_file']['tmp_name'], $uploadDir . $filename)) {
                $imageUrl = 'uploads/products/' . $filename;
            }
        } elseif (!empty($_POST['image_url']) && $_POST['image_url'] !== $imageUrl) {
             // Download from URL if changed
            $url = $_POST['image_url'];
            if (strpos($url, 'uploads/') !== 0) { // Only if not already local
                $content = @file_get_contents($url);
                if ($content) {
                    $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                    $filename = 'prod_url_' . time() . '_' . uniqid() . '.' . $ext;
                    file_put_contents($uploadDir . $filename, $content);
                    $imageUrl = 'uploads/products/' . $filename;
                } else {
                    $imageUrl = $url;
                }
            }
        }
        
        $db->query(
            "UPDATE products SET name=?, description=?, category_id=?, price=?, cost_price=?, quantity=?, barcode=?, serial_number=?, image_url=?, updated_at=CURRENT_TIMESTAMP WHERE id=?",
            [$_POST['name'], $_POST['description'] ?? '', $_POST['category_id'], floatval($_POST['price']), floatval($_POST['cost_price'] ?? 0), intval($_POST['quantity'] ?? 0), $_POST['barcode'] ?? '', $_POST['serial_number'] ?? '', $imageUrl, $_POST['product_id']]
        );
        setFlash('success', 'تم تحديث المنتج بنجاح');
        header('Location: products.php');
        exit;
    }
    if ($_POST['action'] === 'delete') {
        $db->query("UPDATE products SET is_active = 0 WHERE id = ?", [$_POST['product_id']]);
        setFlash('success', 'تم حذف المنتج');
        header('Location: products.php');
        exit;
    }
}

$products = $db->fetchAll("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.is_active = 1 ORDER BY p.updated_at DESC");
$categories = $db->fetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order");

// Stats
$totalProducts = count($products);
$totalValue = array_sum(array_map(fn($p) => $p['price'] * $p['quantity'], $products));
$lowStockCount = count(array_filter($products, fn($p) => $p['quantity'] <= LOW_STOCK_THRESHOLD && $p['quantity'] > 0));
$outOfStock = count(array_filter($products, fn($p) => $p['quantity'] <= 0));

include __DIR__ . '/../includes/header.php';
?>

<div class="flex h-screen overflow-hidden">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-background-light">
        <!-- Page Header -->
        <header class="bg-white border-b border-slate-200 px-6 py-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">إدارة المخزون</h1>
                    <p class="text-sm text-slate-500 mt-1">إدارة المنتجات والأصناف والكميات</p>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="document.getElementById('add-product-modal').classList.remove('hidden')" class="bg-primary hover:bg-primary-dark text-white px-5 py-2.5 rounded-xl shadow-lg shadow-primary/30 font-medium text-sm flex items-center gap-2 transition-all">
                        <span class="material-icons-outlined text-base">add</span>
                        إضافة منتج
                    </button>
                </div>
            </div>
        </header>

        <div class="p-6 space-y-6">
            <!-- Search + Filter -->
            <div class="flex flex-col sm:flex-row gap-4">
                <div class="relative flex-1 max-w-lg">
                    <span class="material-icons-outlined absolute right-3 top-2.5 text-slate-400">search</span>
                    <input type="text" id="product-search" class="w-full bg-white border border-slate-200 rounded-xl pr-10 pl-4 py-2.5 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all shadow-sm" placeholder="بحث بالاسم، الباركود، أو الفئة...">
                </div>
                <select id="category-filter" class="bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-primary shadow-sm">
                    <option value="all">كل الفئات</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= sanitize($cat['name']) ?>"><?= sanitize($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="stock-filter" class="bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-primary shadow-sm">
                    <option value="all">كل المخزون</option>
                    <option value="low">منخفض المخزون</option>
                    <option value="out">نفذ من المخزون</option>
                </select>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3">
                    <div class="p-2.5 bg-primary/10 text-primary rounded-lg"><span class="material-icons-outlined">inventory_2</span></div>
                    <div><p class="text-xs text-slate-500">إجمالي المنتجات</p><p class="text-xl font-bold font-num"><?= $totalProducts ?></p></div>
                </div>
                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3">
                    <div class="p-2.5 bg-green-50 text-green-600 rounded-lg"><span class="material-icons-outlined">payments</span></div>
                    <div><p class="text-xs text-slate-500">القيمة الإجمالية</p><p class="text-xl font-bold font-num"><?= number_format($totalValue) ?></p></div>
                </div>
                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3">
                    <div class="p-2.5 bg-yellow-50 text-yellow-600 rounded-lg"><span class="material-icons-outlined">warning</span></div>
                    <div><p class="text-xs text-slate-500">مخزون منخفض</p><p class="text-xl font-bold font-num text-yellow-600"><?= $lowStockCount ?></p></div>
                </div>
                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3">
                    <div class="p-2.5 bg-red-50 text-red-600 rounded-lg"><span class="material-icons-outlined">error</span></div>
                    <div><p class="text-xs text-slate-500">نفذ من المخزون</p><p class="text-xl font-bold font-num text-red-600"><?= $outOfStock ?></p></div>
                </div>
            </div>

            <!-- Products Table -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-auto">
                    <table class="w-full text-right" id="products-table">
                        <thead class="bg-slate-50 sticky top-0 z-10">
                            <tr>
                                <th class="p-4 text-xs font-semibold text-slate-500">المنتج</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">الباركود</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">الفئة</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">سعر البيع</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">التكلفة</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">الكمية</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">الحالة</th>
                                <th class="p-4 text-xs font-semibold text-slate-500">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($products as $p): ?>
                            <tr class="product-row hover:bg-slate-50 transition-colors group" 
                                data-name="<?= sanitize($p['name']) ?>" 
                                data-category="<?= sanitize($p['category_name'] ?? '') ?>"
                                data-barcode="<?= sanitize($p['barcode'] ?? '') ?>"
                                data-qty="<?= $p['quantity'] ?>">
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 rounded-lg bg-slate-100 overflow-hidden flex-shrink-0 flex items-center justify-center">
                                            <?php if ($p['image_url']): ?>
                                            <img class="w-full h-full object-cover" src="<?= (strpos($p['image_url'], 'http') === 0 ? '' : '../') . sanitize($p['image_url']) ?>" alt="">
                                            <?php else: ?>
                                            <span class="material-icons-outlined text-slate-300">inventory_2</span>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-slate-900"><?= sanitize($p['name']) ?></div>
                                            <div class="text-xs text-slate-400"><?= sanitize($p['description'] ?? '') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 text-sm text-slate-500 font-num"><?= sanitize($p['barcode'] ?? '—') ?></td>
                                <td class="p-4"><span class="px-2.5 py-1 text-xs font-medium rounded-full bg-slate-100 text-slate-600"><?= sanitize($p['category_name'] ?? '—') ?></span></td>
                                <td class="p-4 text-sm font-num font-bold text-slate-900"><?= number_format($p['price'], 0) ?></td>
                                <td class="p-4 text-sm font-num text-slate-500"><?= number_format($p['cost_price'], 0) ?></td>
                                <td class="p-4 text-sm font-num font-bold"><?= $p['quantity'] ?></td>
                                <td class="p-4">
                                    <?php if ($p['quantity'] <= 0): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-full bg-red-50 text-red-700 border border-red-100"><span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> نفذ</span>
                                    <?php elseif ($p['quantity'] <= LOW_STOCK_THRESHOLD): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-full bg-yellow-50 text-yellow-700 border border-yellow-100"><span class="w-1.5 h-1.5 rounded-full bg-yellow-500 animate-pulse"></span> منخفض</span>
                                    <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-full bg-green-50 text-green-700 border border-green-100"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> متوفر</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4">
                                    <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button onclick='editProduct(<?= json_encode($p, JSON_UNESCAPED_UNICODE) ?>)' class="p-1.5 hover:bg-slate-100 rounded-lg text-slate-500 hover:text-primary transition-colors"><span class="material-icons-outlined text-[18px]">edit</span></button>
                                        <form method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذا المنتج؟')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="p-1.5 hover:bg-red-50 rounded-lg text-slate-500 hover:text-red-500 transition-colors"><span class="material-icons-outlined text-[18px]">delete</span></button>
                                        </form>
                                    </div>
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

<!-- Add/Edit Product Modal -->
<div id="add-product-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden border border-slate-100">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h2 class="text-lg font-bold" id="modal-title">إضافة منتج جديد</h2>
            <button onclick="closeProductModal()" class="p-2 rounded-full hover:bg-slate-100 text-slate-500"><span class="material-icons-outlined">close</span></button>
        </div>
        <form id="product-form" method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            <input type="hidden" name="action" id="form-action" value="create">
            <input type="hidden" name="product_id" id="form-product-id" value="">
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">اسم المنتج *</label>
                <input type="text" name="name" id="f-name" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary" placeholder="اسم المنتج">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">الوصف</label>
                <input type="text" name="description" id="f-desc" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary" placeholder="وصف قصير">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">الفئة</label>
                    <select name="category_id" id="f-category" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= sanitize($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">الباركود</label>
                    <input type="text" name="barcode" id="f-barcode" dir="ltr" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 text-sm font-num focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary text-left">
                </div>
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">سعر البيع *</label>
                    <input type="number" name="price" id="f-price" required step="0.01" dir="ltr" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 text-sm font-num focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary text-left" placeholder="0.00">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">التكلفة</label>
                    <input type="number" name="cost_price" id="f-cost" step="0.01" dir="ltr" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 text-sm font-num focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary text-left" placeholder="0.00">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">الكمية</label>
                    <input type="number" name="quantity" id="f-qty" dir="ltr" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 text-sm font-num focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary text-left" placeholder="0">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">صورة المنتج</label>
                <div class="space-y-2">
                    <input type="file" name="image_file" accept="image/*" class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20">
                    <div class="relative">
                        <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-400">
                            <span class="material-icons-outlined text-sm">link</span>
                        </span>
                        <input type="url" name="image_url" id="f-image" dir="ltr" class="w-full bg-slate-50 border border-slate-200 rounded-lg pr-10 pl-4 py-2 text-sm font-num focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary text-left placeholder:text-right" placeholder="أو أدخل رابطاً مباشراً (سيتم تحميله)">
                    </div>
                </div>
                <input type="hidden" name="image_url_original" id="f-image-orig">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">الرقم التسلسلي</label>
                <input type="text" name="serial_number" id="f-serial" dir="ltr" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 text-sm font-num focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary text-left">
            </div>
            <div class="pt-4 border-t border-slate-100">
                <button type="submit" class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-3 rounded-xl shadow-lg shadow-primary/30 transition-all">
                    <span id="submit-text">إضافة المنتج</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Search & Filter
function filterProducts() {
    const q = document.getElementById('product-search').value.toLowerCase();
    const cat = document.getElementById('category-filter').value;
    const stock = document.getElementById('stock-filter').value;
    
    document.querySelectorAll('.product-row').forEach(row => {
        const name = row.dataset.name.toLowerCase();
        const category = row.dataset.category;
        const barcode = row.dataset.barcode.toLowerCase();
        const qty = parseInt(row.dataset.qty);
        
        let show = true;
        if (q && !name.includes(q) && !barcode.includes(q)) show = false;
        if (cat !== 'all' && category !== cat) show = false;
        if (stock === 'low' && qty > <?= LOW_STOCK_THRESHOLD ?>) show = false;
        if (stock === 'out' && qty > 0) show = false;
        
        row.style.display = show ? '' : 'none';
    });
}

document.getElementById('product-search').addEventListener('input', filterProducts);
document.getElementById('category-filter').addEventListener('change', filterProducts);
document.getElementById('stock-filter').addEventListener('change', filterProducts);

function editProduct(p) {
    document.getElementById('form-action').value = 'update';
    document.getElementById('form-product-id').value = p.id;
    document.getElementById('f-name').value = p.name;
    document.getElementById('f-desc').value = p.description || '';
    document.getElementById('f-category').value = p.category_id || '';
    document.getElementById('f-barcode').value = p.barcode || '';
    document.getElementById('f-price').value = p.price;
    document.getElementById('f-cost').value = p.cost_price || '';
    document.getElementById('f-qty').value = p.quantity;
    document.getElementById('f-qty').value = p.quantity;
    document.getElementById('f-image').value = p.image_url || '';
    document.getElementById('f-image-orig').value = p.image_url || '';
    document.getElementById('f-serial').value = p.serial_number || '';
    document.getElementById('modal-title').textContent = 'تعديل المنتج';
    document.getElementById('submit-text').textContent = 'حفظ التعديلات';
    document.getElementById('add-product-modal').classList.remove('hidden');
}

function closeProductModal() {
    document.getElementById('add-product-modal').classList.add('hidden');
    document.getElementById('product-form').reset();
    document.getElementById('form-action').value = 'create';
    document.getElementById('form-product-id').value = '';
    document.getElementById('modal-title').textContent = 'إضافة منتج جديد';
    document.getElementById('submit-text').textContent = 'إضافة المنتج';
}

document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeProductModal(); });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
