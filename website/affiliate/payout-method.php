<?php
require dirname(__DIR__) . '/admin/php-includes/security-bridge.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/affiliate.php';
auraai_sec_bootstrap();
auraai_sec_session_start();
auraai_sec_require_method('POST');
auraai_sec_rate_limit_or_exit('affiliate_payout_method', 20, 3600);
auraai_sec_csrf_require();

$session = auraai_affiliate_require_login();
require dirname(__DIR__) . '/admin/php-includes/connect.php';
auraai_affiliate_assert_portal_access($con, $session['id']);

$result = auraai_affiliate_save_payout_method($con, $session['id'], $_POST);

if (!$result['ok']) {
    header('Location: dashboard.php?payout_error=' . urlencode($result['error'] ?? 'Could not save payout method.'));
    exit;
}

header('Location: dashboard.php?payout_saved=1');
exit;
