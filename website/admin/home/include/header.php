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
  <link rel="icon" href="../assets/sitelogo.png" />
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
        <img src="../assets/sitelogo.png" alt="NexTradeAI" />
        <strong>Nex<span style="color:var(--aura-cyan)">Trade</span>AI</strong>
        <span>Mentor console</span>
      </div>
      <ul class="aura-console-nav">
        <li><a class="<?php echo admin_nav_active('index.php'); ?>" href="index.php"><i class="ti ti-layout-dashboard"></i> Overview</a></li>
        <li><a class="<?php echo admin_nav_active('key.php'); ?>" href="key.php"><i class="ti ti-key"></i> Access codes</a></li>
        <li><a class="<?php echo admin_nav_active('EA.php'); ?>" href="EA.php"><i class="ti ti-cpu"></i> Automations</a></li>
        <?php if(get_admin($_SESSION['username'],"super") and !get_admin($_SESSION['username'],"powerhost") and !get_admin($_SESSION['username'],"eamigrate") and !get_admin($_SESSION['username'],"eavault") ){?>
        <li><a class="<?php echo admin_nav_active('users.php'); ?>" href="users.php"><i class="ti ti-users"></i> Members</a></li>
        <li><a class="<?php echo admin_nav_active('blocked-ips.php'); ?>" href="blocked-ips.php"><i class="ti ti-shield-lock"></i> Access guard</a></li>
        <?php }?>
        <?php if(get_admin($_SESSION['username'],"super") and get_admin($_SESSION['username'],"powerhost")){?>
        <li><a class="<?php echo admin_nav_active('usersb.php'); ?>" href="usersb.php"><i class="ti ti-users"></i> Members</a></li>
        <?php }?>
        <?php if(get_admin($_SESSION['username'],"super") and get_admin($_SESSION['username'],"eamigrate")){?>
        <li><a class="<?php echo admin_nav_active('usersc.php'); ?>" href="usersc.php"><i class="ti ti-users"></i> Members</a></li>
        <?php }?>
        <?php if(get_admin($_SESSION['username'],"super") and get_admin($_SESSION['username'],"eavault")){?>
        <li><a class="<?php echo admin_nav_active('usersd.php'); ?>" href="usersd.php"><i class="ti ti-users"></i> Members</a></li>
        <?php }?>
        <li><a class="<?php echo admin_nav_active('alicense.php'); ?>" href="alicense.php"><i class="ti ti-refresh"></i> Restore access</a></li>
        <?php if(get_admin($_SESSION['username'],"super") && !get_admin($_SESSION['username'],"powerhost") && !get_admin($_SESSION['username'],"eamigrate") && !get_admin($_SESSION['username'],"eavault") ){?>
        <li><a class="<?php echo admin_nav_active('aemail.php'); ?>" href="aemail.php"><i class="ti ti-mail"></i> Restore inbox</a></li>
        <?php }?>
        <?php if(get_admin($_SESSION['username'],"super")&& get_admin($_SESSION['username'],"powerhost")){?>
        <li><a class="<?php echo admin_nav_active('aemailb.php'); ?>" href="aemailb.php"><i class="ti ti-mail"></i> Restore inbox</a></li>
        <?php }?>
        <?php if(get_admin($_SESSION['username'],"super")&& get_admin($_SESSION['username'],"eamigrate")){?>
        <li><a class="<?php echo admin_nav_active('aemailc.php'); ?>" href="aemailc.php"><i class="ti ti-mail"></i> Restore inbox</a></li>
        <?php }?>
        <?php if(get_admin($_SESSION['username'],"super")&& get_admin($_SESSION['username'],"eavault")){?>
        <li><a class="<?php echo admin_nav_active('aemaild.php'); ?>" href="aemaild.php"><i class="ti ti-mail"></i> Restore inbox</a></li>
        <?php }?>
        <li><a class="<?php echo admin_nav_active('copy_trades.php'); ?>" href="copy_trades.php"><i class="ti ti-arrows-shuffle"></i> Signal sync</a></li>
        <li><a class="<?php echo admin_nav_active('stats.php'); ?>" href="stats.php"><i class="ti ti-chart-dots"></i> Insights</a></li>
        <?php nextrade_admin_external_nav(); ?>
        <li><a href="../php-includes/logout.php"><i class="ti ti-logout"></i> Sign out</a></li>
      </ul>
    </aside>

    <div class="aura-console-backdrop" id="auraSidebarBackdrop"></div>

    <div class="aura-console-body">
      <header class="aura-console-topbar">
        <button type="button" class="aura-console-menu-btn" id="auraMenuBtn" aria-label="Open menu"><i class="ti ti-menu-2"></i></button>
        <div class="aura-console-topbar-title">Your workspace</div>
        <div class="aura-console-profile">
          <button type="button" class="aura-console-profile-btn" id="auraProfileBtn" aria-haspopup="true">
            <img src="<?php echo '../uploads/'.htmlspecialchars(get_admin($_SESSION['username'],'image'), ENT_QUOTES, 'UTF-8'); ?>" alt="Profile" />
            <i class="ti ti-chevron-down" style="font-size:0.85rem;color:var(--aura-muted);"></i>
          </button>
          <div class="aura-console-profile-menu" id="auraProfileMenu">
            <a href="/"><i class="ti ti-home"></i> Marketing site</a>
            <a href="/shop/"><i class="ti ti-shopping-cart"></i> Shop</a>
            <a href="<?php echo htmlspecialchars(NEXTRADE_APP_URL, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><i class="ti ti-device-mobile"></i> Open app</a>
            <a href="profile.php"><i class="ti ti-user"></i> Account</a>
            <a href="../php-includes/logout.php"><i class="ti ti-logout"></i> Sign out</a>
          </div>
        </div>
      </header>

      <main class="aura-console-main">
