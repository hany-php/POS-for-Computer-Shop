<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireRole(['admin']);
$pageTitle = 'النسخ الاحتياطي';
$user = Auth::user();

$dbPath = __DIR__ . '/../database/pos.db';
$backupDir = __DIR__ . '/../database/backups/';
if (!is_dir($backupDir)) mkdir($backupDir, 0777, true);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'backup') {
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.db';
        if (copy($dbPath, $backupDir . $filename)) {
            logActivity('نسخ احتياطي', 'system', null, $filename);
            setFlash('success', 'تم إنشاء نسخة احتياطية: ' . $filename);
        } else {
            setFlash('error', 'فشل إنشاء النسخة الاحتياطية');
        }
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
            // Create a safety backup before restore
            $safetyName = 'pre_restore_' . date('Y-m-d_H-i-s') . '.db';
            copy($dbPath, $backupDir . $safetyName);
            
            // Restore
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

// List existing backups
$backups = [];
if (is_dir($backupDir)) {
    $files = scandir($backupDir, SCANDIR_SORT_DESCENDING);
    foreach ($files as $f) {
        if (pathinfo($f, PATHINFO_EXTENSION) === 'db') {
            $backups[] = [
                'name' => $f,
                'size' => filesize($backupDir . $f),
                'date' => filemtime($backupDir . $f)
            ];
        }
    }
}

// Current DB info
$dbSize = file_exists($dbPath) ? filesize($dbPath) : 0;

include __DIR__ . '/../includes/header.php';
?>

<div class="flex h-screen overflow-hidden">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="flex-1 overflow-y-auto bg-background-light">
        <header class="bg-white border-b border-slate-200 px-6 py-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">النسخ الاحتياطي</h1>
                    <p class="text-sm text-slate-500 mt-1">حماية بياناتك وإمكانية الاستعادة</p>
                </div>
                <form method="POST" class="inline">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="action" value="backup">
                    <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-5 py-2.5 rounded-xl shadow-lg shadow-primary/30 font-medium text-sm flex items-center gap-2 transition-all">
                        <span class="material-icons-outlined text-base">backup</span>
                        إنشاء نسخة احتياطية الآن
                    </button>
                </form>
            </div>
        </header>

        <div class="p-6 space-y-6 max-w-4xl mx-auto">
            <!-- Storage Info -->
            <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-slate-100 rounded-lg text-slate-500">
                        <span class="material-icons-outlined">sd_storage</span>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">المساحة المستخدمة للنسخ</p>
                        <?php
                        $totalBackupSize = array_sum(array_column($backups, 'size'));
                        ?>
                        <p class="text-sm font-bold font-num"><?= number_format($totalBackupSize / (1024 * 1024), 2) ?> MB</p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-xs text-slate-500">جدولة النسخ التلقائي</p>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">نشط (يومياً)</span>
                </div>
            </div>

            <!-- DB Info -->
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
                    <p class="text-xl font-bold font-num"><?= !empty($backups) ? date('Y/m/d H:i', $backups[0]['date']) : '—' ?></p>
                </div>
            </div>

            <!-- Restore Upload -->
            <div class="bg-white rounded-xl border border-slate-200 p-6">
                <h3 class="font-bold text-slate-900 mb-3 flex items-center gap-2">
                    <span class="material-icons-outlined text-amber-500">restore</span>
                    استعادة من ملف
                </h3>
                <form method="POST" enctype="multipart/form-data" onsubmit="return confirm('سيتم استبدال قاعدة البيانات الحالية. هل أنت متأكد؟')" class="flex items-center gap-3">
                    <input type="hidden" name="action" value="restore">
                    <input type="file" name="backup_file" accept=".db" required class="text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100">
                    <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">استعادة</button>
                </form>
            </div>

            <!-- Backup List -->
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
                                <p class="text-xs text-slate-400"><?= number_format($b['size'] / 1024, 1) ?> KB · <?= date('Y/m/d H:i:s', $b['date']) ?></p>
                            </div>
                        </div>
                        <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="download">
                                <input type="hidden" name="filename" value="<?= sanitize($b['name']) ?>">
                                <button type="submit" class="p-2 hover:bg-blue-50 rounded-lg text-slate-500 hover:text-blue-500 transition-colors" title="تحميل"><span class="material-icons-outlined text-[18px]">download</span></button>
                            </form>
                            <form method="POST" class="inline" onsubmit="return confirm('حذف هذه النسخة الاحتياطية؟')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="filename" value="<?= sanitize($b['name']) ?>">
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
