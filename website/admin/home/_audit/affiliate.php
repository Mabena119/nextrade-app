<?php
/**
 * Mentor dashboard → affiliate portal bridge.
 * Requires active admin session; links or creates affiliate profile by mentor email.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id']) || empty($_SESSION['username'])) {
    header('Location: ../index.php');
    exit;
}

require_once dirname(__DIR__) . '/php-includes/connect.php';
require_once dirname(__DIR__) . '/php-includes/functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/affiliate.php';

auraai_sec_bootstrap();

$email = (string) $_SESSION['username'];
$adminId = (int) get_admin($email, 'id');
$fullName = (string) (get_admin($email, 'fullname') ?: get_admin($email, 'displayname') ?: 'Mentor');
$phone = (string) (get_admin($email, 'phone') ?? '');
$adminPassword = (string) (get_admin($email, 'password') ?? '');

$result = auraai_affiliate_ensure_for_admin($con, $adminId, $email, $fullName, $phone, $adminPassword);

if (!$result['ok']) {
    header('Location: index.php?affiliate_error=' . urlencode($result['error'] ?? 'Could not open affiliate dashboard.'));
    exit;
}

$_SESSION['affiliate_id'] = (int) $result['id'];
header('Location: /affiliate/dashboard.php?from=mentor');
exit;
