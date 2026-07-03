<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

auth_logout();

// Clear remember-me cookie
if (isset($_COOKIE['dvibes_remember_uid'])) {
    setcookie('dvibes_remember_uid', '', time() - 3600, '/', '', false, true);
}
// Clear last-email prefill cookie
if (isset($_COOKIE['dvibes_last_email'])) {
    setcookie('dvibes_last_email', '', time() - 3600, '/', '', false, true);
}


header('Location: index.php');
exit;
