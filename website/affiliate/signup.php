<?php
require dirname(__DIR__) . '/admin/php-includes/security-bridge.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/affiliate.php';
auraai_sec_bootstrap();
auraai_sec_session_start();

if (!empty($_SESSION['affiliate_id'])) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$messageType = 'danger';
if (!empty($_GET['error'])) {
    $message = auraai_sec_string(urldecode((string) $_GET['error']), 300) ?? 'Registration failed.';
}
$csrfField = auraai_sec_csrf_field();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#020B18">
    <title>Affiliate Sign Up | NexTradeAI</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="../admin/assets/sitelogo.png">
    <?php include __DIR__ . '/include/styles.php'; ?>
</head>
<body class="auth-page">
    <div class="auth-card card">
        <div class="logo">
            <img src="../admin/assets/sitelogo.png" alt="NexTradeAI">
            <div class="tagline">NexTradeAI</div>
            <h1>Join as Affiliate</h1>
            <p class="muted">Earn 5–10% commission — your rate grows as you refer more members</p>
        </div>
        <?php if ($message !== ''): ?>
            <div class="alert alert-<?php echo auraai_sec_escape($messageType); ?>"><i class="ti ti-alert-circle"></i><span><?php echo auraai_sec_escape($message); ?></span></div>
        <?php endif; ?>
        <form method="post" action="register.php">
            <?php echo $csrfField; ?>
            <div style="position:absolute;left:-9999px;" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
            <div class="form-group"><label for="full_name">Full name</label><input id="full_name" name="full_name" required maxlength="120"></div>
            <div class="form-group"><label for="email">Email</label><input type="email" id="email" name="email" required maxlength="254"></div>
            <div class="form-group"><label for="phone">Phone (optional)</label><input id="phone" name="phone" maxlength="30"></div>
            <div class="form-group"><label for="password">Password</label><input type="password" id="password" name="password" required minlength="6" maxlength="50"></div>
            <div class="form-group"><label for="confirm_password">Confirm password</label><input type="password" id="confirm_password" name="confirm_password" required minlength="6" maxlength="50"></div>
            <button type="submit" class="btn btn-block"><i class="ti ti-user-plus"></i> Create Account</button>
        </form>
        <div class="footer-link"><a href="index.php">Already have an account? Sign in</a></div>
    </div>
</body>
</html>
