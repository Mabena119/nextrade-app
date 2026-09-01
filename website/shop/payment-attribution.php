<?php
/**
 * App follow-up after checkout: record device IP + affiliate ref once member is paid.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/security.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/affiliate.php';
auraai_sec_bootstrap();
auraai_sec_rate_limit_or_exit('affiliate_payment_confirm', 30, 300);

header('Content-Type: application/json; charset=utf-8');

if (strcasecmp($_SERVER['REQUEST_METHOD'] ?? '', 'POST') !== 0) {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = auraai_sec_json_input(4096);
$email = auraai_sec_email($input['email'] ?? $_POST['email'] ?? '');
if ($email === null) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Valid email required']);
    exit;
}

require dirname(__DIR__) . '/admin/php-includes/connect.php';

if (!auraai_affiliate_member_is_paid($con, $email)) {
    echo json_encode(['ok' => true, 'paid' => false, 'attributed' => false]);
    exit;
}

$code = auraai_affiliate_normalize_code($input['ref'] ?? $_POST['ref'] ?? auraai_affiliate_get_ref_cookie());
$visitorId = auraai_affiliate_ensure_visitor_cookie(
    auraai_affiliate_normalize_visitor($input['vid'] ?? $_POST['vid'] ?? '')
);

if ($code !== null) {
    auraai_affiliate_set_ref_cookie($code);
    auraai_affiliate_record_attribution_signals($con, $code, $visitorId);
    auraai_affiliate_ensure_email_attribution($con, $email, $code, $visitorId);
}

$clientIp = auraai_affiliate_normalize_client_ip(auraai_sec_client_ip());
$gatewayRef = 'app_' . substr(hash('sha256', strtolower($email)), 0, 32);

$attributed = auraai_affiliate_handle_member_payment_success(
    $con,
    $email,
    'app',
    $gatewayRef,
    49900,
    'vps',
    null,
    $code !== null ? [(object) ['variable_name' => 'ref', 'value' => $code]] : null,
    'ZAR',
    $visitorId,
    $clientIp
);

echo json_encode([
    'ok' => true,
    'paid' => true,
    'attributed' => $attributed,
    'ref' => $code,
    'vid' => $visitorId,
]);
