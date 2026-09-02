<?php
/**
 * INTEGRATION — shop/notifyb.php (Whop membership / scanner)
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/email-hooks.php';

$memberEmail = trim($email);
if ($memberEmail === '') {
    return;
}

if ($productAction === 'scanner') {
    $memberFound = ($scannerResult['member_action'] ?? '') === 'updated';
    nextrade_email_scanner_payment($memberEmail, $memberFound);
} else {
    nextrade_email_member_payment($memberEmail, 'Whop', false);
}
