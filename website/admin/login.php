<?php
ob_start();
require __DIR__ . '/php-includes/security-bridge.php';
auraai_sec_bootstrap();
auraai_sec_session_start();
auraai_sec_require_method('POST');
auraai_sec_rate_limit_or_exit('admin_login', 10, 900);

require __DIR__ . '/php-includes/connect.php';

if (!auraai_sec_csrf_validate()) {
    header('Location: index.php?error=csrf');
    ob_end_flush();
    exit();
}

auraai_sec_honeypot_require('website');

$email = auraai_sec_email($_POST['email'] ?? '');
$password = auraai_sec_password($_POST['password'] ?? '', 1, 50);

if ($email === null || $password === null) {
    header('Location: index.php?error=invalid');
    ob_end_flush();
    exit();
}

$stmt = $con->prepare('SELECT id, email, status, displayname FROM admin WHERE email = ? AND password = ? LIMIT 1');
$stmt->bind_param('ss', $email, $password);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    header('Location: index.php?error=auth');
    ob_end_flush();
    exit();
}

$status = (string) ($user['status'] ?? '');

if ($status === 'pending') {
    header('Location: index.php?error=pending');
    ob_end_flush();
    exit();
}

if ($status !== 'active') {
    header('Location: index.php?error=blocked');
    ob_end_flush();
    exit();
}

session_regenerate_id(true);
$_SESSION['username'] = $user['email'];
$_SESSION['id'] = session_id();
$_SESSION['admin_id'] = (int) $user['id'];
$_SESSION['displayname'] = (string) ($user['displayname'] ?? '');

header('Location: home/index.php');
ob_end_flush();
exit();
