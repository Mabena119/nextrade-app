<?php
/**
 * INTEGRATION — admin/home/users.php bulk update (updateusers.php)
 * or single update (updateuser.php) when mentor status changes.
 */

require_once dirname(__DIR__, 2) . '/includes/email-hooks.php';

if ($oldStatus !== $status) {
    nextrade_email_mentor_status($email, $displayname, $status);
}
