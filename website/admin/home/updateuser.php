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

$id = auraai_sec_int($_POST['id'] ?? null, 1);
$totalKeys = auraai_sec_int($_POST['total_keys'] ?? null, 0, 100000);
$status = auraai_sec_enum($_POST['status'] ?? '', ['active', 'pending', 'blocked']);

if ($id === null || $totalKeys === null || $status === null) {
    header('Location: users.php?error=missing_fields');
    exit();
}

$stmt = $con->prepare('SELECT email, password, displayname, status FROM admin WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header('Location: users.php?error=user_not_found');
    exit();
}

$email = $user['email'];
$password = $user['password'];
$displayname = $user['displayname'] ?? '';
$oldStatus = $user['status'] ?? '';

$update = $con->prepare('UPDATE admin SET total_keys = ?, status = ? WHERE id = ?');
$update->bind_param('isi', $totalKeys, $status, $id);
$ok = $update->execute();
$update->close();

if (!$ok) {
    error_log('[updateuser] Database update error: ' . mysqli_error($con));
    header('Location: users.php?error=update_failed');
    exit();
}

    if ($oldStatus !== $status) {
        try {
            require_once dirname(__DIR__, 2) . '/includes/email-hooks.php';
            nextrade_email_mentor_status($email, $displayname, $status);
        } catch (Throwable $e) {
            error_log('[NexTradeAI Email] mentor status: ' . $e->getMessage());
        }
    }

if ($status === 'active') {
    require_once dirname(__DIR__, 2) . '/includes/members.php';
    $memberToken = 'admin-active-' . $id;
    $memberResult = auraai_upsert_paid_member($con, $email, $id, $memberToken);
    if (!$memberResult['ok']) {
        error_log('[updateuser] members upsert failed for ' . $email . ' (action=' . ($memberResult['action'] ?? 'unknown') . ')');
        header('Location: users.php?error=member_sync_failed');
        exit();
    }

    // Legacy external hook — non-blocking; members row is source of truth above.
    $url = 'https://hkdk.events/v7wvm47amyb5x2';
    $post_data = ['email' => $email, 'password' => $password];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($post_data),
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
        error_log("[updateuser] hkdk hook failed (non-fatal): $curl_error, HTTP $http_code");
    }
}

header('Location: users.php?success=updated');
exit();
