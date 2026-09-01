<?php
$GLOBALS['admin_light_assets'] = true;
include("include/header.php");
$dash_owner   = (int) get_admin($_SESSION['username'], 'id');
$dash_lic_jj  = total_licences($dash_owner, 'jj');
$dash_lic_act = total_licences($dash_owner, 'Active');
$dash_ea      = total_EAs($dash_owner);
$dash_cap     = (int) get_admin($_SESSION['username'], 'total_keys');
$dash_usage   = $dash_cap > 0 ? (int) round(($dash_lic_jj / $dash_cap) * 100) : 0;
$dash_slots   = max(0, $dash_cap - $dash_lic_jj);
$dash_name    = htmlspecialchars(get_admin($_SESSION['username'], "displayname"), ENT_QUOTES, 'UTF-8');
?>

<div class="aura-console-page">
  <header class="aura-console-head">
    <div>
      <p class="aura-kicker">Overview</p>
      <h1>Hi, <?php echo $dash_name; ?></h1>
      <p>Everything you need to run cloud VPS access — simple, fast, and friendly.</p>
    </div>
    <span class="aura-chip"><i class="ti ti-circle-filled" style="font-size:0.45rem;"></i> Online</span>
  </header>

  <section class="aura-welcome">
    <div>
      <h2>Quick start</h2>
      <p>Mint an access code, check insights, or manage your automations.</p>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:0.55rem;">
      <a href="key.php" class="aura-btn aura-btn-primary"><i class="ti ti-key"></i> New code</a>
      <a href="stats.php" class="aura-btn aura-btn-ghost"><i class="ti ti-chart-dots"></i> Insights</a>
    </div>
  </section>

  <div class="aura-stat-grid">
    <a class="aura-stat" href="stats.php">
      <small>Codes issued</small>
      <strong><?php echo (int) $dash_lic_jj; ?></strong>
      <span>All time</span>
    </a>
    <a class="aura-stat" href="stats.php">
      <small>Live now</small>
      <strong><?php echo (int) $dash_lic_act; ?></strong>
      <span>Active members</span>
    </a>
    <a class="aura-stat" href="EA.php">
      <small>Automations</small>
      <strong><?php echo (int) $dash_ea; ?></strong>
      <span>On your account</span>
    </a>
    <a class="aura-stat" href="key.php">
      <small>Capacity</small>
      <strong><?php echo (int) $dash_cap; ?></strong>
      <span><?php echo (int) $dash_slots; ?> seats left</span>
    </a>
  </div>

  <div class="aura-split">
    <section class="aura-panel">
      <p class="aura-kicker" style="margin-bottom:0.5rem;">Shortcuts</p>
      <h2 style="margin:0 0 1rem;font-family:var(--aura-font-display);font-size:1.15rem;">Jump in</h2>
      <div class="aura-actions">
        <a href="key.php"><i class="ti ti-key"></i> Mint code</a>
        <a href="EA.php"><i class="ti ti-cpu"></i> Automations</a>
        <a href="stats.php"><i class="ti ti-chart-dots"></i> Insights</a>
        <a href="copy_trades.php"><i class="ti ti-arrows-shuffle"></i> Signal sync</a>
        <a href="alicense.php"><i class="ti ti-refresh"></i> Restore access</a>
        <a href="profile.php"><i class="ti ti-user"></i> Account</a>
      </div>
    </section>

    <section class="aura-panel">
      <p class="aura-kicker" style="margin-bottom:0.5rem;">Usage</p>
      <h2 style="margin:0 0 0.35rem;font-family:var(--aura-font-display);font-size:1.15rem;"><?php echo (int) $dash_usage; ?>% filled</h2>
      <p style="margin:0 0 0.85rem;color:var(--aura-muted);font-size:0.9rem;"><?php echo (int) $dash_lic_act; ?> live · <?php echo (int) $dash_slots; ?> open seats</p>
      <div class="aura-progress" aria-hidden="true"><i style="width:<?php echo (int) $dash_usage; ?>%"></i></div>
      <ul style="list-style:none;margin:0;padding:0;display:grid;gap:0.45rem;color:var(--aura-muted);font-size:0.9rem;">
        <li>✓ Console ready</li>
        <li>✓ Code minting enabled</li>
        <li>✓ <?php echo (int) $dash_ea; ?> automations linked</li>
      </ul>
    </section>
  </div>
</div>

<?php include("include/footer.php"); ?>
