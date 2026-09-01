<?php
require __DIR__ . '/php-includes/security-bridge.php';
auraai_sec_bootstrap();
auraai_sec_session_start();
auraai_sec_require_method('POST');
auraai_sec_rate_limit_or_exit('mentor_signup', 5, 3600);
auraai_sec_csrf_require();
auraai_sec_honeypot_require('website');

require __DIR__ . '/php-includes/connect.php';
require __DIR__ . '/php-includes/functions.php';

function mentor_signup_fail(string $message, string $redirect = 'hostsignup.php'): void
{
    echo '<script>alert(' . json_encode($message) . ');window.location.assign(' . json_encode($redirect) . ');</script>';
    exit;
}

$fullname = auraai_sec_string($_POST['fullname'] ?? '', 50, 2);
$displayname = auraai_sec_string($_POST['displayname'] ?? '', 100, 2);
$email = auraai_sec_email($_POST['email'] ?? '');
$whatsapp = auraai_sec_string($_POST['phone'] ?? '', 30, 6);
$instagram = auraai_sec_string($_POST['instagram'] ?? '', 255, 0) ?? '';
$tiktok = auraai_sec_string($_POST['tiktok'] ?? '', 255, 0) ?? '';
$telegram = auraai_sec_string($_POST['telegram'] ?? '', 255, 0) ?? '';
$password = auraai_sec_password($_POST['password'] ?? '', 6, 50);

if ($fullname === null || $displayname === null || $email === null || $whatsapp === null || $password === null) {
    mentor_signup_fail('Please fill all required fields correctly.');
}

$filePath = '';
if (isset($_FILES['ea_file']) && ($_FILES['ea_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $upload = auraai_sec_validate_upload($_FILES['ea_file'], __DIR__ . '/uploads', ['ex5', 'ex4', 'mq5', 'zip']);
    if (!$upload['ok']) {
        mentor_signup_fail($upload['error'] ?? 'Invalid file upload.');
    }
    $filePath = $upload['path'] ?? '';
}

$check = $con->prepare('SELECT id FROM admin WHERE email = ? LIMIT 1');
$check->bind_param('s', $email);
$check->execute();
$exists = $check->get_result()->fetch_assoc();
$check->close();

if ($exists) {
    mentor_signup_fail('Email already exists. Please login instead.', 'index.php');
}

$isHost = isset($_POST['eahost']) && $_POST['eahost'] === 'true';
$isMigrate = isset($_POST['eamigrate']) && $_POST['eamigrate'] === 'true';
$isVault = isset($_POST['eavault']) && $_POST['eavault'] === 'true';

if ($isHost) {
    $stmt = $con->prepare('INSERT INTO admin (email, fullname, phone, displayname, password, powerhost) VALUES (?, ?, ?, ?, ?, 1)');
    $stmt->bind_param('sssss', $email, $fullname, $whatsapp, $displayname, $password);
} elseif ($isMigrate) {
    $stmt = $con->prepare('INSERT INTO admin (email, fullname, phone, instagram, tiktok, telegram, displayname, password, ea_file, eamigrate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
    $stmt->bind_param('sssssssss', $email, $fullname, $whatsapp, $instagram, $tiktok, $telegram, $displayname, $password, $filePath);
} elseif ($isVault) {
    $stmt = $con->prepare('INSERT INTO admin (email, fullname, phone, instagram, tiktok, telegram, displayname, password, ea_file, eavault) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
    $stmt->bind_param('sssssssss', $email, $fullname, $whatsapp, $instagram, $tiktok, $telegram, $displayname, $password, $filePath);
} else {
    $stmt = $con->prepare('INSERT INTO admin (email, fullname, phone, instagram, tiktok, telegram, displayname, password, ea_file) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('sssssssss', $email, $fullname, $whatsapp, $instagram, $tiktok, $telegram, $displayname, $password, $filePath);
}

$query_reg = $stmt->execute();
$stmt->close();

if ($query_reg) {
    try {
        require_once dirname(__DIR__) . '/includes/bootstrap.php';
        auraai_email_bootstrap();
        auraai_email_mentor_signup_pending($email, $displayname);
        auraai_email_mentor_signup_admin($email, $displayname, $whatsapp);
    } catch (Throwable $e) {
        error_log('[NexTradeAI Email] mentor signup: ' . $e->getMessage());
    }
    // Show success page instead of alert
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/admin-chrome.php';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Registration Successful | NexTradeAI</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="shortcut icon" href="<?php echo htmlspecialchars(NEXTRADE_LOGO_URL, ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="icon" href="<?php echo htmlspecialchars(NEXTRADE_LOGO_URL, ENT_QUOTES, 'UTF-8'); ?>" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #020B18 0%, #0A1628 100%);
            color: #ffffff;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 50%, rgba(0, 229, 255, 0.1) 0%, transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(0, 229, 255, 0.05) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        .success-container {
            width: 100%;
            max-width: 600px;
            position: relative;
            z-index: 1;
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .success-card {
            background-color: #0A1628;
            border: 1px solid #333;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5),
                        0 0 0 1px rgba(255, 255, 255, 0.05);
            padding: 4rem 3rem;
            text-align: center;
            backdrop-filter: blur(10px);
        }

        .success-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #00A8FF 0%, #0077CC 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            animation: scaleIn 0.5s ease-out 0.2s both;
            box-shadow: 0 8px 24px rgba(0, 168, 255, 0.3);
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }

        .success-icon i {
            font-size: 3rem;
            color: #ffffff;
        }

        .success-title {
            font-size: 2rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 1rem;
            letter-spacing: -0.3px;
        }

        .success-message {
            font-size: 1.125rem;
            color: #cccccc;
            line-height: 1.6;
            margin-bottom: 2.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #00A8FF 0%, #0077CC 100%);
            color: #ffffff;
            border: none;
            padding: 1rem 2.5rem;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            cursor: pointer;
            letter-spacing: 0.3px;
            position: relative;
            overflow: hidden;
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 229, 255, 0.4);
            text-decoration: none;
            color: #ffffff;
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .logo-wrapper {
            margin-bottom: 2rem;
        }

        .logo-wrapper img {
            width: 56px;
            height: 56px;
            object-fit: contain;
            filter: drop-shadow(0 4px 12px rgba(0, 229, 255, 0.3));
        }

        @media (max-width: 768px) {
            .success-card {
                padding: 3rem 2rem;
            }

            .success-icon {
                width: 80px;
                height: 80px;
                margin-bottom: 1.5rem;
            }

            .success-icon i {
                font-size: 2.5rem;
            }

            .success-title {
                font-size: 1.75rem;
            }

            .success-message {
                font-size: 1rem;
                margin-bottom: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-card">
            <div class="logo-wrapper">
                <img src="<?php echo htmlspecialchars(NEXTRADE_LOGO_URL, ENT_QUOTES, 'UTF-8'); ?>" alt="NexTradeAI" width="56" height="56" />
            </div>
            <div class="success-icon">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <h1 class="success-title">Registration Successful!</h1>
            <p class="success-message">
                Thank you for joining us! Your account will be activated shortly, then you can log in to access the admin dashboard.
            </p>
            <a href="index.php" class="btn-primary">Go to Login</a>
        </div>
    </div>
    <script>
        // Auto-redirect after 5 seconds
        setTimeout(function() {
            window.location.href = 'index.php';
        }, 5000);
    </script>
</body>
</html>
                <?php
                exit;
} else {
    mentor_signup_fail('Error registering user. Please try again.');
}