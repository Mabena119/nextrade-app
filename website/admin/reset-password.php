<?php
ob_start();
require __DIR__ . '/php-includes/security-bridge.php';
auraai_sec_bootstrap();
auraai_sec_session_start();

if (isset($_SESSION['id'])) {
    header('Location: home/index.php');
    ob_end_flush();
    exit();
}

require __DIR__ . '/php-includes/connect.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/password-reset.php';

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$message = '';
$messageType = 'danger';
$tokenValid = false;
$email = '';

if ($token !== '') {
    $validation = auraai_password_reset_validate($con, $token);
    if ($validation['ok']) {
        $tokenValid = true;
        $email = $validation['email'] ?? '';
    } else {
        $message = $validation['error'] ?? 'This reset link is invalid or has expired.';
    }
} else {
    $message = 'Missing reset token. Please use the link from your email.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token !== '') {
    auraai_sec_require_method('POST');
    auraai_sec_rate_limit_or_exit('admin_reset_password', 8, 900);
    auraai_sec_csrf_require();

    $result = auraai_password_reset_submit(
        $con,
        $token,
        $_POST['password'] ?? '',
        $_POST['confirm_password'] ?? ''
    );

    if ($result['ok']) {
        header('Location: index.php?reset=success');
        ob_end_flush();
        exit();
    }

    $message = $result['message'];
    $messageType = 'danger';
    $validation = auraai_password_reset_validate($con, $token);
    $tokenValid = !empty($validation['ok']);
    $email = $validation['email'] ?? $email;
}
$csrfField = auraai_sec_csrf_field();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#020B18">
    <title>Reset Password | NexTradeAI</title>
    <link rel="icon" href="assets/sitelogo.png" />
    <link rel="stylesheet" href="/assets/css/platform.css" />
</head>
<body class="aura-platform aura-auth">
    <div class="aura-atmosphere" aria-hidden="true"></div>
    <div class="aura-auth-shell">
        <div class="aura-auth-card">
            <div class="aura-auth-logo">
                <img src="assets/sitelogo.png" alt="NexTradeAI" />
                <h1>Nex<span style="color:var(--aura-cyan)">Trade</span>AI</h1>
                <p>Set a new password</p>
            </div>
            <h2>Reset password</h2>
            <?php if ($tokenValid): ?>
            <p class="lead">Choose a new password for <strong style="color:#fff;"><?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></strong>.</p>
            <?php else: ?>
            <p class="lead">This reset link is no longer valid.</p>
            <?php endif; ?>
            <?php if ($message !== ''): ?>
            <div class="aura-alert aura-alert-<?php echo $messageType === 'danger' ? 'danger' : 'success'; ?>">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php endif; ?>
            <?php if ($tokenValid): ?>
            <form method="post" action="reset-password.php">
                <input type="hidden" name="token" value="<?php echo auraai_sec_escape($token); ?>">
                <?php echo $csrfField; ?>
                <div class="aura-field">
                    <label for="password">New password</label>
                    <input class="aura-input" type="password" name="password" id="password" required minlength="6" maxlength="50" autocomplete="new-password" placeholder="At least 6 characters" />
                </div>
                <div class="aura-field">
                    <label for="confirm_password">Confirm password</label>
                    <input class="aura-input" type="password" name="confirm_password" id="confirm_password" required minlength="6" maxlength="50" autocomplete="new-password" placeholder="Re-enter password" />
                </div>
                <button class="aura-btn aura-btn-primary aura-btn-block" type="submit">Update password</button>
            </form>
            <?php endif; ?>
            <div class="aura-auth-links">
                <?php if (!$tokenValid): ?>
                <a href="forgot-password.php">Request a new reset link</a><br><br>
                <?php endif; ?>
                <a href="index.php">← Back to sign in</a>
            </div>
        </div>
    </div>
</body>
</html>
<?php ob_end_flush(); ?>
