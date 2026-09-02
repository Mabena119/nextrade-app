<?php
	if (session_status() === PHP_SESSION_NONE) {
		session_start();
	}
	if(!isset($_SESSION['id']))
	{
		header("location:../index.php");
		exit();
	}
	require_once("../php-includes/functions.php");
	require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/admin-chrome.php';
	$adminCurrentPage = basename($_SERVER['PHP_SELF'] ?? '');
	if (!function_exists('admin_nav_active')) {
		function admin_nav_active($pages) {
			global $adminCurrentPage;
			$arr = is_array($pages) ? $pages : array($pages);
			return in_array($adminCurrentPage, $arr, true) ? 'active' : '';
		}
	}
	$__console_css_v = (string) (@filemtime(__DIR__ . '/../../assets/css/aura-console.css') ?: time());
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta name="theme-color" content="#020B18">
  <title>NexTradeAI Console</title>
  <link rel="icon" href="<?php echo htmlspecialchars(NEXTRADE_LOGO_URL, ENT_QUOTES, 'UTF-8'); ?>" />
  <link rel="stylesheet" href="/assets/css/platform.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.19.0/dist/tabler-icons.min.css" />
  <?php if ( ! ( $GLOBALS['admin_light_assets'] ?? false ) ): ?>
  <link rel="stylesheet" href="vendors/datatables.net-bs4/dataTables.bootstrap4.css">
  <?php endif; ?>
  <link rel="stylesheet" href="../assets/css/aura-console.css?v=<?php echo htmlspecialchars($__console_css_v, ENT_QUOTES, 'UTF-8'); ?>" />
</head>
<body class="aura-platform aura-console">
  <div class="aura-atmosphere" aria-hidden="true"></div>
  <div class="aura-console-app">
    <aside class="aura-console-sidebar" id="auraSidebar" aria-label="Console navigation">
      <div class="aura-console-brand">
        <img src="<?php echo htmlspecialchars(NEXTRADE_LOGO_URL, ENT_QUOTES, 'UTF-8'); ?>" alt="NexTradeAI" width="40" height="40" />
        <strong>Nex<span style="color:var(--aura-cyan)">Trade</span>AI</strong>
        <span>Mentor console</span>
      </div>
      <ul class="aura-console-nav">
        <li><a class="<?php echo admin_nav_active('index.php'); ?>" href="index.php"><i class="ti ti-layout-dashboard"></i> Dashboard</a></li>
        <li><a class="<?php echo admin_nav_active('key.php'); ?>" href="key.php"><i class="ti ti-key"></i> Licence keys</a></li>
        <li><a class="<?php echo admin_nav_active('EA.php'); ?>" href="EA.php"><i class="ti ti-cpu"></i> EAs</a></li>
        <?php if(get_admin($_SESSION['username'],"super")){?>
        <li><a class="<?php echo admin_nav_active(array('users.php', 'usersb.php', 'usersc.php', 'usersd.php')); ?>" href="users.php"><i class="ti ti-users"></i> Users</a></li>
        <?php if(!get_admin($_SESSION['username'],"powerhost") && !get_admin($_SESSION['username'],"eamigrate") && !get_admin($_SESSION['username'],"eavault")){?>
        <li><a class="<?php echo admin_nav_active('blocked-ips.php'); ?>" href="blocked-ips.php"><i class="ti ti-shield-lock"></i> Security</a></li>
        <?php }?>
        <?php }?>
        <li><a class="<?php echo admin_nav_active('alicense.php'); ?>" href="alicense.php"><i class="ti ti-refresh"></i> Restore licence</a></li>
        <?php if(get_admin($_SESSION['username'],"super")){?>
        <li><a class="<?php echo admin_nav_active(array('aemail.php', 'aemailb.php', 'aemailc.php', 'aemaild.php')); ?>" href="aemail.php"><i class="ti ti-mail"></i> Restore email</a></li>
        <?php }?>
        <li><a class="<?php echo admin_nav_active('copy_trades.php'); ?>" href="copy_trades.php"><i class="ti ti-arrows-shuffle"></i> Copy trades</a></li>
        <li><a class="<?php echo admin_nav_active('stats.php'); ?>" href="stats.php"><i class="ti ti-chart-dots"></i> Analytics</a></li>
        <li class="aura-console-nav-spacer" aria-hidden="true"></li>
        <li><a href="../php-includes/logout.php"><i class="ti ti-logout"></i> Sign out</a></li>
      </ul>
    </aside>

    <div class="aura-console-backdrop" id="auraSidebarBackdrop"></div>

    <div class="aura-console-body">
      <header class="aura-console-topbar">
        <button type="button" class="aura-console-menu-btn" id="auraMenuBtn" aria-label="Open menu"><i class="ti ti-menu-2"></i></button>
        <div class="aura-console-topbar-title">Mentor console</div>
        <div class="aura-console-profile">
          <button type="button" class="aura-console-profile-btn" id="auraProfileBtn" aria-haspopup="true">
            <?php
            $__uploadsFs = realpath(__DIR__ . '/../uploads');
            if ($__uploadsFs === false) {
                $__uploadsFs = dirname(__DIR__) . '/uploads';
            }
            $__avatarSrc = $GLOBALS['nextrade_admin_avatar_src'] ?? nextrade_admin_avatar_src(
                get_admin($_SESSION['username'], 'image'),
                $__uploadsFs
            );
            ?>
            <img src="<?php echo htmlspecialchars($__avatarSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile" onerror="this.onerror=null;this.src='../assets/sitelogo.png'" />
            <i class="ti ti-chevron-down" style="font-size:0.85rem;color:var(--aura-muted);"></i>
          </button>
          <div class="aura-console-profile-menu" id="auraProfileMenu">
            <a href="profile.php"><i class="ti ti-user"></i> Account</a>
            <a href="../php-includes/logout.php"><i class="ti ti-logout"></i> Sign out</a>
          </div>
        </div>
      </header>

      <main class="aura-console-main">
