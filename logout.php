<?php
require_once __DIR__ . '/includes/bootstrap.php';

session_unset();
session_destroy();
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta http-equiv="refresh" content="0;url=login.php">
    <title>تسجيل الخروج</title>
</head>
<body>
<script>
(() => {
    try {
        const prefixes = ['pos_cart_state_user_', 'pos_card_size_level_user_'];
        for (let i = localStorage.length - 1; i >= 0; i--) {
            const key = localStorage.key(i);
            if (!key) continue;
            if (prefixes.some(p => key.startsWith(p))) {
                localStorage.removeItem(key);
            }
        }
    } catch (_) {}
    window.location.replace('login.php');
})();
</script>
</body>
</html>
