<?php
require dirname(__DIR__) . '/admin/php-includes/security-bridge.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/affiliate.php';
auraai_sec_bootstrap();
auraai_sec_session_start();
auraai_sec_require_method('POST');
auraai_sec_rate_limit_or_exit('affiliate_login', 10, 900);
auraai_sec_csrf_require();
auraai_sec_honeypot_require('website');

require dirname(__DIR__) . '/admin/php-includes/connect.php';

$result = auraai_affiliate_login($con, $_POST['email'] ?? '', $_POST['password'] ?? '');
if (!$result['ok']) {
    $code = (string) ($result['code'] ?? 'auth');
    if (!in_array($code, ['blocked', 'inactive'], true)) {
        $code = 'auth';
    }
    header('Location: index.php?error=' . urlencode($code));
    exit;
}

session_regenerate_id(true);
$_SESSION['affiliate_id'] = (int) $result['id'];
header('Location: dashboard.php');
exit();
