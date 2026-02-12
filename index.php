<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (Auth::isLoggedIn()) {
    header('Location: pos.php');
} else {
    header('Location: login.php');
}
exit;
