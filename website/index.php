<?php
/**
 * Landing page entry — capture affiliate ref, then serve index.html.
 */
require_once __DIR__ . '/includes/ip-block-guard.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/affiliate.php';

$visitorId = auraai_affiliate_ensure_visitor_cookie(
    auraai_affiliate_normalize_visitor($_GET['vid'] ?? '')
);

$refCode = auraai_affiliate_normalize_code($_GET['ref'] ?? '');
if ($refCode !== null) {
    require $_SERVER['DOCUMENT_ROOT'] . '/admin/php-includes/connect.php';
    auraai_affiliate_set_ref_cookie($refCode);
    auraai_affiliate_record_attribution_signals($con, $refCode, $visitorId);
    auraai_affiliate_track_click($con, $refCode, '/', $visitorId);
    header('Location: /', true, 302);
    exit;
}

$html = __DIR__ . '/index.html';
if (!is_readable($html)) {
    http_response_code(404);
    exit('Landing page not found.');
}

header('Content-Type: text/html; charset=utf-8');
readfile($html);
