<?php
/**
 * INTEGRATION — shop/notifyb.php (Whop webhook)
 *
 * Scanner: only UPDATE members.scanner = 1 (no insert). Email via auraai_email_scanner_payment_result().
 * VPS membership: auraai_email_member_payment_success($email, 'Whop', false);
 */

require_once dirname(__DIR__) . '/includes/bootstrap.php';
auraai_email_bootstrap();

$memberEmail = trim($memberEmail ?? $email ?? '');

if ($memberEmail !== '') {
    if (!empty($isScanner)) {
        $memberFound = (($scannerResult['member_action'] ?? '') === 'updated');
        auraai_email_scanner_payment_result($memberEmail, $memberFound);
    } else {
        auraai_email_member_payment_success($memberEmail, 'Whop', false);
    }
}
