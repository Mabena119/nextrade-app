<?php
require __DIR__ . '/php-includes/security-bridge.php';
auraai_sec_bootstrap();
auraai_sec_session_start();
if (isset($_SESSION['id'])) {
    header('Location: home/index.php');
    exit();
}
$csrfField = auraai_sec_csrf_field();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#020B18">
    <title>Create Account | NexTradeAI</title>
    <link rel="icon" href="assets/sitelogo.png" />
    <link rel="stylesheet" href="/assets/css/platform.css" />
    <style>
        .aura-auth-shell { width: min(520px, 100%); }
        .aura-hint { color: var(--aura-muted-dim); font-size: 0.8rem; margin-top: 0.35rem; }
    </style>
</head>
<body class="aura-platform aura-auth">
    <div class="aura-atmosphere" aria-hidden="true"></div>
    <div class="aura-auth-shell">
        <div class="aura-auth-card">
            <div class="aura-auth-logo">
                <img src="assets/sitelogo.png" alt="NexTradeAI" />
                <h1>Nex<span style="color:var(--aura-cyan)">Trade</span>AI</h1>
                <p>Mentor onboarding</p>
            </div>
            <h2>Create your console</h2>
            <p class="lead">Apply for a mentor account to issue licenses and run cloud VPS for your traders.</p>
            <form action="user_request.php" method="post" enctype="multipart/form-data" id="signupForm">
                <?php echo $csrfField; ?>
                <div style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
                    <input type="text" name="website" tabindex="-1" autocomplete="off">
                </div>
                <div class="aura-field">
                    <label for="fullname">Full name</label>
                    <input class="aura-input" type="text" name="fullname" id="fullname" required autocomplete="name" placeholder="Your name" />
                </div>
                <div class="aura-field">
                    <label for="displayname">Display / brand name</label>
                    <input class="aura-input" type="text" name="displayname" id="displayname" required placeholder="Your trading brand" />
                </div>
                <div class="aura-field">
                    <label for="email">Email</label>
                    <input class="aura-input" type="email" name="email" id="email" required autocomplete="email" placeholder="you@domain.com" />
                </div>
                <div class="aura-field">
                    <label for="phone">WhatsApp number</label>
                    <input class="aura-input" type="text" name="phone" id="phone" required autocomplete="tel" placeholder="+27…" />
                </div>
                <div class="aura-field">
                    <label for="password">Password</label>
                    <input class="aura-input" type="password" name="password" id="password" required autocomplete="new-password" placeholder="Create a password" />
                </div>
                <input type="url" name="instagram" value="" hidden>
                <input type="url" name="tiktok" value="" hidden>
                <input type="url" name="telegram" value="" hidden>
                <input type="file" name="ea_file" accept=".ex4,.ex5" hidden>
                <input type="hidden" name="eamerge" value="true">
                <button class="aura-btn aura-btn-primary aura-btn-block" type="submit" name="login" value="1">Submit application</button>
            </form>
            <div class="aura-auth-links">
                Already have an account? <a href="index.php">Sign in</a>
            </div>
        </div>
    </div>
</body>
</html>
