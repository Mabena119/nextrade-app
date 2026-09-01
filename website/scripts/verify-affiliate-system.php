<?php
/**
 * End-to-end affiliate attribution verification (CLI).
 * Usage: php verify-affiliate-system.php <affiliate-email>
 */
if (php_sapi_name() !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/admin/php-includes/connect.php';
require $root . '/includes/affiliate.php';

auraai_affiliate_ensure_tables($con);

$affiliateEmail = $argv[1] ?? '';
if ($affiliateEmail === '') {
    fwrite(STDERR, "Usage: php verify-affiliate-system.php <affiliate-email>\n");
    exit(1);
}

$affiliate = auraai_affiliate_by_email($con, $affiliateEmail);
if (!$affiliate) {
    fwrite(STDERR, "Test affiliate not found\n");
    exit(1);
}

$code = (string) $affiliate['code'];
$affiliateId = (int) $affiliate['id'];
$ts = time();
$passed = 0;
$failed = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "PASS  $label" . ($detail ? " — $detail" : '') . "\n";
    } else {
        $failed++;
        echo "FAIL  $label" . ($detail ? " — $detail" : '') . "\n";
    }
}

echo "=== Affiliate system verification ===\n";
echo "Affiliate: {$affiliate['full_name']} ($code)\n\n";

// 1. Shop link format
$link = auraai_affiliate_shop_link($code);
check('Shop link is landing URL', str_contains($link, 'https://nextradeai.io/?ref=' . rawurlencode($code)));

// 2. Tables exist
$tables = ['affiliate_ip_attributions', 'affiliate_visitor_attributions', 'affiliate_attributions', 'affiliate_conversions'];
foreach ($tables as $t) {
    $r = $con->query("SHOW TABLES LIKE '$t'");
    check("Table $t exists", $r && $r->num_rows > 0);
}

// 3. IP attribution flow
$testIp = "198.51.100.{$ts}";
$ipEmail = "verify.ip.{$ts}@test.auraai-vps.com";
auraai_affiliate_record_ip_attribution($con, $code, $testIp);
$_SERVER['REMOTE_ADDR'] = $testIp;
$fromIp = auraai_affiliate_resolve_by_ip($con, $testIp);
check('IP attribution stored', $fromIp && $fromIp['code'] === $code);
$boundIp = auraai_affiliate_ensure_email_attribution($con, $ipEmail, null, null);
check('Email bound via IP only', $boundIp && $boundIp['code'] === $code, $ipEmail);

// 4. Visitor attribution flow
$visitorId = auraai_affiliate_generate_visitor_id();
$visitorEmail = "verify.vid.{$ts}@test.auraai-vps.com";
auraai_affiliate_record_visitor_attribution($con, $code, $visitorId);
$fromVid = auraai_affiliate_resolve_by_visitor($con, $visitorId);
check('Visitor attribution stored', $fromVid && $fromVid['code'] === $code);
$boundVid = auraai_affiliate_ensure_email_attribution($con, $visitorEmail, null, $visitorId);
check('Email bound via visitor id', $boundVid && $boundVid['code'] === $code, $visitorEmail);

// 5. Direct ref + webhook simulation
$directEmail = "verify.direct.{$ts}@test.auraai-vps.com";
auraai_affiliate_bind_email($con, $directEmail, $code);
$prior = auraai_affiliate_confirmed_sales_count($con, $affiliateId);
$rate = auraai_affiliate_commission_rate_from_sales($prior);
$amount = 49900;
$expectedComm = (int) round($amount * $rate);
$gwRef = "VERIFY_{$ts}_DIRECT";
$ok = auraai_affiliate_process_member_payment($con, $directEmail, 'paystack', $gwRef, $amount, 'vps', null, null, 'ZAR');
$stmt = $con->prepare('SELECT commission_cents, status FROM affiliate_conversions WHERE gateway_ref = ? AND affiliate_id = ?');
$stmt->bind_param('si', $gwRef, $affiliateId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
check('Webhook-style conversion (email bind)', $ok && $row && $row['status'] === 'confirmed');
check('Commission math', $row && (int) $row['commission_cents'] === $expectedComm, 'R' . number_format($expectedComm / 100, 2));

// 6. IP-bound email → webhook (app flow simulation)
$appEmail = "verify.appflow.{$ts}@test.auraai-vps.com";
$appIp = "203.0.113.{$ts}";
auraai_affiliate_record_ip_attribution($con, $code, $appIp);
$_SERVER['REMOTE_ADDR'] = $appIp;
auraai_affiliate_ensure_email_attribution($con, $appEmail, null, null);
$gwRef2 = "VERIFY_{$ts}_APP";
$ok2 = auraai_affiliate_process_member_payment($con, $appEmail, 'paystack', $gwRef2, $amount, 'vps', null, null, 'ZAR');
$stmt = $con->prepare('SELECT id FROM affiliate_conversions WHERE gateway_ref = ? AND affiliate_id = ?');
$stmt->bind_param('si', $gwRef2, $affiliateId);
$stmt->execute();
$row2 = $stmt->get_result()->fetch_assoc();
$stmt->close();
check('App flow: IP click → email bind → payment', $ok2 && $row2, $appEmail);

// 6b. Webhook IP fallback (customer IP from Paystack payload, no prior email bind)
$webhookIp = "203.0.113.{$ts}";
$webhookEmail = "verify.webhookip.{$ts}@test.auraai-vps.com";
auraai_affiliate_record_ip_attribution($con, $code, $webhookIp);
$gwRefIp = "VERIFY_{$ts}_WEBHOOK_IP";
$okIp = auraai_affiliate_process_member_payment($con, $webhookEmail, 'paystack', $gwRefIp, $amount, 'vps', null, null, 'ZAR', null, $webhookIp);
$stmt = $con->prepare('SELECT id FROM affiliate_conversions WHERE gateway_ref = ? AND affiliate_id = ?');
$stmt->bind_param('si', $gwRefIp, $affiliateId);
$stmt->execute();
$rowIp = $stmt->get_result()->fetch_assoc();
$stmt->close();
check('Webhook IP fallback credits affiliate', $okIp && $rowIp, $webhookEmail);

// 7. Paystack context extractor
$fakeEvent = (object) [
    'data' => (object) [
        'metadata' => (object) [
            'referrer' => 'https://paystack.shop/pay/qhnur7yjsr?email=test@x.com&ref=' . $code,
            'custom_fields' => [],
        ],
    ],
];
$ctx = auraai_affiliate_paystack_context($fakeEvent);
$extracted = auraai_affiliate_extract_code_from_custom_fields($ctx['custom_fields']);
check('Paystack referrer ref extraction', $extracted === $code);

// 8. Whop context + product routing
$whopPayload = (object) [
    'id' => 'mem_TEST_' . $ts,
    'status' => 'completed',
    'currency' => 'zar',
    'user' => (object) ['email' => "verify.whop.{$ts}@test.auraai-vps.com"],
    'product' => (object) ['id' => 'prod_vxK8sI9whIztu', 'title' => 'EA Matrix VPS'],
    'plan' => (object) ['id' => 'plan_lokoDFGCt9OAk'],
    'custom_field_responses' => [
        (object) ['question' => 'ref', 'answer' => $code],
    ],
];
$whopCtx = auraai_affiliate_whop_context($whopPayload);
check('Whop question/answer ref extraction', $whopCtx['ref_code'] === $code);

$whopEmail = "verify.whop.{$ts}@test.auraai-vps.com";
auraai_affiliate_bind_email($con, $whopEmail, $code);
$gwRefWhop = "VERIFY_{$ts}_WHOP";
$okWhop = auraai_affiliate_process_member_payment(
    $con,
    $whopEmail,
    'whop',
    $gwRefWhop,
    49900,
    'vps',
    $whopCtx['referrer'],
    $whopCtx['custom_fields'],
    'ZAR'
);
$stmt = $con->prepare('SELECT id FROM affiliate_conversions WHERE gateway_ref = ? AND affiliate_id = ?');
$stmt->bind_param('si', $gwRefWhop, $affiliateId);
$stmt->execute();
$rowWhop = $stmt->get_result()->fetch_assoc();
$stmt->close();
check('Whop webhook-style conversion', $okWhop && $rowWhop, $whopEmail);

// 9. bind_param smoke: duplicate gateway ref ignored
$dup = auraai_affiliate_process_member_payment($con, $directEmail, 'paystack', $gwRef, $amount, 'vps', null, null, 'ZAR');
check('Duplicate payment ignored', $dup === false);

// Cleanup test rows
$emails = [$ipEmail, $visitorEmail, $directEmail, $appEmail, $whopEmail];
foreach ($emails as $e) {
    $stmt = $con->prepare('DELETE FROM affiliate_attributions WHERE email = ?');
    $stmt->bind_param('s', $e);
    $stmt->execute();
    $stmt->close();
}
foreach ([$gwRef, $gwRef2, $gwRefWhop] as $ref) {
    $stmt = $con->prepare('DELETE FROM affiliate_conversions WHERE gateway_ref = ? AND affiliate_id = ?');
    $stmt->bind_param('si', $ref, $affiliateId);
    $stmt->execute();
    $stmt->close();
}
$ipHash = hash('sha256', $testIp);
$stmt = $con->prepare('DELETE FROM affiliate_ip_attributions WHERE ip_hash = ?');
$stmt->bind_param('s', $ipHash);
$stmt->execute();
$stmt->close();
$ipHash2 = hash('sha256', $appIp);
$stmt = $con->prepare('DELETE FROM affiliate_ip_attributions WHERE ip_hash = ?');
$stmt->bind_param('s', $ipHash2);
$stmt->execute();
$stmt->close();
$vh = auraai_affiliate_visitor_hash($visitorId);
$stmt = $con->prepare('DELETE FROM affiliate_visitor_attributions WHERE visitor_hash = ?');
$stmt->bind_param('s', $vh);
$stmt->execute();
$stmt->close();

echo "\n=== Result: $passed passed, $failed failed ===\n";
exit($failed > 0 ? 1 : 0);
