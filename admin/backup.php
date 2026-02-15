<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole(['admin']);
$pageTitle = 'النسخ الاحتياطي';
$user = Auth::user();

$dbPath = __DIR__ . '/../database/pos.db';
$backupDir = __DIR__ . '/../database/backups/';
if (!is_dir($backupDir)) mkdir($backupDir, 0777, true);

function createBackupFile($dbPath, $backupDir, $prefix = 'backup_') {
    $filename = $prefix . date('Y-m-d_H-i-s') . '.db';
    return copy($dbPath, $backupDir . $filename) ? $filename : null;
}

function pruneBackups($backupDir, $keepCount) {
    $keepCount = max(3, intval($keepCount));
    $files = [];
    foreach (scandir($backupDir, SCANDIR_SORT_DESCENDING) as $f) {
        if (pathinfo($f, PATHINFO_EXTENSION) === 'db') $files[] = $f;
    }
    $deleted = 0;
    for ($i = $keepCount; $i < count($files); $i++) {
        $path = $backupDir . $files[$i];
        if (is_file($path) && @unlink($path)) $deleted++;
    }
    return $deleted;
}

function readTailLines($path, $maxLines = 25) {
    if (!is_file($path)) return [];
    $size = filesize($path);
    $offset = max(0, $size - 24000);
    $content = @file_get_contents($path, false, null, $offset);
    if ($content === false) return [];
    $lines = preg_split('/\R/', trim($content));
    if (!$lines) return [];
    return array_slice($lines, -1 * max(1, intval($maxLines)));
}

$settingsRows = $db->fetchAll("SELECT key, value FROM settings WHERE key IN ('backup_auto_enabled','backup_frequency_hours','backup_keep_count','last_auto_backup_at')");
$settingMap = [];
foreach ($settingsRows as $sr) $settingMap[$sr['key']] = $sr['value'];

$backupAutoEnabled = in_array(strtolower(trim((string)($settingMap['backup_auto_enabled'] ?? '1'))), ['1', 'true', 'yes', 'on'], true);
$backupFrequencyHours = max(1, min(168, intval($settingMap['backup_frequency_hours'] ?? 24)));
$backupKeepCount = max(3, min(200, intval($settingMap['backup_keep_count'] ?? 20)));
$lastAutoBackupAt = trim((string)($settingMap['last_auto_backup_at'] ?? ''));
$autoBackupRan = false;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $backupAutoEnabled) {
    $isDue = true;
    if ($lastAutoBackupAt !== '') {
        $lastTs = strtotime($lastAutoBackupAt);
        if ($lastTs) $isDue = (time() - $lastTs) >= ($backupFrequencyHours * 3600);
    }
    if ($isDue) {
        $autoFile = createBackupFile($dbPath, $backupDir, 'auto_backup_');
        if ($autoFile) {
            $db->query("UPDATE settings SET value = ?, updated_at = CURRENT_TIMESTAMP WHERE key = 'last_auto_backup_at'", [date('Y-m-d H:i:s')]);
            $lastAutoBackupAt = date('Y-m-d H:i:s');
            $deleted = pruneBackups($backupDir, $backupKeepCount);
            $autoBackupRan = true;
            logActivity('نسخ احتياطي تلقائي', 'system', null, $autoFile . ($deleted > 0 ? " - pruned:$deleted" : ''));
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCsrfTokenOrFail();
    if ($_POST['action'] === 'backup') {
        $filename = createBackupFile($dbPath, $backupDir, 'backup_');
        if ($filename) {
            $deleted = pruneBackups($backupDir, $backupKeepCount);
            logActivity('نسخ احتياطي', 'system', null, $filename . ($deleted > 0 ? " - pruned:$deleted" : ''));
            setFlash('success', 'تم إنشاء نسخة احتياطية: ' . $filename);
        } else {
            setFlash('error', 'فشل إنشاء النسخة الاحتياطية');
        }
        header('Location: backup.php');
        exit;
    }

    if ($_POST['action'] === 'optimize') {
        try {
            $pdo = $db->getConnection();
            $pdo->exec("ANALYZE");
            $pdo->exec("VACUUM");
            logActivity('تحسين قاعدة البيانات', 'system', null, 'ANALYZE + VACUUM');
            setFlash('success', 'تم تحسين قاعدة البيانات بنجاح');
        } catch (Exception $e) {
            setFlash('error', 'فشل تحسين قاعدة البيانات: ' . $e->getMessage());
        }
        header('Location: backup.php');
        exit;
    }

    if ($_POST['action'] === 'save_schedule') {
        $enabled = isset($_POST['backup_auto_enabled']) ? '1' : '0';
        $frequency = max(1, min(168, intval($_POST['backup_frequency_hours'] ?? 24)));
        $keep = max(3, min(200, intval($_POST['backup_keep_count'] ?? 20)));
        $db->query("UPDATE settings SET value = ?, updated_at = CURRENT_TIMESTAMP WHERE key = 'backup_auto_enabled'", [$enabled]);
        $db->query("UPDATE settings SET value = ?, updated_at = CURRENT_TIMESTAMP WHERE key = 'backup_frequency_hours'", [(string)$frequency]);
        $db->query("UPDATE settings SET value = ?, updated_at = CURRENT_TIMESTAMP WHERE key = 'backup_keep_count'", [(string)$keep]);
        logActivity('تحديث جدولة النسخ الاحتياطي', 'system', null, "enabled:$enabled, every:$frequency h, keep:$keep");
        setFlash('success', 'تم حفظ إعدادات الجدولة التلقائية');
        header('Location: backup.php');
        exit;
    }

    if ($_POST['action'] === 'download') {
        $file = $backupDir . basename($_POST['filename']);
        if (file_exists($file)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file) . '"');
            header('Content-Length: ' . filesize($file));
            readfile($file);
            exit;
        }
    }

    if ($_POST['action'] === 'restore') {
        if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
            $safetyName = 'pre_restore_' . date('Y-m-d_H-i-s') . '.db';
            copy($dbPath, $backupDir . $safetyName);
            if (copy($_FILES['backup_file']['tmp_name'], $dbPath)) {
                logActivity('استعادة نسخة احتياطية', 'system', null, $_FILES['backup_file']['name']);
                setFlash('success', 'تم استعادة النسخة الاحتياطية بنجاح. تم حفظ نسخة أمان: ' . $safetyName);
            } else {
                setFlash('error', 'فشل استعادة النسخة الاحتياطية');
            }
            header('Location: backup.php');
            exit;
        }
    }

    if ($_POST['action'] === 'delete') {
        $file = $backupDir . basename($_POST['filename']);
        if (file_exists($file)) {
            unlink($file);
            setFlash('success', 'تم حذف النسخة الاحتياطية');
        }
        header('Location: backup.php');
        exit;
    }
}

$backups = [];
if (is_dir($backupDir)) {
    foreach (scandir($backupDir, SCANDIR_SORT_DESCENDING) as $f) {
        if (pathinfo($f, PATHINFO_EXTENSION) === 'db') {
            $backups[] = ['name' => $f, 'size' => filesize($backupDir . $f), 'date' => filemtime($backupDir . $f)];
        }
    }
}

$dbSize = file_exists($dbPath) ? filesize($dbPath) : 0;
$totalBackupSize = array_sum(array_column($backups, 'size'));
$quickCheckRow = $db->fetchOne("PRAGMA quick_check");
$dbHealthStatus = strtolower((string)($quickCheckRow['quick_check'] ?? '')) === 'ok';
$errorLogPath = ini_get('error_log') ?: (defined('ERROR_LOG_PATH') ? ERROR_LOG_PATH : '');
$errorLogExists = $errorLogPath !== '' && file_exists($errorLogPath);
$errorLogSize = $errorLogExists ? filesize($errorLogPath) : 0;
$errorTail = $errorLogExists ? readTailLines($errorLogPath, 25) : [];

include __DIR__ . '/../includes/header.php';
?>

<div class="flex h-screen overflow-hidden">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="flex-1 overflow-y-auto bg-background-light">
        <header class="bg-white border-b border-slate-200 px-6 py-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">النسخ الاحتياطي</h1>
                    <p class="text-sm text-slate-500 mt-1">حماية بياناتك، جدولة تلقائية، ومراقبة حالة النظام</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <form method="POST" class="inline">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        <input type="hidden" name="action" value="backup">
                        <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-5 py-2.5 rounded-xl shadow-lg shadow-primary/30 font-medium text-sm flex items-center gap-2 transition-all">
                            <span class="material-icons-outlined text-base">backup</span>
                            إنشاء نسخة احتياطية الآن
                        </button>
                    </form>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="optimize">
                        <?php csrfInput(); ?>
                        <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-xl shadow-lg shadow-emerald-500/20 font-medium text-sm flex items-center gap-2 transition-all">
                            <span class="material-icons-outlined text-base">tune</span>
                            تحسين قاعدة البيانات
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <div class="p-6 space-y-6 max-w-5xl mx-auto">
            <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-slate-100 rounded-lg text-slate-500">
                        <span class="material-icons-outlined">sd_storage</span>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">المساحة المستخدمة للنسخ</p>
                        <p class="text-sm font-bold font-num"><?= number_format($totalBackupSize / (1024 * 1024), 2) ?> MB</p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-xs text-slate-500">جدولة النسخ التلقائي</p>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?= $backupAutoEnabled ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-600' ?>">
                        <?= $backupAutoEnabled ? 'نشط كل ' . $backupFrequencyHours . ' ساعة' : 'متوقف' ?>
                    </span>
                    <p class="text-[11px] text-slate-400 mt-1">آخر تنفيذ تلقائي: <?= $lastAutoBackupAt !== '' ? sanitize(formatDateTimeAr($lastAutoBackupAt)) : '—' ?></p>
                    <?php if ($autoBackupRan): ?><p class="text-[11px] text-emerald-600 mt-1">تم تنفيذ نسخة تلقائية الآن</p><?php endif; ?>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-xl border border-slate-200 p-5 text-center">
                    <span class="material-icons-outlined text-3xl text-primary mb-2">storage</span>
                    <p class="text-xs text-slate-500">حجم قاعدة البيانات</p>
                    <p class="text-xl font-bold font-num"><?= number_format($dbSize / 1024, 1) ?> KB</p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-5 text-center">
                    <span class="material-icons-outlined text-3xl text-emerald-500 mb-2">cloud_done</span>
                    <p class="text-xs text-slate-500">عدد النسخ المحفوظة</p>
                    <p class="text-xl font-bold font-num"><?= count($backups) ?></p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-5 text-center">
                    <span class="material-icons-outlined text-3xl text-amber-500 mb-2">schedule</span>
                    <p class="text-xs text-slate-500">آخر نسخة</p>
                    <p class="text-xl font-bold"><?= !empty($backups) ? sanitize(formatDateTimeAr(date('Y-m-d H:i:s', $backups[0]['date']))) : '—' ?></p>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 p-6">
                <h3 class="font-bold text-slate-900 mb-3 flex items-center gap-2">
                    <span class="material-icons-outlined text-primary">schedule</span>
                    إعداد جدولة النسخ الاحتياطي
                </h3>
                <form method="POST" class="grid sm:grid-cols-4 gap-3 items-end">
                    <input type="hidden" name="action" value="save_schedule">
                    <?php csrfInput(); ?>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="backup_auto_enabled" value="1" <?= $backupAutoEnabled ? 'checked' : '' ?> class="rounded border-slate-300 text-primary focus:ring-primary">
                        تفعيل النسخ التلقائي
                    </label>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">كل كم ساعة</label>
                        <input type="number" min="1" max="168" name="backup_frequency_hours" value="<?= $backupFrequencyHours ?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-num">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">الاحتفاظ بعدد</label>
                        <input type="number" min="3" max="200" name="backup_keep_count" value="<?= $backupKeepCount ?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-num">
                    </div>
                    <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">حفظ الجدولة</button>
                </form>
                <p class="text-xs text-slate-400 mt-2">يتم تنفيذ النسخ التلقائي عند أول زيارة للوحة بعد مرور المدة المحددة.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-white rounded-xl border border-slate-200 p-5">
                    <h3 class="font-bold text-slate-900 mb-3 flex items-center gap-2">
                        <span class="material-icons-outlined <?= $dbHealthStatus ? 'text-emerald-600' : 'text-red-600' ?>">health_and_safety</span>
                        صحة قاعدة البيانات
                    </h3>
                    <p class="text-sm text-slate-600">فحص `PRAGMA quick_check`</p>
                    <p class="mt-2 text-lg font-bold <?= $dbHealthStatus ? 'text-emerald-600' : 'text-red-600' ?>">
                        <?= $dbHealthStatus ? 'سليمة (OK)' : 'تحتاج فحص يدوي' ?>
                    </p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 p-5">
                    <h3 class="font-bold text-slate-900 mb-3 flex items-center gap-2">
                        <span class="material-icons-outlined text-amber-600">bug_report</span>
                        سجل الأخطاء
                    </h3>
                    <p class="text-xs text-slate-500 mb-1">المسار: <span class="font-num"><?= sanitize($errorLogPath ?: 'غير محدد') ?></span></p>
                    <p class="text-sm text-slate-600">الحجم: <span class="font-num font-semibold"><?= $errorLogExists ? number_format($errorLogSize / 1024, 1) . ' KB' : '0 KB' ?></span></p>
                    <p class="text-xs mt-2 <?= $errorLogExists ? 'text-emerald-600' : 'text-slate-400' ?>"><?= $errorLogExists ? 'ملف السجل متاح' : 'لا يوجد ملف أخطاء بعد' ?></p>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 p-6">
                <h3 class="font-bold text-slate-900 mb-3 flex items-center gap-2">
                    <span class="material-icons-outlined text-amber-500">restore</span>
                    استعادة من ملف
                </h3>
                <form method="POST" enctype="multipart/form-data" onsubmit="return confirm('سيتم استبدال قاعدة البيانات الحالية. هل أنت متأكد؟')" class="flex items-center gap-3">
                    <input type="hidden" name="action" value="restore">
                    <?php csrfInput(); ?>
                    <input type="file" name="backup_file" accept=".db" required class="text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100">
                    <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">استعادة</button>
                </form>
            </div>

            <?php if ($errorLogExists): ?>
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="p-4 border-b border-slate-100">
                    <h3 class="font-bold text-slate-900">آخر سجلات الأخطاء</h3>
                </div>
                <div class="p-4 bg-slate-950 text-slate-200 font-mono text-xs max-h-72 overflow-auto" dir="ltr">
                    <?php if (empty($errorTail)): ?>
                        <p class="text-slate-500">No recent entries.</p>
                    <?php else: ?>
                        <?php foreach ($errorTail as $line): ?>
                            <div><?= sanitize($line) ?></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="p-4 border-b border-slate-100"><h3 class="font-bold">النسخ الاحتياطية المحفوظة</h3></div>
                <div class="divide-y divide-slate-100">
                    <?php if (empty($backups)): ?>
                    <div class="p-8 text-center text-slate-400">لا توجد نسخ احتياطية بعد</div>
                    <?php endif; ?>
                    <?php foreach ($backups as $b): ?>
                    <div class="flex items-center justify-between p-4 hover:bg-slate-50 transition-colors group">
                        <div class="flex items-center gap-3">
                            <span class="material-icons-outlined text-slate-400">description</span>
                            <div>
                                <p class="text-sm font-medium font-num"><?= sanitize($b['name']) ?></p>
                                <p class="text-xs text-slate-400"><?= number_format($b['size'] / 1024, 1) ?> KB · <?= sanitize(formatDateTimeAr(date('Y-m-d H:i:s', $b['date']))) ?></p>
                            </div>
                        </div>
                        <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="download">
                                <input type="hidden" name="filename" value="<?= sanitize($b['name']) ?>">
                                <?php csrfInput(); ?>
                                <button type="submit" class="p-2 hover:bg-blue-50 rounded-lg text-slate-500 hover:text-blue-500 transition-colors" title="تحميل"><span class="material-icons-outlined text-[18px]">download</span></button>
                            </form>
                            <form method="POST" class="inline" onsubmit="return confirm('حذف هذه النسخة الاحتياطية؟')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="filename" value="<?= sanitize($b['name']) ?>">
                                <?php csrfInput(); ?>
                                <button type="submit" class="p-2 hover:bg-red-50 rounded-lg text-slate-500 hover:text-red-500 transition-colors" title="حذف"><span class="material-icons-outlined text-[18px]">delete</span></button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
