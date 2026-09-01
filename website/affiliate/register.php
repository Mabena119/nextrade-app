<?php
require dirname(__DIR__) . '/admin/php-includes/security-bridge.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/affiliate.php';
auraai_sec_bootstrap();
auraai_sec_session_start();
auraai_sec_require_method('POST');
auraai_sec_rate_limit_or_exit('affiliate_register', 5, 3600);
auraai_sec_csrf_require();
auraai_sec_honeypot_require('website');

require dirname(__DIR__) . '/admin/php-includes/connect.php';

$password = $_POST['password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';
if ($password !== $confirm) {
    header('Location: signup.php?error=mismatch');
    exit;
}

$result = auraai_affiliate_register(
    $con,
    $_POST['full_name'] ?? '',
    $_POST['email'] ?? '',
    $password,
    $_POST['phone'] ?? ''
);

if (!$result['ok']) {
    header('Location: signup.php?error=' . urlencode($result['error'] ?? 'Registration failed'));
    exit;
}

session_regenerate_id(true);
$_SESSION['affiliate_id'] = (int) $result['id'];
header('Location: dashboard.php?welcome=1');
exit();
