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
$csrfField = auraai_sec_csrf_field();
$loginError = '';
if (isset($_GET['error'])) {
    $errors = [
        'csrf' => 'Security token expired. Please try again.',
        'auth' => 'Email or password is incorrect.',
        'invalid' => 'Please enter a valid email and password.',
        'pending' => 'Your account is still pending activation.',
        'blocked' => 'This account has been deactivated.',
    ];
    $loginError = $errors[$_GET['error']] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#020B18">
    <title>Sign In | NexTradeAI</title>
    <link rel="icon" href="assets/sitelogo.png" />
    <link rel="stylesheet" href="/assets/css/platform.css" />
</head>
<body class="aura-platform aura-auth">
    <div class="aura-atmosphere" aria-hidden="true"></div>
    <div class="aura-auth-shell">
        <div class="aura-auth-card">
            <div class="aura-auth-logo">
                <img src="assets/sitelogo.png" alt="NexTradeAI" />
                <h1>Aura<span style="color:var(--aura-cyan)">AI</span>VPS</h1>
                <p>Mentor console</p>
            </div>
            <h2>Welcome back</h2>
            <p class="lead">Sign in to manage licenses, members, and cloud VPS access.</p>
            <?php if ($loginError !== ''): ?>
            <div class="aura-alert aura-alert-danger"><?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <form action="login.php" method="POST" autocomplete="on">
                <?php echo $csrfField; ?>
                <div class="aura-field">
                    <label for="email">Email</label>
                    <input class="aura-input" type="email" id="email" name="email" required placeholder="you@domain.com" />
                </div>
                <div class="aura-field">
                    <label for="password">Password</label>
                    <input class="aura-input" type="password" id="password" name="password" required placeholder="••••••••" />
                </div>
                <button class="aura-btn aura-btn-primary aura-btn-block" type="submit">Sign in</button>
            </form>
            <div class="aura-auth-links">
                <a href="forgot-password.php">Forgot password?</a>
                <span style="opacity:.4"> · </span>
                <a href="hostsignup.php">Create mentor account</a>
            </div>
        </div>
    </div>
</body>
</html>
<?php ob_end_flush(); ?>
