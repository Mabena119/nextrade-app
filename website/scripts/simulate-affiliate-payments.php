<?php
/**
 * Simulate affiliate conversions for testing (CLI only).
 * Usage: php simulate-affiliate-payments.php <affiliate-email>
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$affiliateEmail = $argv[1] ?? '';
if ($affiliateEmail === '') {
    fwrite(STDERR, "Usage: php simulate-affiliate-payments.php <affiliate-email>\n");
    exit(1);
}
$root = dirname(__DIR__);
require $root . '/admin/php-includes/connect.php';
require $root . '/includes/affiliate.php';

auraai_affiliate_ensure_tables($con);

$affiliate = auraai_affiliate_by_email($con, $affiliateEmail);
if (!$affiliate) {
    fwrite(STDERR, "Affiliate not found: $affiliateEmail\n");
    exit(1);
}

$affiliateId = (int) $affiliate['id'];
$code = (string) $affiliate['code'];
$ts = time();

$before = auraai_affiliate_dashboard_stats($con, $affiliateId);
$beforeRate = auraai_affiliate_commission_info($con, $affiliateId);

echo "=== Affiliate simulation ===\n";
echo "Affiliate: {$affiliate['full_name']} <{$affiliate['email']}>\n";
echo "Code (internal): $code\n";
echo "Link: " . auraai_affiliate_shop_link($code) . "\n";
echo "Before: {$before['conversions']} conversions, R" . number_format($before['commission_cents'] / 100, 2) . " commission, {$beforeRate['current_pct']}% rate\n\n";

$tests = [
    [
        'label' => 'Paystack VPS (email attribution)',
        'member' => "affsim.ps.vps.{$ts}@test.auraai-vps.com",
        'gateway' => 'paystack',
        'ref' => "SIM_PS_VPS_{$ts}_1",
        'amount' => 55000,
        'product' => 'vps',
        'setup' => function (mysqli $c, string $member, string $refCode) {
            return auraai_affiliate_bind_email($c, $member, $refCode);
        },
        'referrer' => null,
        'custom_fields' => null,
    ],
    [
        'label' => 'Whop VPS (referrer URL)',
        'member' => "affsim.whop.vps.{$ts}@test.auraai-vps.com",
        'gateway' => 'whop',
        'ref' => "SIM_WHOP_{$ts}_2",
        'amount' => 55000,
        'product' => 'vps',
        'setup' => null,
        'referrer' => 'https://auraai-vps.com/?ref=' . rawurlencode($code),
        'custom_fields' => null,
    ],
    [
        'label' => 'Paystack VPS (custom field ref)',
        'member' => "affsim.ps.vps2.{$ts}@test.auraai-vps.com",
        'gateway' => 'paystack',
        'ref' => "SIM_PS_VPS_{$ts}_3",
        'amount' => 55000,
        'product' => 'vps',
        'setup' => null,
        'referrer' => null,
        'custom_fields' => [
            (object) ['variable_name' => 'ref', 'value' => $code],
        ],
    ],
];

$results = [];

foreach ($tests as $i => $test) {
    echo '--- Payment ' . ($i + 1) . ': ' . $test['label'] . " ---\n";
    echo "Member: {$test['member']}\n";

    if (is_callable($test['setup'] ?? null)) {
        $bound = $test['setup']($con, $test['member'], $code);
        echo 'Attribution setup: ' . ($bound ? 'bound' : 'FAILED') . "\n";
    }

    $priorSales = auraai_affiliate_confirmed_sales_count($con, $affiliateId);
    $expectedRate = auraai_affiliate_commission_rate_from_sales($priorSales);
    $expectedCommission = (int) round($test['amount'] * $expectedRate);

    $ok = auraai_affiliate_process_member_payment(
        $con,
        $test['member'],
        $test['gateway'],
        $test['ref'],
        $test['amount'],
        $test['product'],
        $test['referrer'],
        $test['custom_fields'],
        'ZAR'
    );

    echo 'Recorded: ' . ($ok ? 'YES' : 'NO (duplicate or error)') . "\n";
    echo 'Expected rate: ' . round($expectedRate * 100, 3) . "%, commission: R" . number_format($expectedCommission / 100, 2) . "\n";

    $stmt = $con->prepare(
        "SELECT commission_cents, amount_cents, status, product_type, gateway
         FROM affiliate_conversions
         WHERE gateway = ? AND gateway_ref = ? AND affiliate_id = ?
         LIMIT 1"
    );
    $stmt->bind_param('ssi', $test['gateway'], $test['ref'], $affiliateId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $match = (int) $row['commission_cents'] === $expectedCommission
            && $row['status'] === 'confirmed'
            && $row['product_type'] === $test['product'];
        echo 'DB row: gateway=' . $row['gateway'] . ', product=' . $row['product_type']
            . ', sale=R' . number_format($row['amount_cents'] / 100, 2)
            . ', commission=R' . number_format($row['commission_cents'] / 100, 2)
            . ', status=' . $row['status'] . "\n";
        echo 'Validation: ' . ($match ? 'PASS' : 'FAIL') . "\n";
        $results[] = $match && $ok;
    } else {
        echo "DB row: NOT FOUND\n";
        echo "Validation: FAIL\n";
        $results[] = false;
    }
    echo "\n";
}

$after = auraai_affiliate_dashboard_stats($con, $affiliateId);
$afterRate = auraai_affiliate_commission_info($con, $affiliateId);

echo "=== After simulation ===\n";
echo "Conversions: {$before['conversions']} → {$after['conversions']} (+" . ($after['conversions'] - $before['conversions']) . ")\n";
echo "Commission: R" . number_format($before['commission_cents'] / 100, 2) . ' → R' . number_format($after['commission_cents'] / 100, 2) . "\n";
echo "Rate: {$beforeRate['current_pct']}% → {$afterRate['current_pct']}%\n";
echo 'Sales count: ' . $afterRate['confirmed_sales'] . "\n";

$passed = count(array_filter($results));
echo "\nResult: $passed/3 payments confirmed correctly\n";
exit($passed === 3 ? 0 : 1);
