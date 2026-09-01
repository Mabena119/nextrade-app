<?php
require dirname(__DIR__) . '/admin/php-includes/security-bridge.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/affiliate.php';
auraai_sec_bootstrap();
auraai_sec_session_start();
auraai_sec_require_method('POST');
auraai_sec_rate_limit_or_exit('affiliate_withdraw', 5, 3600);
auraai_sec_csrf_require();

$session = auraai_affiliate_require_login();
require dirname(__DIR__) . '/admin/php-includes/connect.php';
auraai_affiliate_assert_portal_access($con, $session['id']);

$methodId = (int) ($_POST['payout_method_id'] ?? 0);
$amountRaw = str_replace(',', '.', trim((string) ($_POST['amount'] ?? '')));
if ($amountRaw === '' || !is_numeric($amountRaw)) {
    header('Location: dashboard.php?withdraw_error=' . urlencode('Enter a valid withdrawal amount.'));
    exit;
}

$amountCents = (int) round((float) $amountRaw * 100);
$result = auraai_affiliate_request_withdrawal($con, $session['id'], $methodId, $amountCents);

if (!$result['ok']) {
    header('Location: dashboard.php?withdraw_error=' . urlencode($result['error'] ?? 'Withdrawal request failed.'));
    exit;
}

header('Location: dashboard.php?withdraw_requested=1');
exit;
