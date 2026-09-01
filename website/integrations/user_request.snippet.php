<?php
/**
 * INTEGRATION — admin/user_request.php (mentor signup)
 *
 * Form: admin/hostsignup.php → POST admin/user_request.php
 *
 * Add AFTER the mentor record is successfully inserted into the database
 * (before the "Registration Successful" HTML output).
 */

require_once dirname(__DIR__) . '/includes/bootstrap.php';
auraai_email_bootstrap();

// Use your existing POST variables (names from hostsignup.php form):
$signupEmail = isset($email) ? trim($email) : trim($_POST['email'] ?? '');
$signupDisplay = isset($displayname) ? trim($displayname) : trim($_POST['displayname'] ?? '');
$signupPhone = isset($phone) ? trim($phone) : trim($_POST['phone'] ?? '');

if ($signupEmail !== '') {
    auraai_email_mentor_signup_pending($signupEmail, $signupDisplay);
    auraai_email_mentor_signup_admin($signupEmail, $signupDisplay, $signupPhone);
}
