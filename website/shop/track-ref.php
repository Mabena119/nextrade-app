<?php
/**
 * Bind shopper email to affiliate ref before checkout (supports IP/visitor fallback).
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/security.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/affiliate.php';
auraai_sec_bootstrap();
auraai_sec_rate_limit_or_exit('affiliate_track_ref', 60, 300);

header('Content-Type: application/json; charset=utf-8');

if (strcasecmp($_SERVER['REQUEST_METHOD'] ?? '', 'POST') !== 0) {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = auraai_sec_json_input(4096);
$email = auraai_sec_email($input['email'] ?? $_POST['email'] ?? '');
$visitorId = auraai_affiliate_ensure_visitor_cookie(
    auraai_affiliate_normalize_visitor($input['vid'] ?? $_POST['vid'] ?? '')
);
$code = auraai_affiliate_normalize_code($input['ref'] ?? $_POST['ref'] ?? auraai_affiliate_get_ref_cookie());

if ($email === null) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Valid email required']);
    exit;
}

require dirname(__DIR__) . '/admin/php-includes/connect.php';

if ($code !== null) {
    auraai_affiliate_set_ref_cookie($code);
    auraai_affiliate_record_attribution_signals($con, $code, $visitorId);
}

$affiliate = auraai_affiliate_ensure_email_attribution($con, $email, $code, $visitorId);
$resolvedCode = $affiliate ? (string) $affiliate['code'] : $code;

echo json_encode(['ok' => $affiliate !== null, 'ref' => $resolvedCode, 'vid' => $visitorId]);
