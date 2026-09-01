<?php
/**
 * INTEGRATION — shop/webhook1.php (Paystack webhook)
 *
 * Scanner: paystackSetScannerOnly() then auraai_email_scanner_payment_result().
 * Membership: auraai_email_member_payment_success($email, 'Paystack', false);
 */

require_once dirname(__DIR__) . '/includes/bootstrap.php';
auraai_email_bootstrap();

$memberEmail = trim($memberEmail ?? $customerEmail ?? '');

if ($memberEmail !== '') {
    if (!empty($isScannerPayment)) {
        auraai_email_scanner_payment_result($memberEmail, !empty($memberResult['found']));
    } else {
        auraai_email_member_payment_success($memberEmail, 'Paystack', false);
    }
}
