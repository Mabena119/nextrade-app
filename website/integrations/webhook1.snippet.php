<?php
/**
 * INTEGRATION — shop/webhook1.php / shop/notifyb.php (member payments)
 *
 * After members.paid = 1 (and optionally members.scanner = 1 for AI Scanner):
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/email-hooks.php';

$memberEmail = trim($email); // your extracted customer email
$isScanner = false;          // set true when scanner column is activated

if ($memberEmail !== '') {
    if ($isScanner) {
        nextrade_email_scanner_payment($memberEmail, true);
    } else {
        nextrade_email_member_payment($memberEmail, 'Paystack', false); // or 'Whop'
    }
}
