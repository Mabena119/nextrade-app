<?php
/**
 * Send all NexTradeAI email templates via production mailer.
 * Usage: php test-all-emails.php recipient@example.com
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$to = trim($argv[1] ?? '');
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php test-all-emails.php recipient@example.com\n");
    exit(1);
}

$root = dirname(__DIR__);
require_once $root . '/includes/bootstrap.php';
auraai_email_bootstrap();

$tests = [
    'mentor_signup_pending' => fn () => auraai_email_mentor_signup_pending($to, 'Test Mentor'),
    'mentor_signup_admin' => fn () => auraai_email_mentor_signup_admin($to, 'Test Mentor', '+27123456789'),
    'mentor_status_active' => fn () => auraai_email_mentor_status_changed($to, 'Test Mentor', 'active'),
    'mentor_status_pending' => fn () => auraai_email_mentor_status_changed($to, 'Test Mentor', 'pending'),
    'mentor_status_blocked' => fn () => auraai_email_mentor_status_changed($to, 'Test Mentor', 'blocked'),
    'member_whop' => fn () => auraai_email_member_joined($to, 'Whop', false),
    'member_paystack' => fn () => auraai_email_member_joined($to, 'Paystack', false),
    'scanner_activated' => fn () => auraai_email_scanner_activated($to),
    'scanner_payment_found' => fn () => auraai_email_scanner_payment_result($to, true),
    'scanner_payment_not_found' => fn () => auraai_email_scanner_payment_result($to, false),
    'license_key' => fn () => auraai_email_license_key($to, 'ABC-123-DEF-456', 'AURA AI', 'Test Mentor'),
    'password_reset' => fn () => auraai_email_password_reset($to, AURAAI_ADMIN_LOGIN . 'reset-password.php?token=test', 'Test Admin'),
    'password_reset_confirmation' => fn () => auraai_email_password_reset_confirmation($to),
    'affiliate_welcome' => fn () => auraai_email_affiliate_welcome($to, 'Test Affiliate', AURAAI_SHOP_URL . '?ref=TESTCODE', false),
    'affiliate_commission' => fn () => auraai_email_affiliate_commission_earned($to, 'Test Affiliate', 'R27.50', 'VPS Membership', 5.0),
    'affiliate_withdrawal_requested' => fn () => auraai_email_affiliate_withdrawal_requested($to, 'Test Affiliate', 'R100.00', 'Bank transfer'),
    'affiliate_withdrawal_admin' => fn () => auraai_email_affiliate_withdrawal_admin('Test Affiliate', $to, AURAAI_SHOP_URL . '?ref=TESTCODE', 'R100.00', 'Bank transfer', 'Test Bank · 1234567890'),
    'affiliate_withdrawal_paid' => fn () => auraai_email_affiliate_withdrawal_status($to, 'Test Affiliate', 'R100.00', 'paid', 'Processed via EFT'),
];

echo "Sending " . count($tests) . " NexTradeAI templates to {$to}...\n\n";

$passed = 0;
$failed = 0;

foreach ($tests as $name => $sender) {
    try {
        $ok = (bool) $sender();
        if ($ok) {
            echo "[OK]   {$name}\n";
            $passed++;
        } else {
            echo "[FAIL] {$name} — " . auraai_email_last_error() . "\n";
            $failed++;
        }
    } catch (Throwable $e) {
        echo "[FAIL] {$name} — " . $e->getMessage() . "\n";
        $failed++;
    }
    usleep(400000);
}

echo "\nDone: {$passed}/" . count($tests) . " sent";
if ($failed > 0) {
    echo ", {$failed} failed";
}
echo ".\n";

exit($failed > 0 ? 1 : 0);
