<?php
/**
 * INTEGRATION — admin/user_request.php (mentor signup)
 *
 * Form: admin/hostsignup.php → POST admin/user_request.php
 *
 * Add AFTER the mentor record is successfully inserted into the database
 * (before the "Registration Successful" HTML output).
 */

require_once dirname(__DIR__) . '/includes/email-hooks.php';

$signupEmail = isset($email) ? trim($email) : trim($_POST['email'] ?? '');
$signupDisplay = isset($displayname) ? trim($displayname) : trim($_POST['displayname'] ?? '');
$signupPhone = isset($whatsapp) ? trim($whatsapp) : trim($_POST['phone'] ?? '');

if ($signupEmail !== '') {
    nextrade_email_mentor_signup($signupEmail, $signupDisplay, $signupPhone);
}
