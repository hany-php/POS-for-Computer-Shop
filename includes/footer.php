<?php
/**
 * Footer Template
 */
$flash = getFlash();
if ($flash): ?>
<div id="flash-message" class="fixed bottom-6 left-6 z-50 px-6 py-4 rounded-xl shadow-lg border <?= $flash['type'] === 'success' ? 'bg-green-50 text-green-800 border-green-200' : 'bg-red-50 text-red-800 border-red-200' ?> animate-pulse">
    <div class="flex items-center gap-2">
        <span class="material-icons-outlined"><?= $flash['type'] === 'success' ? 'check_circle' : 'error' ?></span>
        <span class="font-medium"><?= $flash['message'] ?></span>
    </div>
</div>
<script>setTimeout(() => document.getElementById('flash-message')?.remove(), 3000);</script>
<?php endif; ?>
</body>
</html>
