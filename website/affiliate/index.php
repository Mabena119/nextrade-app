<?php
require dirname(__DIR__) . '/admin/php-includes/security-bridge.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/affiliate.php';
auraai_sec_bootstrap();
auraai_sec_session_start();

if (!empty($_SESSION['affiliate_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if (isset($_GET['error'])) {
    $map = [
        'auth' => 'Invalid email or password.',
        'blocked' => 'Your affiliate account has been blocked. Contact support if you believe this is a mistake.',
        'inactive' => 'Your affiliate account is not active. Contact support.',
        'csrf' => 'Security token expired. Try again.',
    ];
    $error = $map[$_GET['error']] ?? '';
}
$csrfField = auraai_sec_csrf_field();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#020B18">
    <title>Affiliate Sign In | NexTradeAI</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="../admin/assets/sitelogo.png">
    <?php include __DIR__ . '/include/styles.php'; ?>
</head>
<body class="auth-page">
    <div class="auth-card card">
        <div class="logo">
            <img src="../admin/assets/sitelogo.png" alt="NexTradeAI">
            <div class="tagline">NexTradeAI</div>
            <h1>Affiliate Portal</h1>
            <p class="muted">Sign in to track referrals and commissions</p>
        </div>
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><i class="ti ti-alert-circle"></i><span><?php echo auraai_sec_escape($error); ?></span></div>
        <?php endif; ?>
        <form method="post" action="login.php">
            <?php echo $csrfField; ?>
            <div style="position:absolute;left:-9999px;" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autocomplete="email">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-block"><i class="ti ti-login"></i> Sign In</button>
        </form>
        <div class="footer-link">
            <a href="signup.php">Create affiliate account</a>
        </div>
    </div>
</body>
</html>
