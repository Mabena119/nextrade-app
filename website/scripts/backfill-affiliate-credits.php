<?php
/**
 * One-off: credit affiliate for paid members missing conversions.
 * Usage: php backfill-affiliate-credits.php <affiliate-email> <customer-email> [...]
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$affiliateEmail = $argv[1] ?? '';
$customerEmails = array_slice($argv, 2);

if ($affiliateEmail === '' || $customerEmails === []) {
    fwrite(STDERR, "Usage: php backfill-affiliate-credits.php <affiliate-email> <customer-email> [...]\n");
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/admin/php-includes/connect.php';
require $root . '/includes/affiliate.php';

auraai_affiliate_ensure_tables($con);

$affiliate = auraai_affiliate_by_email($con, $affiliateEmail);
if (!$affiliate) {
    fwrite(STDERR, "Affiliate not found or invalid: {$affiliateEmail}\n");
    exit(1);
}

if (($affiliate['status'] ?? '') !== 'active') {
    fwrite(STDERR, "Affiliate is not active: {$affiliateEmail} (status=" . ($affiliate['status'] ?? 'unknown') . ")\n");
    exit(1);
}

$affiliateId = (int) $affiliate['id'];
$code = (string) $affiliate['code'];

echo "Affiliate: {$affiliate['full_name']} <{$affiliate['email']}> code={$code} id={$affiliateId}\n\n";

$before = auraai_affiliate_dashboard_stats($con, $affiliateId);
$credited = 0;
$skipped = 0;
$failed = 0;

foreach ($customerEmails as $raw) {
    $email = auraai_sec_email($raw);
    if ($email === null) {
        echo "SKIP invalid email: {$raw}\n";
        $skipped++;
        continue;
    }

    echo "===== {$email} =====\n";

    $stmt = $con->prepare('SELECT id, email, paid, sub_tocken FROM members WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$member) {
        echo "SKIP: not in members table\n\n";
        $skipped++;
        continue;
    }

    if (empty($member['paid'])) {
        echo "SKIP: member exists but paid=0\n\n";
        $skipped++;
        continue;
    }

    $memberId = (int) $member['id'];
    $gatewayRef = (string) ($member['sub_tocken'] ?? '');
    if ($gatewayRef === '') {
        $gatewayRef = 'manual_backfill_' . substr(hash('sha256', $email), 0, 24);
    }

    $stmt = $con->prepare(
        "SELECT id, affiliate_id, gateway, gateway_ref, commission_cents, status
         FROM affiliate_conversions
         WHERE email = ? AND status = 'confirmed'
         LIMIT 1"
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        echo 'SKIP: already credited (conv id=' . $existing['id']
            . ', affiliate_id=' . $existing['affiliate_id']
            . ', commission_cents=' . $existing['commission_cents'] . ")\n\n";
        $skipped++;
        continue;
    }

    auraai_affiliate_bind_email($con, $email, $code);

    $amountCents = 55000;
    $gateway = 'paystack';
    if (stripos($gatewayRef, 'whop') !== false || str_starts_with($gatewayRef, 'pay_')) {
        $gateway = 'whop';
    }

    $backfillRef = 'backfill_' . substr(hash('sha256', $email . $affiliateId), 0, 28);
    $recorded = auraai_affiliate_handle_member_payment_success(
        $con,
        $email,
        $gateway,
        $backfillRef,
        $amountCents,
        'vps',
        auraai_affiliate_shop_link($code),
        [(object) ['variable_name' => 'ref', 'value' => $code]],
        'ZAR',
        null,
        null
    );

    if (!$recorded) {
        $recorded = auraai_affiliate_record_conversion(
            $con,
            $affiliate,
            $email,
            'vps',
            $gateway,
            $backfillRef,
            $amountCents,
            'ZAR',
            $memberId
        );
    }

    if ($recorded) {
        $stmt = $con->prepare(
            "SELECT commission_cents, amount_cents, gateway_ref FROM affiliate_conversions
             WHERE email = ? AND affiliate_id = ? ORDER BY id DESC LIMIT 1"
        );
        $stmt->bind_param('si', $email, $affiliateId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        echo 'CREDITED: commission=R' . number_format(((int) ($row['commission_cents'] ?? 0)) / 100, 2)
            . ', sale=R' . number_format(((int) ($row['amount_cents'] ?? 0)) / 100, 2)
            . ', ref=' . ($row['gateway_ref'] ?? '') . "\n\n";
        $credited++;
    } else {
        echo "FAILED: could not record conversion\n\n";
        $failed++;
    }
}

$after = auraai_affiliate_dashboard_stats($con, $affiliateId);

echo "=== Summary ===\n";
echo "Credited: {$credited}, Skipped: {$skipped}, Failed: {$failed}\n";
echo "Conversions: {$before['conversions']} -> {$after['conversions']}\n";
echo 'Commission: R' . number_format($before['commission_cents'] / 100, 2)
    . ' -> R' . number_format($after['commission_cents'] / 100, 2) . "\n";

exit($failed > 0 ? 1 : 0);
