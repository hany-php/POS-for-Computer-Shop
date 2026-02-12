<?php
require_once __DIR__ . '/includes/bootstrap.php';
Auth::requireLogin();
$pageTitle = 'الصيانة';
$user = Auth::user();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $ticketNumber = $db->generateTicketNumber();
        $db->insert(
            "INSERT INTO maintenance_tickets (ticket_number, customer_name, customer_phone, device_type, device_brand, device_model, serial_number, problem_description, estimated_cost, technician_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$ticketNumber, $_POST['customer_name'], $_POST['customer_phone'], $_POST['device_type'], $_POST['device_brand'] ?? '', $_POST['device_model'] ?? '', $_POST['serial_number'] ?? '', $_POST['problem_description'], floatval($_POST['estimated_cost'] ?? 0), $_POST['technician_id'] ?: null]
        );
        setFlash('success', 'تم إنشاء تذكرة الصيانة بنجاح: ' . $ticketNumber);
        header('Location: maintenance.php');
        exit;
    }
    if ($_POST['action'] === 'update_status') {
        $newStatus = $_POST['status'];
        $ticketId = $_POST['ticket_id'];
        // If delivering, require actual_cost
        if ($newStatus === 'delivered' && isset($_POST['actual_cost'])) {
            $db->query("UPDATE maintenance_tickets SET status = ?, actual_cost = ?, discount = ?, notes = COALESCE(?, notes), updated_at = CURRENT_TIMESTAMP WHERE id = ?", 
                [$newStatus, floatval($_POST['actual_cost']), floatval($_POST['discount'] ?? 0), $_POST['delivery_notes'] ?? null, $ticketId]);
        } else {
            $db->query("UPDATE maintenance_tickets SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$newStatus, $ticketId]);
        }
        setFlash('success', 'تم تحديث الحالة بنجاح');
        header('Location: maintenance.php');
        exit;
    }
    if ($_POST['action'] === 'update_ticket') {
        $db->query(
            "UPDATE maintenance_tickets SET estimated_cost = ?, actual_cost = ?, discount = ?, notes = ?, technician_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
            [floatval($_POST['estimated_cost']), floatval($_POST['actual_cost']), floatval($_POST['discount'] ?? 0), $_POST['notes'] ?? '', $_POST['technician_id'] ?: null, $_POST['ticket_id']]
        );
        setFlash('success', 'تم تحديث بيانات التذكرة بنجاح');
        header('Location: maintenance.php');
        exit;
    }
}

$tickets = $db->fetchAll("SELECT mt.*, u.full_name as technician_name FROM maintenance_tickets mt LEFT JOIN users u ON mt.technician_id = u.id ORDER BY mt.created_at DESC");
$technicians = $db->fetchAll("SELECT id, full_name FROM users WHERE role = 'technician' AND is_active = 1");
$store = getStoreSettings();

include __DIR__ . '/includes/header.php';
?>

<div class="min-h-screen flex flex-col">
    <!-- Top Navigation -->
    <header class="bg-white border-b border-slate-200 h-16 flex items-center px-6 shadow-sm z-10 sticky top-0">
        <div class="flex items-center gap-4 w-full">
            <div class="flex items-center gap-3">
                <div class="bg-primary/10 p-2 rounded-lg text-primary">
                    <span class="material-icons-outlined">build</span>
                </div>
                <div>
                    <h1 class="font-bold text-lg leading-tight">خدمات الصيانة</h1>
                    <p class="text-xs text-slate-500 font-num">Maintenance Services</p>
                </div>
            </div>
            <div class="flex-grow"></div>
            <div class="flex items-center gap-3">
                <a href="pos.php" class="flex items-center gap-2 bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    <span class="material-icons-outlined text-base">point_of_sale</span>
                    <span>نقطة البيع</span>
                </a>
                <a href="logout.php" class="p-2 rounded-lg hover:bg-red-50 text-slate-400 hover:text-red-500 transition-colors">
                    <span class="material-icons-outlined">logout</span>
                </a>
            </div>
        </div>
    </header>

    <main class="flex-grow p-4 md:p-6">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- RIGHT: New Ticket Form -->
            <div class="lg:col-span-4 xl:col-span-3">
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="create">
                    
                    <!-- Customer Info -->
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                        <h2 class="text-base font-bold mb-4 flex items-center gap-2 text-primary">
                            <span class="material-icons-outlined text-sm">person</span>
                            بيانات العميل
                        </h2>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-500 mb-1">اسم العميل</label>
                                <input type="text" name="customer_name" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all" placeholder="الاسم الكامل">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500 mb-1">رقم الجوال</label>
                                <input type="tel" name="customer_phone" required dir="ltr" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-num focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all text-left" placeholder="05xxxxxxxx">
                            </div>
                        </div>
                    </div>

                    <!-- Device Info -->
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                        <h2 class="text-base font-bold mb-4 flex items-center gap-2 text-primary">
                            <span class="material-icons-outlined text-sm">devices</span>
                            بيانات الجهاز
                        </h2>
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1">نوع الجهاز</label>
                                    <select name="device_type" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all">
                                        <option value="كمبيوتر مكتبي">كمبيوتر مكتبي</option>
                                        <option value="لابتوب">لابتوب</option>
                                        <option value="طابعة">طابعة</option>
                                        <option value="شاشة">شاشة</option>
                                        <option value="كاميرا مراقبة">كاميرا مراقبة</option>
                                        <option value="DVR/NVR">DVR/NVR</option>
                                        <option value="جهاز شبكة">جهاز شبكة</option>
                                        <option value="أخرى">أخرى</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1">الماركة</label>
                                    <input type="text" name="device_brand" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all" placeholder="مثال: Dell">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500 mb-1">الموديل</label>
                                <input type="text" name="device_model" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all" placeholder="مثال: Inspiron 15">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500 mb-1">الرقم التسلسلي</label>
                                <input type="text" name="serial_number" dir="ltr" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-num focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all text-left" placeholder="Serial Number">
                            </div>
                        </div>
                    </div>

                    <!-- Problem & Cost -->
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                        <h2 class="text-base font-bold mb-4 flex items-center gap-2 text-primary">
                            <span class="material-icons-outlined text-sm">report_problem</span>
                            المشكلة والتكلفة
                        </h2>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-500 mb-1">وصف المشكلة</label>
                                <textarea name="problem_description" required rows="3" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all resize-none" placeholder="صف المشكلة بالتفصيل..."></textarea>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1">التكلفة المتوقعة</label>
                                    <div class="relative">
                                        <input type="number" name="estimated_cost" step="0.01" dir="ltr" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-num focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all text-left pl-14" placeholder="0.00">
                                        <span class="absolute left-3 top-2.5 text-slate-400 text-xs"><?= CURRENCY_EN ?></span>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1">تعيين فني</label>
                                    <select name="technician_id" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all">
                                        <option value="">بدون تعيين</option>
                                        <?php foreach ($technicians as $tech): ?>
                                        <option value="<?= $tech['id'] ?>"><?= sanitize($tech['full_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-3.5 px-4 rounded-xl shadow-lg shadow-primary/30 transition-all flex items-center justify-center gap-2">
                                <span class="material-icons-outlined">add_circle</span>
                                <span>إنشاء تذكرة صيانة</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- LEFT: Tickets List -->
            <div class="lg:col-span-8 xl:col-span-9">
                <!-- Status Filter Tabs -->
                <div class="flex gap-2 mb-4 overflow-x-auto pb-2">
                    <button onclick="filterTickets('all')" class="ticket-filter flex-shrink-0 px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium transition-all" data-status="all">الكل</button>
                    <button onclick="filterTickets('pending_inspection')" class="ticket-filter flex-shrink-0 px-4 py-2 bg-white border border-slate-200 text-slate-600 rounded-lg text-sm font-medium hover:bg-slate-50 transition-all" data-status="pending_inspection">قيد الفحص</button>
                    <button onclick="filterTickets('under_maintenance')" class="ticket-filter flex-shrink-0 px-4 py-2 bg-white border border-slate-200 text-slate-600 rounded-lg text-sm font-medium hover:bg-slate-50 transition-all" data-status="under_maintenance">تحت الصيانة</button>
                    <button onclick="filterTickets('ready_for_pickup')" class="ticket-filter flex-shrink-0 px-4 py-2 bg-white border border-slate-200 text-slate-600 rounded-lg text-sm font-medium hover:bg-slate-50 transition-all" data-status="ready_for_pickup">جاهز للاستلام</button>
                    <button onclick="filterTickets('delivered')" class="ticket-filter flex-shrink-0 px-4 py-2 bg-white border border-slate-200 text-slate-600 rounded-lg text-sm font-medium hover:bg-slate-50 transition-all" data-status="delivered">تم التسليم</button>
                </div>

                <!-- Tickets Table -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                        <h3 class="font-bold text-slate-800 flex items-center gap-2">
                            <span class="w-2 h-6 bg-primary rounded-full"></span>
                            تذاكر الصيانة
                        </h3>
                        <span class="bg-primary/10 text-primary text-xs font-num font-bold px-2 py-1 rounded-full"><?= count($tickets) ?> تذكرة</span>
                    </div>
                    <div class="overflow-auto">
                        <table class="w-full text-right">
                            <thead class="bg-slate-50 sticky top-0 z-10">
                                <tr>
                                    <th class="p-4 text-xs font-semibold text-slate-500 whitespace-nowrap">رقم التذكرة</th>
                                    <th class="p-4 text-xs font-semibold text-slate-500 whitespace-nowrap">العميل</th>
                                    <th class="p-4 text-xs font-semibold text-slate-500 whitespace-nowrap">الجهاز</th>
                                    <th class="p-4 text-xs font-semibold text-slate-500 whitespace-nowrap">المشكلة</th>
                                    <th class="p-4 text-xs font-semibold text-slate-500 whitespace-nowrap">الفني</th>
                                    <th class="p-4 text-xs font-semibold text-slate-500 whitespace-nowrap">التكلفة المتوقعة</th>
                                    <th class="p-4 text-xs font-semibold text-slate-500 whitespace-nowrap">التكلفة الفعلية</th>
                                    <th class="p-4 text-xs font-semibold text-slate-500 whitespace-nowrap">الحالة</th>
                                    <th class="p-4 text-xs font-semibold text-slate-500 whitespace-nowrap">إجراءات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (empty($tickets)): ?>
                                <tr><td colspan="9" class="p-8 text-center text-slate-400">لا توجد تذاكر صيانة بعد</td></tr>
                                <?php endif; ?>
                                <?php foreach ($tickets as $t): ?>
                                <tr class="ticket-row hover:bg-slate-50 transition-colors group" data-status="<?= $t['status'] ?>">
                                    <td class="p-4 font-num text-sm font-medium text-slate-900"><?= $t['ticket_number'] ?></td>
                                    <td class="p-4 text-sm">
                                        <p class="font-medium"><?= sanitize($t['customer_name']) ?></p>
                                        <p class="text-xs text-slate-500 font-num"><?= sanitize($t['customer_phone']) ?></p>
                                    </td>
                                    <td class="p-4 text-sm">
                                        <p class="font-medium"><?= sanitize($t['device_type']) ?></p>
                                        <p class="text-xs text-slate-500"><?= sanitize($t['device_brand'] . ' ' . $t['device_model']) ?></p>
                                    </td>
                                    <td class="p-4 text-sm text-slate-600 max-w-[200px] truncate"><?= sanitize($t['problem_description']) ?></td>
                                    <td class="p-4 text-sm text-slate-600"><?= sanitize($t['technician_name'] ?? 'غير معين') ?></td>
                                    <td class="p-4 text-sm font-num text-slate-600"><?= number_format($t['estimated_cost'], 0) ?> <?= CURRENCY ?></td>
                                    <td class="p-4 text-sm font-num font-bold <?= $t['actual_cost'] > 0 ? 'text-green-700' : 'text-slate-400' ?>"><?= $t['actual_cost'] > 0 ? number_format($t['actual_cost'], 0) . ' ' . CURRENCY : '—' ?></td>
                                    <td class="p-4">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border <?= getMaintenanceStatusColor($t['status']) ?>">
                                            <?= getMaintenanceStatusAr($t['status']) ?>
                                        </span>
                                    </td>
                                    <td class="p-4">
                                        <div class="flex items-center gap-1">
                                        <?php if ($t['status'] !== 'delivered' && $t['status'] !== 'cancelled'): ?>
                                            <!-- Edit Button -->
                                            <button onclick='openEditTicket(<?= json_encode($t, JSON_UNESCAPED_UNICODE) ?>)' class="p-1.5 hover:bg-blue-50 rounded-lg text-slate-400 hover:text-primary transition-colors" title="تعديل التكلفة">
                                                <span class="material-icons-outlined text-[18px]">edit</span>
                                            </button>
                                            <!-- Status Change -->
                                            <?php if ($t['status'] === 'ready_for_pickup'): ?>
                                            <button onclick='openDeliverModal(<?= json_encode($t, JSON_UNESCAPED_UNICODE) ?>)' class="p-1.5 hover:bg-green-50 rounded-lg text-slate-400 hover:text-green-600 transition-colors" title="تسليم">
                                                <span class="material-icons-outlined text-[18px]">check_circle</span>
                                            </button>
                                            <?php else: ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                                                <select name="status" onchange="this.form.submit()" class="text-xs bg-white border border-slate-200 rounded-lg px-2 py-1 focus:outline-none focus:border-primary cursor-pointer">
                                                    <option value="">تغيير...</option>
                                                    <?php if ($t['status'] === 'pending_inspection'): ?>
                                                    <option value="under_maintenance">تحت الصيانة</option>
                                                    <?php endif; ?>
                                                    <?php if ($t['status'] === 'under_maintenance'): ?>
                                                    <option value="ready_for_pickup">جاهز للاستلام</option>
                                                    <?php endif; ?>
                                                    <option value="cancelled">إلغاء</option>
                                                </select>
                                            </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                            <!-- Print Button (always visible) -->
                                            <button onclick='openReceipt(<?= json_encode($t, JSON_UNESCAPED_UNICODE) ?>)' class="p-1.5 hover:bg-purple-50 rounded-lg text-slate-400 hover:text-purple-600 transition-colors" title="طباعة فاتورة">
                                                <span class="material-icons-outlined text-[18px]">print</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
function filterTickets(status) {
    document.querySelectorAll('.ticket-filter').forEach(btn => {
        if (btn.dataset.status === status) {
            btn.className = 'ticket-filter flex-shrink-0 px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium transition-all';
        } else {
            btn.className = 'ticket-filter flex-shrink-0 px-4 py-2 bg-white border border-slate-200 text-slate-600 rounded-lg text-sm font-medium hover:bg-slate-50 transition-all';
        }
    });
    document.querySelectorAll('.ticket-row').forEach(row => {
        row.style.display = (status === 'all' || row.dataset.status === status) ? '' : 'none';
    });
}

// Edit Ticket Modal
function openEditTicket(t) {
    document.getElementById('edit-ticket-id').value = t.id;
    document.getElementById('edit-ticket-num').textContent = t.ticket_number;
    document.getElementById('edit-estimated-cost').value = t.estimated_cost || 0;
    document.getElementById('edit-actual-cost').value = t.actual_cost || 0;
    document.getElementById('edit-discount').value = t.discount || 0;
    document.getElementById('edit-notes').value = t.notes || '';
    document.getElementById('edit-technician').value = t.technician_id || '';
    document.getElementById('edit-ticket-modal').classList.remove('hidden');
}

function closeEditTicket() {
    document.getElementById('edit-ticket-modal').classList.add('hidden');
}

// Deliver Modal
function openDeliverModal(t) {
    document.getElementById('deliver-ticket-id').value = t.id;
    document.getElementById('deliver-ticket-num').textContent = t.ticket_number;
    document.getElementById('deliver-customer').textContent = t.customer_name;
    document.getElementById('deliver-device').textContent = t.device_type + ' ' + (t.device_brand || '') + ' ' + (t.device_model || '');
    document.getElementById('deliver-estimated').textContent = parseFloat(t.estimated_cost || 0).toLocaleString() + ' <?= CURRENCY ?>';
    document.getElementById('deliver-actual-cost').value = t.actual_cost > 0 ? t.actual_cost : t.estimated_cost;
    document.getElementById('deliver-discount').value = t.discount || 0;
    document.getElementById('deliver-modal').classList.remove('hidden');
    calcDeliverTotal();
}

function calcDeliverTotal() {
    const cost = parseFloat(document.getElementById('deliver-actual-cost').value) || 0;
    const disc = parseFloat(document.getElementById('deliver-discount').value) || 0;
    const total = Math.max(0, cost - disc);
    document.getElementById('deliver-final-total').textContent = total.toLocaleString() + ' <?= CURRENCY ?>';
}

function closeDeliverModal() {
    document.getElementById('deliver-modal').classList.add('hidden');
}

// Receipt Modal
function openReceipt(t) {
    const currency = '<?= sanitize($store['currency']) ?>';
    const est = parseFloat(t.estimated_cost) || 0;
    const act = parseFloat(t.actual_cost) || 0;
    const disc = parseFloat(t.discount) || 0;
    const cost = act > 0 ? act : est;
    const total = Math.max(0, cost - disc);
    const now = new Date();
    const dateStr = now.toLocaleDateString('ar-EG') + ' ' + now.toLocaleTimeString('ar-EG', {hour:'2-digit',minute:'2-digit'});

    let html = `
    <div style="text-align:center;border-bottom:1px dashed #999;padding-bottom:10px;margin-bottom:10px">
        <?php if (!empty($store['store_logo_url'])): ?>
        <img src="<?= sanitize($store['store_logo_url']) ?>" style="max-height:60px;margin:0 auto 5px;display:block">
        <?php else: ?>
        <div style="width:40px;height:40px;background:#000;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 5px"><span class="material-icons-outlined" style="font-size:24px">computer</span></div>
        <?php endif; ?>
        <h2 style="margin:0;font-size:16px;font-weight:800"><?= sanitize($store['store_name']) ?></h2>
        <?php if(!empty($store['store_address'])): ?><p style="margin:2px 0;font-size:11px;color:#666"><?= sanitize($store['store_address']) ?></p><?php endif; ?>
        <?php if(!empty($store['store_phone'])): ?><p style="margin:2px 0;font-size:11px;color:#666;direction:ltr"><?= sanitize($store['store_phone']) ?></p><?php endif; ?>
    </div>
    <p style="text-align:center;font-weight:700;font-size:14px;margin:8px 0">فاتورة صيانة</p>
    <table style="width:100%;font-size:12px;border-collapse:collapse">
        <tr><td style="padding:3px 0;color:#888">رقم التذكرة</td><td style="text-align:left;direction:ltr;font-weight:600">${t.ticket_number}</td></tr>
        <tr><td style="padding:3px 0;color:#888">التاريخ</td><td style="text-align:left;font-weight:600">${dateStr}</td></tr>
    </table>
    <div style="border-top:1px dashed #ccc;margin:8px 0"></div>
    <table style="width:100%;font-size:12px;border-collapse:collapse">
        <tr><td style="padding:3px 0;color:#888">العميل</td><td style="text-align:left;font-weight:600">${t.customer_name}</td></tr>
        <tr><td style="padding:3px 0;color:#888">التليفون</td><td style="text-align:left;direction:ltr;font-weight:600">${t.customer_phone}</td></tr>
        <tr><td style="padding:3px 0;color:#888">الجهاز</td><td style="text-align:left;font-weight:600">${t.device_type} ${t.device_brand||''} ${t.device_model||''}</td></tr>
    </table>
    <div style="border-top:1px dashed #ccc;margin:8px 0"></div>
    <p style="font-size:11px;color:#555;line-height:1.6;margin:4px 0">${t.problem_description}</p>
    <div style="border-top:1px dashed #ccc;margin:8px 0"></div>
    <table style="width:100%;font-size:12px;border-collapse:collapse">
        <tr><td style="padding:4px 0">التكلفة المتوقعة</td><td style="text-align:left;direction:ltr">${est.toLocaleString()} ${currency}</td></tr>
        ${act > 0 ? '<tr><td style="padding:4px 0">التكلفة الفعلية</td><td style="text-align:left;direction:ltr">' + act.toLocaleString() + ' ' + currency + '</td></tr>' : ''}
        ${disc > 0 ? '<tr style="color:#c00"><td style="padding:4px 0">خصم</td><td style="text-align:left;direction:ltr">- ' + disc.toLocaleString() + ' ' + currency + '</td></tr>' : ''}
    </table>
    <div style="border-top:2px solid #333;margin:8px 0"></div>
    <table style="width:100%;font-size:15px;font-weight:800">
        <tr><td>الإجمالي</td><td style="text-align:left;direction:ltr">${total.toLocaleString()} ${currency}</td></tr>
    </table>
    <div style="border-top:1px dashed #ccc;margin:10px 0"></div>
    <p style="text-align:center;font-size:11px;color:#888;margin:8px 0"><?= sanitize($store['receipt_footer']) ?></p>
    `;

    document.getElementById('receipt-content').innerHTML = html;
    document.getElementById('receipt-modal').classList.remove('hidden');
}

function closeReceipt() {
    document.getElementById('receipt-modal').classList.add('hidden');
}

function printReceipt() {
    window.print();
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { closeEditTicket(); closeDeliverModal(); closeReceipt(); }
});
</script>

<!-- Edit Ticket Modal -->
<div id="edit-ticket-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden border border-slate-100">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h2 class="text-lg font-bold flex items-center gap-2">
                <span class="material-icons-outlined text-primary">edit</span>
                تعديل التذكرة <span class="font-num text-primary" id="edit-ticket-num"></span>
            </h2>
            <button onclick="closeEditTicket()" class="p-2 rounded-full hover:bg-slate-100 text-slate-500"><span class="material-icons-outlined">close</span></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="update_ticket">
            <input type="hidden" name="ticket_id" id="edit-ticket-id">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">التكلفة المتوقعة</label>
                    <div class="relative">
                        <input type="number" name="estimated_cost" id="edit-estimated-cost" step="1" dir="ltr" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2.5 text-sm font-num focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary text-left pl-14">
                        <span class="absolute left-3 top-3 text-slate-400 text-xs"><?= CURRENCY_EN ?></span>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">التكلفة الفعلية</label>
                    <div class="relative">
                        <input type="number" name="actual_cost" id="edit-actual-cost" step="1" dir="ltr" class="w-full bg-green-50 border border-green-200 rounded-lg px-3 py-2.5 text-sm font-num font-bold focus:outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 text-left pl-14 text-green-700">
                        <span class="absolute left-3 top-3 text-green-500 text-xs"><?= CURRENCY_EN ?></span>
                    </div>
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">خصم</label>
                <div class="relative">
                    <input type="number" name="discount" id="edit-discount" step="1" dir="ltr" class="w-full bg-red-50 border border-red-200 rounded-lg px-3 py-2.5 text-sm font-num focus:outline-none focus:border-red-400 focus:ring-1 focus:ring-red-400 text-left pl-14 text-red-600">
                    <span class="absolute left-3 top-3 text-red-400 text-xs"><?= CURRENCY_EN ?></span>
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">تعيين فني</label>
                <select name="technician_id" id="edit-technician" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                    <option value="">بدون تعيين</option>
                    <?php foreach ($technicians as $tech): ?>
                    <option value="<?= $tech['id'] ?>"><?= sanitize($tech['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">ملاحظات</label>
                <textarea name="notes" id="edit-notes" rows="3" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary resize-none" placeholder="ملاحظات عن الصيانة، القطع المستبدلة..."></textarea>
            </div>
            <button type="submit" class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-3 rounded-xl shadow-lg shadow-primary/30 transition-all flex items-center justify-center gap-2">
                <span class="material-icons-outlined text-base">save</span>
                حفظ التعديلات
            </button>
        </form>
    </div>
</div>

<!-- Deliver Modal -->
<div id="deliver-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden border border-slate-100">
        <div class="p-6 border-b border-green-100 bg-green-50/50 flex justify-between items-center">
            <h2 class="text-lg font-bold text-green-800 flex items-center gap-2">
                <span class="material-icons-outlined">check_circle</span>
                تسليم الجهاز - <span class="font-num" id="deliver-ticket-num"></span>
            </h2>
            <button onclick="closeDeliverModal()" class="p-2 rounded-full hover:bg-green-100 text-green-600"><span class="material-icons-outlined">close</span></button>
        </div>
        <form method="POST" class="p-6 space-y-5">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="status" value="delivered">
            <input type="hidden" name="ticket_id" id="deliver-ticket-id">
            
            <div class="bg-slate-50 rounded-lg p-4 text-sm space-y-2">
                <div class="flex justify-between"><span class="text-slate-500">العميل</span><span class="font-medium" id="deliver-customer"></span></div>
                <div class="flex justify-between"><span class="text-slate-500">الجهاز</span><span class="font-medium" id="deliver-device"></span></div>
                <div class="flex justify-between"><span class="text-slate-500">التكلفة المتوقعة</span><span class="font-num font-medium" id="deliver-estimated"></span></div>
            </div>
            
            <div>
                <label class="block text-sm font-bold text-green-800 mb-2 flex items-center gap-1">
                    <span class="material-icons-outlined text-base">payments</span>
                    التكلفة النهائية (المبلغ المحصّل) *
                </label>
                <div class="relative">
                    <input type="number" name="actual_cost" id="deliver-actual-cost" required step="1" dir="ltr" class="w-full bg-green-50 border-2 border-green-300 rounded-xl px-4 py-4 text-xl font-num font-bold focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-200 text-left pl-16 text-green-700" oninput="calcDeliverTotal()">
                    <span class="absolute left-4 top-4 text-green-500 font-medium text-lg"><?= CURRENCY_EN ?></span>
                </div>
            </div>
            
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">خصم (اختياري)</label>
                <div class="relative">
                    <input type="number" name="discount" id="deliver-discount" step="1" value="0" dir="ltr" class="w-full bg-red-50 border border-red-200 rounded-lg px-3 py-2.5 text-sm font-num focus:outline-none focus:border-red-400 focus:ring-1 focus:ring-red-400 text-left pl-14 text-red-600" oninput="calcDeliverTotal()">
                    <span class="absolute left-3 top-3 text-red-400 text-xs"><?= CURRENCY_EN ?></span>
                </div>
            </div>
            
            <div class="bg-green-50 border border-green-200 rounded-lg p-3 text-center">
                <span class="text-xs text-green-600">الإجمالي بعد الخصم</span>
                <p class="text-2xl font-num font-bold text-green-700" id="deliver-final-total">0</p>
            </div>
            
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">ملاحظات التسليم</label>
                <textarea name="delivery_notes" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-green-500 resize-none" placeholder="ملاحظات إضافية عند التسليم..."></textarea>
            </div>
            
            <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-green-500/30 transition-all flex items-center justify-center gap-2 text-base">
                <span class="material-icons-outlined">check_circle</span>
                تأكيد التسليم والتحصيل
            </button>
        </form>
    </div>
</div>

<!-- Receipt Modal (inline, same page) -->
<div id="receipt-modal" class="hidden fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl overflow-hidden max-w-sm w-full mx-4">
        <!-- Modal Controls (hidden on print) -->
        <div class="print-hide flex items-center justify-between p-4 border-b border-slate-100 bg-slate-50">
            <h3 class="font-bold text-slate-700 flex items-center gap-2">
                <span class="material-icons-outlined text-purple-500">receipt</span>
                معاينة الفاتورة
            </h3>
            <div class="flex items-center gap-2">
                <button onclick="printReceipt()" class="bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-1 transition-colors">
                    <span class="material-icons-outlined text-base">print</span>
                    طباعة
                </button>
                <button onclick="closeReceipt()" class="p-2 rounded-full hover:bg-slate-200 text-slate-500">
                    <span class="material-icons-outlined">close</span>
                </button>
            </div>
        </div>
        <!-- Receipt Body (printed) -->
        <div id="receipt-content" class="receipt-body" dir="rtl" style="font-family:'Cairo',sans-serif;padding:16px;max-width:300px;margin:0 auto;font-size:13px;color:#222">
        </div>
    </div>
</div>

<!-- Print CSS: only print the receipt -->
<style>
@media print {
    body * { visibility: hidden !important; }
    #receipt-modal,
    #receipt-modal .receipt-body,
    #receipt-modal .receipt-body * {
        visibility: visible !important;
    }
    #receipt-modal {
        position: fixed !important; inset: 0 !important;
        display: flex !important; align-items: flex-start !important; justify-content: center !important;
        background: white !important; z-index: 99999 !important;
        backdrop-filter: none !important;
    }
    #receipt-modal > div {
        box-shadow: none !important; border: none !important; border-radius: 0 !important;
        max-width: 80mm !important; width: 80mm !important; margin: 0 auto !important;
    }
    .print-hide { display: none !important; }
    .receipt-body { padding: 4mm !important; }
    @page { size: 80mm auto; margin: 2mm; }
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
