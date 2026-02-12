<?php
/**
 * Maintenance Invoice / فاتورة صيانة
 * Print-friendly page for maintenance ticket invoice
 */
require_once __DIR__ . '/includes/bootstrap.php';
Auth::requireLogin();

$ticketId = intval($_GET['id'] ?? 0);
if (!$ticketId) { header('Location: maintenance.php'); exit; }

$ticket = $db->fetchOne("SELECT mt.*, u.full_name as technician_name FROM maintenance_tickets mt LEFT JOIN users u ON mt.technician_id = u.id WHERE mt.id = ?", [$ticketId]);
if (!$ticket) { header('Location: maintenance.php'); exit; }

$store = getStoreSettings();

// Calculated values
$estimatedCost = floatval($ticket['estimated_cost']);
$actualCost = floatval($ticket['actual_cost']);
$discount = floatval($ticket['discount']);
$finalCost = $actualCost > 0 ? $actualCost : $estimatedCost;
$finalAfterDiscount = $finalCost - $discount;
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فاتورة صيانة - <?= sanitize($ticket['ticket_number']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Cairo', sans-serif; background: #f6f7f8; color: #1e293b; }
        .font-num { font-family: 'Space Grotosk', monospace; }
        
        /* Print controls */
        .print-controls { 
            background: white; padding: 12px 24px; border-bottom: 1px solid #e2e8f0;
            display: flex; align-items: center; gap: 12px; position: sticky; top: 0; z-index: 50;
        }
        .btn { padding: 8px 20px; border-radius: 10px; font-weight: 700; font-size: 14px; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 6px; font-family: 'Cairo', sans-serif; }
        .btn-primary { background: #137fec; color: white; }
        .btn-primary:hover { background: #0e6ad0; }
        .btn-outline { background: white; color: #64748b; border: 1px solid #e2e8f0; }
        .btn-outline:hover { background: #f8fafc; }
        
        /* Invoice */
        .invoice-container { max-width: 800px; margin: 24px auto; padding: 0 16px; }
        .invoice { background: white; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        
        /* Header */
        .invoice-header { padding: 32px; border-bottom: 2px solid #f1f5f9; text-align: center; }
        .invoice-header .logo { height: 48px; margin-bottom: 8px; }
        .invoice-header h1 { font-size: 22px; font-weight: 800; color: #0f172a; }
        .invoice-header .subtitle { font-size: 13px; color: #64748b; margin-top: 4px; }
        .invoice-header .contact { font-size: 12px; color: #94a3b8; margin-top: 8px; font-family: 'Space Grotesk', monospace; }
        
        /* Invoice Meta */
        .invoice-meta { display: flex; justify-content: space-between; padding: 24px 32px; background: #f8fafc; border-bottom: 1px solid #f1f5f9; }
        .meta-group { }
        .meta-label { font-size: 11px; color: #94a3b8; font-weight: 600; text-transform: uppercase; margin-bottom: 4px; }
        .meta-value { font-size: 14px; font-weight: 700; color: #1e293b; }
        
        /* Sections */
        .section { padding: 24px 32px; border-bottom: 1px solid #f1f5f9; }
        .section-title { font-size: 11px; font-weight: 700; color: #137fec; text-transform: uppercase; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; letter-spacing: 0.5px; }
        .section-title::after { content: ''; flex: 1; height: 1px; background: #e2e8f0; }
        
        /* Info Grid */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .info-item { }
        .info-item .label { font-size: 11px; color: #94a3b8; }
        .info-item .value { font-size: 14px; font-weight: 600; }
        
        /* Description */
        .description { background: #f8fafc; border-radius: 8px; padding: 16px; font-size: 14px; line-height: 1.8; }
        
        /* Cost Table */
        .cost-table { width: 100%; border-collapse: collapse; }
        .cost-table td { padding: 12px 0; font-size: 14px; border-bottom: 1px solid #f1f5f9; }
        .cost-table td:first-child { color: #64748b; }
        .cost-table td:last-child { text-align: left; font-weight: 600; font-family: 'Space Grotesk', monospace; }
        .cost-table tr.discount td { color: #dc2626; }
        .cost-table tr.total { border-top: 2px solid #1e293b; }
        .cost-table tr.total td { font-size: 18px; font-weight: 800; color: #137fec; padding-top: 16px; }
        
        /* Footer */
        .invoice-footer { padding: 24px 32px; text-align: center; background: #f8fafc; }
        .invoice-footer .footer-text { font-size: 13px; color: #94a3b8; }
        .invoice-footer .thank-you { font-size: 16px; font-weight: 700; color: #1e293b; margin-bottom: 4px; }
        
        /* Status Badge */
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-under { background: #dbeafe; color: #1e40af; }
        .status-ready { background: #d1fae5; color: #065f46; }
        .status-delivered { background: #f1f5f9; color: #475569; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        
        /* Signature */
        .signature-area { display: flex; justify-content: space-between; margin-top: 32px; padding-top: 16px; }
        .signature-box { text-align: center; width: 200px; }
        .signature-line { border-top: 1px dashed #cbd5e1; margin-top: 48px; padding-top: 8px; font-size: 12px; color: #94a3b8; }
        
        /* Print Styles */
        @media print {
            body { background: white; }
            .print-controls { display: none !important; }
            .invoice-container { margin: 0; padding: 0; max-width: 100%; }
            .invoice { border: none; border-radius: 0; box-shadow: none; }
            @page { margin: 10mm; size: A4; }
        }
    </style>
</head>
<body>
    <!-- Print Controls (hidden when printing) -->
    <div class="print-controls">
        <button onclick="window.print()" class="btn btn-primary">
            <span class="material-icons-outlined" style="font-size:18px">print</span>
            طباعة الفاتورة
        </button>
        <button onclick="history.back()" class="btn btn-outline">
            <span class="material-icons-outlined" style="font-size:18px">arrow_forward</span>
            رجوع
        </button>
    </div>

    <div class="invoice-container">
        <div class="invoice">
            <!-- Header -->
            <div class="invoice-header">
                <?php if (!empty($store['store_logo_url'])): ?>
                <img src="<?= sanitize($store['store_logo_url']) ?>" class="logo" alt="Logo">
                <?php endif; ?>
                <h1><?= sanitize($store['store_name']) ?></h1>
                <?php if (!empty($store['store_address'])): ?>
                <p class="subtitle"><?= sanitize($store['store_address']) ?></p>
                <?php endif; ?>
                <p class="contact">
                    <?php if (!empty($store['store_phone'])): ?>
                    📞 <?= sanitize($store['store_phone']) ?>
                    <?php endif; ?>
                    <?php if (!empty($store['store_email'])): ?>
                    &nbsp;&nbsp;✉ <?= sanitize($store['store_email']) ?>
                    <?php endif; ?>
                </p>
            </div>

            <!-- Invoice Meta -->
            <div class="invoice-meta">
                <div class="meta-group">
                    <div class="meta-label">رقم التذكرة</div>
                    <div class="meta-value font-num"><?= sanitize($ticket['ticket_number']) ?></div>
                </div>
                <div class="meta-group">
                    <div class="meta-label">تاريخ الاستلام</div>
                    <div class="meta-value font-num"><?= formatDateAr($ticket['created_at']) ?></div>
                </div>
                <div class="meta-group">
                    <div class="meta-label">الحالة</div>
                    <div class="meta-value">
                        <?php
                        $statusClass = [
                            'pending_inspection' => 'status-pending',
                            'under_maintenance' => 'status-under',
                            'ready_for_pickup' => 'status-ready',
                            'delivered' => 'status-delivered',
                            'cancelled' => 'status-cancelled',
                        ][$ticket['status']] ?? '';
                        ?>
                        <span class="status-badge <?= $statusClass ?>"><?= getMaintenanceStatusAr($ticket['status']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Customer Info -->
            <div class="section">
                <div class="section-title">بيانات العميل</div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="label">الاسم</div>
                        <div class="value"><?= sanitize($ticket['customer_name']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">رقم التليفون</div>
                        <div class="value font-num"><?= sanitize($ticket['customer_phone']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Device Info -->
            <div class="section">
                <div class="section-title">بيانات الجهاز</div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="label">نوع الجهاز</div>
                        <div class="value"><?= sanitize($ticket['device_type']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">الماركة / الموديل</div>
                        <div class="value"><?= sanitize(trim($ticket['device_brand'] . ' ' . $ticket['device_model'])) ?: '—' ?></div>
                    </div>
                    <?php if (!empty($ticket['serial_number'])): ?>
                    <div class="info-item">
                        <div class="label">الرقم التسلسلي</div>
                        <div class="value font-num"><?= sanitize($ticket['serial_number']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($ticket['technician_name'])): ?>
                    <div class="info-item">
                        <div class="label">الفني المسؤول</div>
                        <div class="value"><?= sanitize($ticket['technician_name']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Problem Description -->
            <div class="section">
                <div class="section-title">وصف المشكلة</div>
                <div class="description"><?= nl2br(sanitize($ticket['problem_description'])) ?></div>
            </div>

            <?php if (!empty($ticket['notes'])): ?>
            <div class="section">
                <div class="section-title">ملاحظات الصيانة</div>
                <div class="description"><?= nl2br(sanitize($ticket['notes'])) ?></div>
            </div>
            <?php endif; ?>

            <!-- Cost Breakdown -->
            <div class="section">
                <div class="section-title">التكلفة</div>
                <table class="cost-table">
                    <tr>
                        <td>التكلفة المتوقعة</td>
                        <td><?= number_format($estimatedCost, 0) ?> <?= sanitize($store['currency']) ?></td>
                    </tr>
                    <?php if ($actualCost > 0): ?>
                    <tr>
                        <td>التكلفة الفعلية</td>
                        <td><?= number_format($actualCost, 0) ?> <?= sanitize($store['currency']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($discount > 0): ?>
                    <tr class="discount">
                        <td>خصم</td>
                        <td>- <?= number_format($discount, 0) ?> <?= sanitize($store['currency']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="total">
                        <td>الإجمالي المطلوب</td>
                        <td><?= number_format($finalAfterDiscount, 0) ?> <?= sanitize($store['currency']) ?></td>
                    </tr>
                </table>
            </div>

            <!-- Signature -->
            <div class="section" style="border-bottom: none;">
                <div class="signature-area">
                    <div class="signature-box">
                        <div class="signature-line">توقيع الفني</div>
                    </div>
                    <div class="signature-box">
                        <div class="signature-line">توقيع العميل</div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="invoice-footer">
                <p class="thank-you"><?= sanitize($store['receipt_footer']) ?></p>
                <p class="footer-text font-num"><?= date('Y/m/d H:i') ?></p>
            </div>
        </div>
    </div>
</body>
</html>
