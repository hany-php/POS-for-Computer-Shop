<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole(['admin']);
$pageTitle = 'إدارة المشتريات';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCsrfTokenOrFail();

    if ($_POST['action'] === 'create_purchase') {
        $supplierId = intval($_POST['supplier_id'] ?? 0);
        $invoiceDate = $_POST['invoice_date'] ?? date('Y-m-d');
        $paymentMethod = $_POST['payment_method'] ?? 'cash';
        $notes = trim((string)($_POST['notes'] ?? ''));
        $itemsJson = $_POST['items_json'] ?? '[]';
        $paidAmountInput = floatval($_POST['paid_amount'] ?? 0);

        $items = json_decode($itemsJson, true);
        if (!is_array($items) || empty($items)) {
            setFlash('error', 'لا توجد أصناف في فاتورة الشراء');
            header('Location: purchases.php');
            exit;
        }
        if ($supplierId <= 0) {
            setFlash('error', 'يجب اختيار مورد');
            header('Location: purchases.php');
            exit;
        }

        $pdo = $db->getConnection();
        $pdo->beginTransaction();
        try {
            $prepared = [];
            $total = 0.0;
            foreach ($items as $it) {
                $productId = intval($it['product_id'] ?? 0);
                $qty = intval($it['quantity'] ?? 0);
                $unitCost = floatval($it['unit_cost'] ?? 0);
                if ($productId <= 0 || $qty <= 0 || $unitCost <= 0) {
                    throw new Exception('بيانات صنف شراء غير صالحة');
                }
                $product = $db->fetchOne("SELECT id, quantity, cost_price FROM products WHERE id = ? AND is_active = 1", [$productId]);
                if (!$product) throw new Exception('منتج غير موجود أو غير نشط');
                $lineTotal = $qty * $unitCost;
                $total += $lineTotal;
                $prepared[] = [
                    'product_id' => $productId,
                    'qty' => $qty,
                    'unit_cost' => $unitCost,
                    'line_total' => $lineTotal,
                    'current_qty' => intval($product['quantity'] ?? 0),
                    'current_cost' => floatval($product['cost_price'] ?? 0),
                ];
            }

            $paidAmount = max(0, min($paidAmountInput, $total));
            $dueAmount = $total - $paidAmount;
            $status = $dueAmount <= 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'pending');
            $invoiceNumber = generatePurchaseInvoiceNumber();

            $invoiceId = $db->insert(
                "INSERT INTO purchase_invoices (invoice_number, supplier_id, invoice_date, total_amount, paid_amount, due_amount, payment_method, status, notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$invoiceNumber, $supplierId, $invoiceDate, $total, $paidAmount, $dueAmount, $paymentMethod, $status, $notes, $_SESSION['user_id'] ?? null]
            );

            foreach ($prepared as $row) {
                $db->insert(
                    "INSERT INTO purchase_items (purchase_invoice_id, product_id, quantity, unit_cost, total_cost)
                     VALUES (?, ?, ?, ?, ?)",
                    [$invoiceId, $row['product_id'], $row['qty'], $row['unit_cost'], $row['line_total']]
                );

                $newQty = $row['current_qty'] + $row['qty'];
                $newCost = $newQty > 0
                    ? (($row['current_qty'] * $row['current_cost']) + ($row['qty'] * $row['unit_cost'])) / $newQty
                    : $row['unit_cost'];

                $db->query(
                    "UPDATE products SET quantity = ?, cost_price = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                    [$newQty, $newCost, $row['product_id']]
                );
            }

            postPurchaseInvoiceAccountingEntry($invoiceId);
            $pdo->commit();

            logActivity('إنشاء فاتورة شراء', 'purchase_invoice', $invoiceId, $invoiceNumber . ' total=' . $total);
            setFlash('success', 'تم إنشاء فاتورة الشراء وترحيلها محاسبيًا بنجاح');
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('error', $e->getMessage());
        }
        header('Location: purchases.php');
        exit;
    }

    if ($_POST['action'] === 'add_supplier_payment') {
        $invoiceId = intval($_POST['purchase_invoice_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
        $paymentMethod = $_POST['payment_method'] ?? 'cash';
        $notes = trim((string)($_POST['notes'] ?? ''));
        if ($invoiceId <= 0 || $amount <= 0) {
            setFlash('error', 'بيانات السداد غير صالحة');
            header('Location: purchases.php');
            exit;
        }

        $pdo = $db->getConnection();
        $pdo->beginTransaction();
        try {
            $invoice = $db->fetchOne("SELECT * FROM purchase_invoices WHERE id = ?", [$invoiceId]);
            if (!$invoice) throw new Exception('فاتورة الشراء غير موجودة');
            $due = floatval($invoice['due_amount'] ?? 0);
            if ($due <= 0) throw new Exception('هذه الفاتورة مسددة بالكامل');
            $pay = min($amount, $due);

            $paymentId = $db->insert(
                "INSERT INTO supplier_payments (supplier_id, purchase_invoice_id, payment_date, amount, payment_method, notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [intval($invoice['supplier_id']), $invoiceId, $paymentDate, $pay, $paymentMethod, $notes, $_SESSION['user_id'] ?? null]
            );

            $newPaid = floatval($invoice['paid_amount']) + $pay;
            $newDue = max(0, floatval($invoice['total_amount']) - $newPaid);
            $newStatus = $newDue <= 0 ? 'paid' : 'partial';
            $db->query(
                "UPDATE purchase_invoices SET paid_amount = ?, due_amount = ?, status = ? WHERE id = ?",
                [$newPaid, $newDue, $newStatus, $invoiceId]
            );

            postSupplierPaymentAccountingEntry($paymentId);
            $pdo->commit();
            logActivity('سداد مورد', 'supplier_payment', $paymentId, 'invoice=' . $invoiceId . ', amount=' . $pay);
            setFlash('success', 'تم تسجيل السداد وترحيله محاسبيًا');
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('error', $e->getMessage());
        }
        header('Location: purchases.php');
        exit;
    }
}

$suppliers = $db->fetchAll("SELECT id, name, current_balance, is_active FROM suppliers WHERE is_active = 1 ORDER BY name");
$products = $db->fetchAll("SELECT id, name, quantity, cost_price FROM products WHERE is_active = 1 ORDER BY name");
$invoices = $db->fetchAll(
    "SELECT pi.*, s.name AS supplier_name
     FROM purchase_invoices pi
     JOIN suppliers s ON s.id = pi.supplier_id
     ORDER BY pi.id DESC
     LIMIT 80"
);

include __DIR__ . '/../includes/header.php';
?>

<div class="flex h-screen overflow-hidden">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="flex-1 overflow-y-auto bg-background-light">
        <header class="bg-white border-b border-slate-200 px-6 py-5">
            <h1 class="text-2xl font-bold text-slate-900">إدارة المشتريات</h1>
            <p class="text-sm text-slate-500 mt-1">فواتير شراء الموردين، إضافة للمخزون، وسداد الذمم</p>
        </header>

        <div class="p-6 space-y-6">
            <div class="bg-white rounded-xl border border-slate-200 p-6">
                <h3 class="font-bold text-slate-900 mb-4">إنشاء فاتورة شراء</h3>
                <form method="POST" id="purchase-form" class="space-y-4">
                    <input type="hidden" name="action" value="create_purchase">
                    <input type="hidden" name="items_json" id="items-json" value="[]">
                    <?php csrfInput(); ?>
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">المورد</label>
                            <select name="supplier_id" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm">
                                <option value="">اختر المورد</option>
                                <?php foreach ($suppliers as $s): ?>
                                <option value="<?= intval($s['id']) ?>"><?= sanitize($s['name']) ?> (رصيد: <?= number_format(floatval($s['current_balance']), 2) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">تاريخ الفاتورة</label>
                            <input type="date" name="invoice_date" value="<?= date('Y-m-d') ?>" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-num">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">المدفوع الآن</label>
                            <input type="number" name="paid_amount" min="0" step="0.01" value="0" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-num text-left" dir="ltr">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">طريقة السداد</label>
                            <select name="payment_method" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm">
                                <option value="cash">نقدي</option>
                                <option value="card">بنك / بطاقة</option>
                                <option value="transfer">تحويل</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">ملاحظات</label>
                            <input type="text" name="notes" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        </div>
                    </div>

                    <div class="border border-slate-200 rounded-xl overflow-hidden">
                        <div class="p-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                            <h4 class="font-semibold text-slate-700">أصناف الفاتورة</h4>
                            <button type="button" onclick="addPurchaseRow()" class="bg-primary text-white px-3 py-1.5 rounded-lg text-xs">إضافة صنف</button>
                        </div>
                        <div class="overflow-auto">
                            <table class="w-full text-sm text-right">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="p-2">الصنف</th>
                                        <th class="p-2">الكمية</th>
                                        <th class="p-2">تكلفة الوحدة</th>
                                        <th class="p-2">الإجمالي</th>
                                        <th class="p-2">إجراء</th>
                                    </tr>
                                </thead>
                                <tbody id="purchase-items-body"></tbody>
                            </table>
                        </div>
                        <div class="p-3 border-t border-slate-200 flex justify-between font-semibold">
                            <span>إجمالي الفاتورة</span>
                            <span class="font-num" id="purchase-total">0.00</span>
                        </div>
                    </div>

                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-lg text-sm font-medium">حفظ فاتورة الشراء</button>
                </form>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="p-4 border-b border-slate-100">
                    <h3 class="font-bold text-slate-900">آخر فواتير الشراء</h3>
                </div>
                <div class="overflow-auto">
                    <table class="w-full text-sm text-right">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="p-3">رقم الفاتورة</th>
                                <th class="p-3">المورد</th>
                                <th class="p-3">التاريخ</th>
                                <th class="p-3">الإجمالي</th>
                                <th class="p-3">المدفوع</th>
                                <th class="p-3">المتبقي</th>
                                <th class="p-3">الحالة</th>
                                <th class="p-3">سداد</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($invoices)): ?>
                            <tr><td colspan="8" class="p-6 text-center text-slate-400">لا توجد فواتير مشتريات بعد</td></tr>
                            <?php endif; ?>
                            <?php foreach ($invoices as $inv): ?>
                            <tr>
                                <td class="p-3 font-num"><?= sanitize($inv['invoice_number']) ?></td>
                                <td class="p-3"><?= sanitize($inv['supplier_name']) ?></td>
                                <td class="p-3"><?= sanitize(formatDateAr($inv['invoice_date'])) ?></td>
                                <td class="p-3 font-num"><?= number_format(floatval($inv['total_amount']), 2) ?></td>
                                <td class="p-3 font-num"><?= number_format(floatval($inv['paid_amount']), 2) ?></td>
                                <td class="p-3 font-num font-bold <?= floatval($inv['due_amount']) > 0 ? 'text-red-600' : 'text-emerald-600' ?>"><?= number_format(floatval($inv['due_amount']), 2) ?></td>
                                <td class="p-3">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium
                                    <?= $inv['status'] === 'paid' ? 'bg-emerald-50 text-emerald-700' : ($inv['status'] === 'partial' ? 'bg-amber-50 text-amber-700' : 'bg-red-50 text-red-700') ?>">
                                        <?= $inv['status'] === 'paid' ? 'مسددة' : ($inv['status'] === 'partial' ? 'جزئي' : 'معلقة') ?>
                                    </span>
                                </td>
                                <td class="p-3">
                                    <?php if (floatval($inv['due_amount']) > 0): ?>
                                    <button type="button"
                                            onclick="openPayModal(<?= intval($inv['id']) ?>, <?= intval($inv['supplier_id']) ?>, '<?= sanitize($inv['invoice_number']) ?>', <?= floatval($inv['due_amount']) ?>)"
                                            class="bg-primary/10 text-primary px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-primary/20">
                                        سداد
                                    </button>
                                    <?php else: ?>
                                    <span class="text-xs text-slate-400">—</span>
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

<div id="pay-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white w-full max-w-md rounded-2xl border border-slate-200 shadow-2xl overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-900">سداد مورد</h3>
            <button type="button" onclick="closePayModal()" class="p-2 rounded-lg hover:bg-slate-100 text-slate-500">
                <span class="material-icons-outlined">close</span>
            </button>
        </div>
        <form method="POST" class="p-5 space-y-3">
            <input type="hidden" name="action" value="add_supplier_payment">
            <input type="hidden" name="purchase_invoice_id" id="pay-invoice-id" value="">
            <?php csrfInput(); ?>
            <p class="text-sm text-slate-600">الفاتورة: <span id="pay-invoice-num" class="font-num font-semibold"></span></p>
            <p class="text-sm text-slate-600">المتبقي: <span id="pay-due" class="font-num font-semibold text-red-600"></span></p>
            <div>
                <label class="block text-xs text-slate-500 mb-1">المبلغ</label>
                <input type="number" name="amount" id="pay-amount" min="0.01" step="0.01" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-num text-left" dir="ltr">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">تاريخ السداد</label>
                <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-num">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">طريقة السداد</label>
                <select name="payment_method" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm">
                    <option value="cash">نقدي</option>
                    <option value="card">بنك / بطاقة</option>
                    <option value="transfer">تحويل</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">ملاحظات</label>
                <input type="text" name="notes" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm">
            </div>
            <button type="submit" class="w-full bg-primary hover:bg-primary-dark text-white py-2.5 rounded-lg text-sm font-medium">تأكيد السداد</button>
        </form>
    </div>
</div>

<script>
const PRODUCT_OPTIONS = <?= json_encode(array_map(function($p) {
    return ['id' => intval($p['id']), 'name' => $p['name'], 'cost_price' => floatval($p['cost_price'])];
}, $products), JSON_UNESCAPED_UNICODE) ?>;

function productOptionsHtml() {
    let html = '<option value="">اختر الصنف</option>';
    PRODUCT_OPTIONS.forEach(p => {
        html += `<option value="${p.id}" data-cost="${p.cost_price}">${p.name}</option>`;
    });
    return html;
}

function addPurchaseRow() {
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td class="p-2">
            <select class="prod w-full bg-slate-50 border border-slate-200 rounded-lg px-2 py-1.5 text-xs">${productOptionsHtml()}</select>
        </td>
        <td class="p-2"><input type="number" class="qty w-full bg-slate-50 border border-slate-200 rounded-lg px-2 py-1.5 text-xs font-num" min="1" value="1" /></td>
        <td class="p-2"><input type="number" class="cost w-full bg-slate-50 border border-slate-200 rounded-lg px-2 py-1.5 text-xs font-num" min="0.01" step="0.01" value="0" /></td>
        <td class="p-2 font-num line-total">0.00</td>
        <td class="p-2"><button type="button" class="text-red-600 text-xs" onclick="this.closest('tr').remove(); recalcPurchaseTable();">حذف</button></td>
    `;
    document.getElementById('purchase-items-body').appendChild(tr);

    tr.querySelector('.prod').addEventListener('change', (e) => {
        const sel = e.target.options[e.target.selectedIndex];
        const cost = parseFloat(sel.getAttribute('data-cost') || '0');
        tr.querySelector('.cost').value = cost > 0 ? cost.toFixed(2) : '0';
        recalcPurchaseTable();
    });
    tr.querySelector('.qty').addEventListener('input', recalcPurchaseTable);
    tr.querySelector('.cost').addEventListener('input', recalcPurchaseTable);
}

function recalcPurchaseTable() {
    const rows = document.querySelectorAll('#purchase-items-body tr');
    let total = 0;
    const items = [];
    rows.forEach(row => {
        const productId = parseInt(row.querySelector('.prod').value || '0', 10);
        const qty = parseInt(row.querySelector('.qty').value || '0', 10);
        const cost = parseFloat(row.querySelector('.cost').value || '0');
        const line = Math.max(0, qty * cost);
        row.querySelector('.line-total').textContent = line.toFixed(2);
        total += line;
        if (productId > 0 && qty > 0 && cost > 0) {
            items.push({ product_id: productId, quantity: qty, unit_cost: cost });
        }
    });
    document.getElementById('purchase-total').textContent = total.toFixed(2);
    document.getElementById('items-json').value = JSON.stringify(items);
}

document.getElementById('purchase-form')?.addEventListener('submit', (e) => {
    recalcPurchaseTable();
    const items = JSON.parse(document.getElementById('items-json').value || '[]');
    if (!items.length) {
        e.preventDefault();
        alert('أضف صنفًا واحدًا على الأقل');
    }
});

function openPayModal(invoiceId, supplierId, invoiceNumber, due) {
    document.getElementById('pay-invoice-id').value = String(invoiceId);
    document.getElementById('pay-invoice-num').textContent = invoiceNumber;
    document.getElementById('pay-due').textContent = Number(due).toFixed(2);
    document.getElementById('pay-amount').value = Number(due).toFixed(2);
    document.getElementById('pay-modal').classList.remove('hidden');
}

function closePayModal() {
    document.getElementById('pay-modal').classList.add('hidden');
}

addPurchaseRow();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
