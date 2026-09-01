<?php
/**
 * Register affiliate ref against IP + visitor id (app / landing follow-up).
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/security.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/affiliate.php';
auraai_sec_bootstrap();
auraai_sec_rate_limit_or_exit('affiliate_attribution_ping', 60, 300);

header('Content-Type: application/json; charset=utf-8');

if (strcasecmp($_SERVER['REQUEST_METHOD'] ?? '', 'POST') !== 0) {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = auraai_sec_json_input(4096);
$code = auraai_affiliate_normalize_code($input['ref'] ?? $_POST['ref'] ?? auraai_affiliate_get_ref_cookie());
$visitorParam = auraai_affiliate_normalize_visitor($input['vid'] ?? $_POST['vid'] ?? '');
$visitorId = auraai_affiliate_ensure_visitor_cookie($visitorParam);

if ($code === null) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Valid referral code required']);
    exit;
}

require dirname(__DIR__) . '/admin/php-includes/connect.php';
auraai_affiliate_set_ref_cookie($code);
auraai_affiliate_record_attribution_signals($con, $code, $visitorId);
auraai_affiliate_track_click($con, $code, '/ping', $visitorId);

$email = auraai_sec_email($input['email'] ?? $_POST['email'] ?? '');
if ($email !== null) {
    auraai_affiliate_ensure_email_attribution($con, $email, $code, $visitorId);
}

echo json_encode(['ok' => true, 'ref' => $code, 'vid' => $visitorId]);
