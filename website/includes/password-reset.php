<?php
/**
 * NexTradeAI admin — password reset token helpers.
 */

require_once __DIR__ . '/site-config.php';

function auraai_password_reset_ensure_table(mysqli $con): void
{
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_token_hash (token_hash),
        KEY idx_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** @return array{ok:bool,message:string} */
function auraai_password_reset_request(mysqli $con, string $email): array
{
    $email = trim(strtolower($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => true, 'message' => 'If that email is registered, you will receive a reset link shortly.'];
    }

    auraai_password_reset_ensure_table($con);

    $stmt = $con->prepare('SELECT email, displayname, fullname, status FROM admin WHERE LOWER(email) = LOWER(?) LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        return ['ok' => true, 'message' => 'If that email is registered, you will receive a reset link shortly.'];
    }

    if (($user['status'] ?? '') === 'blocked') {
        return ['ok' => true, 'message' => 'If that email is registered, you will receive a reset link shortly.'];
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + 3600);

    $del = $con->prepare('DELETE FROM password_resets WHERE LOWER(email) = LOWER(?)');
    $del->bind_param('s', $email);
    $del->execute();
    $del->close();

    $ins = $con->prepare('INSERT INTO password_resets (email, token_hash, expires_at) VALUES (?, ?, ?)');
    $ins->bind_param('sss', $email, $tokenHash, $expiresAt);
    $ok = $ins->execute();
    $ins->close();

    if (!$ok) {
        error_log('[NexTradeAI Password Reset] Failed to store token for ' . $email);
        return ['ok' => true, 'message' => 'If that email is registered, you will receive a reset link shortly.'];
    }

    require_once __DIR__ . '/email-hooks.php';

    $displayName = trim((string) ($user['displayname'] ?: $user['fullname'] ?: ''));
    $resetUrl = NEXTRADE_ADMIN_URL . 'reset-password.php?token=' . urlencode($token);
    nextrade_email_password_reset($user['email'], $resetUrl, $displayName);

    return ['ok' => true, 'message' => 'If that email is registered, you will receive a reset link shortly.'];
}

/** @return array{ok:bool,email?:string,error?:string} */
function auraai_password_reset_validate(mysqli $con, string $token): array
{
    $token = trim($token);
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
        return ['ok' => false, 'error' => 'This reset link is invalid or has expired.'];
    }

    auraai_password_reset_ensure_table($con);

    $tokenHash = hash('sha256', $token);
    $stmt = $con->prepare('SELECT email, expires_at FROM password_resets WHERE token_hash = ? LIMIT 1');
    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['ok' => false, 'error' => 'This reset link is invalid or has expired.'];
    }

    if (strtotime($row['expires_at']) < time()) {
        $del = $con->prepare('DELETE FROM password_resets WHERE token_hash = ?');
        $del->bind_param('s', $tokenHash);
        $del->execute();
        $del->close();
        return ['ok' => false, 'error' => 'This reset link has expired. Please request a new one.'];
    }

    return ['ok' => true, 'email' => $row['email']];
}

/** @return array{ok:bool,message:string} */
function auraai_password_reset_submit(mysqli $con, string $token, string $password, string $confirmPassword): array
{
    $validation = auraai_password_reset_validate($con, $token);
    if (!$validation['ok']) {
        return ['ok' => false, 'message' => $validation['error'] ?? 'Reset failed.'];
    }

    $password = trim($password);
    $confirmPassword = trim($confirmPassword);

    if ($password === '' || $confirmPassword === '') {
        return ['ok' => false, 'message' => 'Please enter and confirm your new password.'];
    }

    if ($password !== $confirmPassword) {
        return ['ok' => false, 'message' => 'Passwords do not match.'];
    }

    if (strlen($password) < 6) {
        return ['ok' => false, 'message' => 'Password must be at least 6 characters.'];
    }

    if (strlen($password) > 50) {
        return ['ok' => false, 'message' => 'Password must be 50 characters or fewer.'];
    }

    $email = $validation['email'];
    $stmt = $con->prepare('UPDATE admin SET password = ? WHERE LOWER(email) = LOWER(?)');
    $stmt->bind_param('ss', $password, $email);
    $updated = $stmt->execute();
    $stmt->close();

    if (!$updated) {
        error_log('[NexTradeAI Password Reset] Update failed for ' . $email);
        return ['ok' => false, 'message' => 'Could not update password. Please try again.'];
    }

    require_once __DIR__ . '/affiliate.php';
    $affiliate = auraai_affiliate_by_email($con, $email);
    if ($affiliate && !empty($affiliate['admin_id'])) {
        auraai_affiliate_sync_admin_password($con, (int) $affiliate['id'], $password);
    }

    $tokenHash = hash('sha256', trim($token));
    $del = $con->prepare('DELETE FROM password_resets WHERE token_hash = ?');
    $del->bind_param('s', $tokenHash);
    $del->execute();
    $del->close();

    require_once __DIR__ . '/email-hooks.php';
    nextrade_email_password_reset_confirmation($email);

    return ['ok' => true, 'message' => 'Your password has been updated. You can sign in now.'];
}
