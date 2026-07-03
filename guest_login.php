<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

$res = auth_guest_login($pdo);
if (!($res['ok'] ?? false)) {
    header('Location: index.php');
    exit;
}

header('Location: index.php');
exit;
