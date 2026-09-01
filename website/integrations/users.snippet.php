<?php
/**
 * INTEGRATION — admin/home/users.php (mentor status: active / pending / blocked)
 *
 * Add INSIDE the block that handles status updates (POST/AJAX), AFTER the
 * database UPDATE succeeds and you know the new status + mentor email.
 *
 * Example variables — replace with your actual column/variable names:
 *   $userEmail, $userDisplayName, $newStatus
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
auraai_email_bootstrap();

// Only email when status actually changed (recommended):
// if (isset($oldStatus) && strtolower($oldStatus) !== strtolower($newStatus)) { ... }

$userEmail = trim($userEmail ?? $_POST['email'] ?? '');
$userDisplayName = trim($userDisplayName ?? $_POST['displayname'] ?? $_POST['name'] ?? '');
$newStatus = trim($newStatus ?? $_POST['status'] ?? '');

if ($userEmail !== '' && $newStatus !== '') {
    auraai_email_mentor_status_changed($userEmail, $userDisplayName, $newStatus);
}
