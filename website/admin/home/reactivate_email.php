<?php
	session_start();
	require("../php-includes/connect.php");
	require("../php-includes/functions.php");
	require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/site-config.php';

	if (!isset($_SESSION['id']) || !get_admin($_SESSION['username'], 'super')) {
		header('Location: aemail.php');
		exit();
	}

	function generateRandomToken() {
		$randomBytes = random_bytes(16); // 16 bytes = 128 bits
	
		// Set the version and variant bits (13th and 17th bytes)
		$randomBytes[6] = chr(ord($randomBytes[6]) & 0x0F | 0x40); // version 4
		$randomBytes[8] = chr(ord($randomBytes[8]) & 0x3F | 0x80); // variant 10
	
		// Format the UUID
		$uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($randomBytes), 4));
	
		return $uuid;
	}
	
	if(isset($_GET['email']))
	{
		$email = mysqli_real_escape_string($con,$_GET['email']);
		$query = mysqli_query($con,"select * from members where email='$email'");
		if(mysqli_num_rows($query) > 0)
		{
			$query1 = mysqli_query($con,"UPDATE members SET used=false WHERE email='$email'");
			// Show success page
			?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Email Reactivated | NexTradeAI Admin</title>
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
            background: linear-gradient(135deg, #0d0d0d 0%, #1a1a1a 100%);
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
            background: radial-gradient(circle at 20% 50%, rgba(0, 123, 255, 0.1) 0%, transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(0, 123, 255, 0.05) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        .message-container {
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

        .message-card {
            background-color: #1a1a1a;
            border: 1px solid #333;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5),
                        0 0 0 1px rgba(255, 255, 255, 0.05);
            padding: 4rem 3rem;
            text-align: center;
            backdrop-filter: blur(10px);
        }

        .message-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            animation: scaleIn 0.5s ease-out 0.2s both;
            box-shadow: 0 8px 24px rgba(0, 123, 255, 0.3);
        }

        .message-icon.error {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            box-shadow: 0 8px 24px rgba(220, 53, 69, 0.3);
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }

        .message-icon i {
            font-size: 3rem;
            color: #ffffff;
        }

        .message-title {
            font-size: 2rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 1rem;
            letter-spacing: -0.3px;
        }

        .message-text {
            font-size: 1.125rem;
            color: #cccccc;
            line-height: 1.6;
            margin-bottom: 2.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
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
            box-shadow: 0 8px 20px rgba(0, 123, 255, 0.4);
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
            max-width: 120px;
            height: auto;
            filter: drop-shadow(0 4px 12px rgba(0, 123, 255, 0.3));
        }

        @media (max-width: 768px) {
            .message-card {
                padding: 3rem 2rem;
            }

            .message-icon {
                width: 80px;
                height: 80px;
                margin-bottom: 1.5rem;
            }

            .message-icon i {
                font-size: 2.5rem;
            }

            .message-title {
                font-size: 1.75rem;
            }

            .message-text {
                font-size: 1rem;
                margin-bottom: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="message-container">
        <div class="message-card">
            <div class="logo-wrapper">
                <img src="<?php echo htmlspecialchars(NEXTRADE_LOGO_URL, ENT_QUOTES, 'UTF-8'); ?>" alt="NexTradeAI" width="56" height="56" />
            </div>
            <div class="message-icon">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <h1 class="message-title">Email Reactivated!</h1>
            <p class="message-text">
                The email address has been successfully reactivated. The user can now access their account again.
            </p>
            <a href="aemail.php" class="btn-primary">Go Back</a>
        </div>
    </div>
    <script>
        setTimeout(function() {
            window.location.href = 'aemail.php';
        }, 5000);
			</script>
</body>
</html>
			<?php
			exit;
		} else {
			// Show error page
			?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Account Not Found | NexTradeAI Admin</title>
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
            background: linear-gradient(135deg, #0d0d0d 0%, #1a1a1a 100%);
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
            background: radial-gradient(circle at 20% 50%, rgba(220, 53, 69, 0.1) 0%, transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(220, 53, 69, 0.05) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        .message-container {
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

        .message-card {
            background-color: #1a1a1a;
            border: 1px solid #333;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5),
                        0 0 0 1px rgba(255, 255, 255, 0.05);
            padding: 4rem 3rem;
            text-align: center;
            backdrop-filter: blur(10px);
        }

        .message-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            animation: scaleIn 0.5s ease-out 0.2s both;
            box-shadow: 0 8px 24px rgba(220, 53, 69, 0.3);
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }

        .message-icon i {
            font-size: 3rem;
            color: #ffffff;
        }

        .message-title {
            font-size: 2rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 1rem;
            letter-spacing: -0.3px;
        }

        .message-text {
            font-size: 1.125rem;
            color: #cccccc;
            line-height: 1.6;
            margin-bottom: 2.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
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
            box-shadow: 0 8px 20px rgba(220, 53, 69, 0.4);
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
            max-width: 120px;
            height: auto;
            filter: drop-shadow(0 4px 12px rgba(220, 53, 69, 0.3));
        }

        @media (max-width: 768px) {
            .message-card {
                padding: 3rem 2rem;
            }

            .message-icon {
                width: 80px;
                height: 80px;
                margin-bottom: 1.5rem;
            }

            .message-icon i {
                font-size: 2.5rem;
            }

            .message-title {
                font-size: 1.75rem;
            }

            .message-text {
                font-size: 1rem;
                margin-bottom: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="message-container">
        <div class="message-card">
            <div class="logo-wrapper">
                <img src="<?php echo htmlspecialchars(NEXTRADE_LOGO_URL, ENT_QUOTES, 'UTF-8'); ?>" alt="NexTradeAI" width="56" height="56" />
            </div>
            <div class="message-icon">
                <i class="bi bi-x-circle-fill"></i>
            </div>
            <h1 class="message-title">Account Not Found</h1>
            <p class="message-text">
                The email address you entered does not exist in our system. Please verify the email address and try again.
            </p>
            <a href="aemail.php" class="btn-primary">Go Back</a>
        </div>
    </div>
    <script>
        setTimeout(function() {
            window.location.href = 'aemail.php';
        }, 5000);
    </script>
</body>
</html>
			<?php
			exit;
		}
	}
?>
