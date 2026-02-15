<?php
/**
 * Footer Template
 */
$flash = getFlash();
$lockUser = Auth::user();
$isAdminPage = strpos($_SERVER['PHP_SELF'] ?? '', '/admin/') !== false;
$unlockApiPath = $isAdminPage ? '../api/unlock.php' : 'api/unlock.php';
$lockApiPath = $isAdminPage ? '../api/lock.php' : 'api/lock.php';
$serviceWorkerPath = $isAdminPage ? '../service-worker.js' : 'service-worker.js';
$isScreenLocked = !empty($_SESSION['screen_locked']);
if ($flash): ?>
<div id="flash-message" class="fixed bottom-6 left-6 z-50 px-6 py-4 rounded-xl shadow-lg border <?= $flash['type'] === 'success' ? 'bg-green-50 text-green-800 border-green-200' : 'bg-red-50 text-red-800 border-red-200' ?> animate-pulse">
    <div class="flex items-center gap-2">
        <span class="material-icons-outlined"><?= $flash['type'] === 'success' ? 'check_circle' : 'error' ?></span>
        <span class="font-medium"><?= $flash['message'] ?></span>
    </div>
</div>
<script>setTimeout(() => document.getElementById('flash-message')?.remove(), 3000);</script>
<?php endif; ?>
<?php if ($lockUser): ?>
<div id="inactivity-lock-modal" class="<?= $isScreenLocked ? '' : 'hidden' ?> fixed inset-0 z-[9999] bg-slate-950/70 backdrop-blur-sm">
    <div class="w-full h-full flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-md rounded-2xl border border-slate-200 shadow-2xl overflow-hidden">
            <div class="p-6 border-b border-slate-100 text-center">
                <h3 class="text-xl font-bold text-slate-900">تم قفل الشاشة</h3>
                <p class="text-sm text-slate-500 mt-1">أدخل بيانات الدخول للمتابعة</p>
            </div>
            <form id="unlock-form" class="p-6 space-y-4">
                <input type="hidden" id="unlock-csrf" value="<?= generateCsrfToken() ?>">
                <input type="hidden" id="unlock-method" value="password">
                <div class="bg-slate-50 rounded-lg p-3 text-sm text-slate-600">
                    <p>المستخدم: <span class="font-semibold text-slate-800"><?= sanitize($lockUser['full_name'] ?? '') ?></span></p>
                    <p>الدور: <span class="font-semibold text-slate-800"><?= sanitize(Auth::getRoleNameAr($lockUser['role'] ?? '')) ?></span></p>
                </div>
                <div class="bg-slate-50 rounded-lg p-1 flex items-center gap-1">
                    <button type="button" id="unlock-tab-password" class="unlock-tab flex-1 rounded-md px-3 py-2 text-xs font-medium bg-white text-primary shadow-sm border border-slate-200">كلمة المرور</button>
                    <button type="button" id="unlock-tab-pin" class="unlock-tab flex-1 rounded-md px-3 py-2 text-xs font-medium text-slate-600 hover:bg-white/70">PIN</button>
                </div>
                <div>
                    <label id="unlock-label" class="block text-sm font-medium text-slate-700 mb-1.5"></label>
                    <input id="unlock-secret" autocomplete="off" required
                        class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-sm font-num text-left focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary"
                        dir="ltr">
                </div>
                <p id="unlock-error" class="hidden text-sm text-red-600"></p>
                <button type="submit" class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-3 rounded-xl transition-colors">فتح القفل</button>
            </form>
        </div>
    </div>
</div>
<script>
(() => {
    const LOCK_TIMEOUT_MS = 5 * 60 * 1000; // 5 minutes
    const defaultUnlockMode = <?= json_encode(($lockUser['role'] ?? '') === 'cashier' ? 'pin' : 'password') ?>;
    const modal = document.getElementById('inactivity-lock-modal');
    const form = document.getElementById('unlock-form');
    const input = document.getElementById('unlock-secret');
    const label = document.getElementById('unlock-label');
    const errorBox = document.getElementById('unlock-error');
    const unlockMethod = document.getElementById('unlock-method');
    const tabPassword = document.getElementById('unlock-tab-password');
    const tabPin = document.getElementById('unlock-tab-pin');
    const csrf = document.getElementById('unlock-csrf');
    const unlockApi = <?= json_encode($unlockApiPath) ?>;
    const lockApi = <?= json_encode($lockApiPath) ?>;
    const isInitiallyLocked = <?= $isScreenLocked ? 'true' : 'false' ?>;

    if (!modal || !form || !input || !label || !unlockMethod || !tabPassword || !tabPin) return;

    let timer = null;
    let locked = isInitiallyLocked;
    let hiddenAt = 0;

    function setUnlockMode(mode) {
        const isPin = mode === 'pin';
        unlockMethod.value = isPin ? 'pin' : 'password';
        label.textContent = isPin ? 'الرمز السري (PIN)' : 'كلمة المرور';
        input.type = 'password';
        input.inputMode = isPin ? 'numeric' : 'text';
        input.value = '';
        tabPassword.className = 'unlock-tab flex-1 rounded-md px-3 py-2 text-xs font-medium ' + (isPin ? 'text-slate-600 hover:bg-white/70' : 'bg-white text-primary shadow-sm border border-slate-200');
        tabPin.className = 'unlock-tab flex-1 rounded-md px-3 py-2 text-xs font-medium ' + (isPin ? 'bg-white text-primary shadow-sm border border-slate-200' : 'text-slate-600 hover:bg-white/70');
    }

    tabPassword.addEventListener('click', () => setUnlockMode('password'));
    tabPin.addEventListener('click', () => setUnlockMode('pin'));
    setUnlockMode(defaultUnlockMode);

    function lockScreen() {
        if (locked) return;
        locked = true;
        modal.classList.remove('hidden');
        input.value = '';
        errorBox.classList.add('hidden');
        setTimeout(() => input.focus(), 50);
        notifyServerLocked();
    }

    async function notifyServerLocked() {
        try {
            const body = new URLSearchParams();
            body.append('csrf_token', csrf.value);
            await fetch(lockApi, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                body: body.toString()
            });
        } catch (_) {
            // Keep client lock active even if backend call fails.
        }
    }

    function startTimer() {
        if (locked) return;
        clearTimeout(timer);
        timer = setTimeout(lockScreen, LOCK_TIMEOUT_MS);
    }

    function markActivity() {
        startTimer();
    }

    ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart'].forEach(evt => {
        document.addEventListener(evt, markActivity, { passive: true });
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            hiddenAt = Date.now();
        } else {
            if (hiddenAt && (Date.now() - hiddenAt >= LOCK_TIMEOUT_MS)) {
                lockScreen();
            } else {
                startTimer();
            }
            hiddenAt = 0;
        }
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        errorBox.classList.add('hidden');

        try {
            const body = new URLSearchParams();
            body.append('csrf_token', csrf.value);
            body.append('secret', input.value);
            body.append('unlock_method', unlockMethod.value);

            const res = await fetch(unlockApi, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                body: body.toString()
            });
            const data = await res.json();
            if (data && data.success) {
                locked = false;
                modal.classList.add('hidden');
                input.value = '';
                startTimer();
            } else {
                errorBox.textContent = (data && data.error) ? data.error : 'فشل فتح القفل';
                errorBox.classList.remove('hidden');
                input.focus();
                input.select();
            }
        } catch (err) {
            errorBox.textContent = 'تعذر الاتصال بالخادم';
            errorBox.classList.remove('hidden');
        }
    });

    if (isInitiallyLocked) {
        modal.classList.remove('hidden');
        setTimeout(() => input.focus(), 50);
    } else {
        startTimer();
    }
})();
</script>
<?php endif; ?>
<script>
(() => {
    if (!('serviceWorker' in navigator)) return;
    window.addEventListener('load', () => {
        navigator.serviceWorker.register(<?= json_encode($serviceWorkerPath) ?>).catch(() => {});
    });
})();
</script>
</body>
</html>
