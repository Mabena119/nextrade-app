<?php
ob_start();
require __DIR__ . '/php-includes/security-bridge.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/admin-chrome.php';
auraai_sec_bootstrap();
auraai_sec_session_start();

if (isset($_SESSION['id'])) {
    header('Location: home/index.php');
    ob_end_flush();
    exit();
}

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    auraai_sec_require_method('POST');
    auraai_sec_rate_limit_or_exit('admin_forgot_password', 5, 900);
    auraai_sec_csrf_require();
    auraai_sec_honeypot_require('website');

    require __DIR__ . '/php-includes/connect.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/password-reset.php';

    $email = auraai_sec_email($_POST['email'] ?? '');
    if ($email === null) {
        $message = 'Please enter a valid email address.';
        $messageType = 'danger';
    } else {
        $result = auraai_password_reset_request($con, $email);
        $message = $result['message'];
        $messageType = 'success';
    }
}
$csrfField = auraai_sec_csrf_field();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#020B18">
    <title>Forgot Password | NexTradeAI</title>
    <link rel="icon" href="<?php echo htmlspecialchars(NEXTRADE_LOGO_URL, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="stylesheet" href="/assets/css/platform.css" />
</head>
<body class="aura-platform aura-auth">
    <div class="aura-atmosphere" aria-hidden="true"></div>
    <div class="aura-auth-shell">
        <div class="aura-auth-card">
            <?php nextrade_admin_auth_logo('Password recovery'); ?>
            <h2>Forgot password</h2>
            <p class="lead">Enter your mentor email and we’ll send a secure reset link.</p>
            <?php if ($message !== ''): ?>
            <div class="aura-alert aura-alert-<?php echo $messageType === 'danger' ? 'danger' : 'success'; ?>">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php endif; ?>
            <form method="post" action="forgot-password.php">
                <?php echo $csrfField; ?>
                <div style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
                    <input type="text" name="website" tabindex="-1" autocomplete="off">
                </div>
                <div class="aura-field">
                    <label for="email">Email</label>
                    <input class="aura-input" type="email" name="email" id="email" required autocomplete="email"
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars(trim($_POST['email']), ENT_QUOTES, 'UTF-8') : ''; ?>"
                        placeholder="you@domain.com" />
                </div>
                <button class="aura-btn aura-btn-primary aura-btn-block" type="submit">Send reset link</button>
            </form>
            <div class="aura-auth-links">
                <a href="index.php">← Back to sign in</a>
            </div>
            <?php nextrade_admin_auth_footer(); ?>
        </div>
    </div>
</body>
</html>
<?php ob_end_flush(); ?>
