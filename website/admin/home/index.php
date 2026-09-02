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

<div class="aura-console-page dash">
  <header class="dash-hero">
    <div class="dash-hero__copy">
      <p class="aura-kicker">Dashboard</p>
      <h1>Welcome back, <?php echo $dash_name; ?></h1>
      <p>Manage access codes, automations, and member activity from one place.</p>
    </div>
    <div class="dash-hero__actions">
      <a href="key.php" class="aura-btn aura-btn-primary"><i class="ti ti-key"></i> New key</a>
      <a href="stats.php" class="aura-btn aura-btn-ghost"><i class="ti ti-chart-dots"></i> Analytics</a>
    </div>
  </header>

  <div class="dash-metrics">
    <a class="dash-card" href="stats.php">
      <span class="dash-card__icon" aria-hidden="true"><i class="ti ti-key"></i></span>
      <span class="dash-card__label">Licence keys</span>
      <strong class="dash-card__value"><?php echo (int) $dash_lic_jj; ?></strong>
      <span class="dash-card__hint">All time</span>
    </a>
    <a class="dash-card" href="stats.php">
      <span class="dash-card__icon dash-card__icon--live" aria-hidden="true"><i class="ti ti-bolt"></i></span>
      <span class="dash-card__label">Live now</span>
      <strong class="dash-card__value"><?php echo (int) $dash_lic_act; ?></strong>
      <span class="dash-card__hint">Active members</span>
    </a>
    <a class="dash-card" href="EA.php">
      <span class="dash-card__icon dash-card__icon--ea" aria-hidden="true"><i class="ti ti-cpu"></i></span>
      <span class="dash-card__label">EAs</span>
      <strong class="dash-card__value"><?php echo (int) $dash_ea; ?></strong>
      <span class="dash-card__hint">On your account</span>
    </a>
    <a class="dash-card" href="key.php">
      <span class="dash-card__icon dash-card__icon--cap" aria-hidden="true"><i class="ti ti-stack-2"></i></span>
      <span class="dash-card__label">Capacity</span>
      <strong class="dash-card__value"><?php echo (int) $dash_cap; ?></strong>
      <span class="dash-card__hint"><?php echo (int) $dash_slots; ?> seats left</span>
    </a>
  </div>

  <div class="dash-grid">
    <section class="dash-panel">
      <div class="dash-panel__head">
        <h2>Quick actions</h2>
        <p>Jump straight to the tools you use most.</p>
      </div>
      <div class="dash-actions">
        <a href="key.php"><i class="ti ti-key"></i><span>Mint key</span></a>
        <a href="EA.php"><i class="ti ti-cpu"></i><span>EAs</span></a>
        <a href="stats.php"><i class="ti ti-chart-dots"></i><span>Analytics</span></a>
        <a href="copy_trades.php"><i class="ti ti-arrows-shuffle"></i><span>Copy trades</span></a>
        <a href="alicense.php"><i class="ti ti-refresh"></i><span>Restore licence</span></a>
        <a href="profile.php"><i class="ti ti-user"></i><span>Account</span></a>
      </div>
    </section>

    <section class="dash-panel dash-panel--usage">
      <div class="dash-panel__head">
        <h2>Seat usage</h2>
        <p><?php echo (int) $dash_lic_act; ?> live · <?php echo (int) $dash_slots; ?> open</p>
      </div>
      <div class="dash-usage">
        <div class="dash-usage__ring" style="--dash-pct: <?php echo (int) $dash_usage; ?>">
          <svg viewBox="0 0 120 120" aria-hidden="true">
            <circle class="dash-usage__track" cx="60" cy="60" r="52"></circle>
            <circle class="dash-usage__fill" cx="60" cy="60" r="52"></circle>
          </svg>
          <div class="dash-usage__center">
            <strong><?php echo (int) $dash_usage; ?>%</strong>
            <span>filled</span>
          </div>
        </div>
        <ul class="dash-status">
          <li><i class="ti ti-circle-check"></i> Console ready</li>
          <li><i class="ti ti-circle-check"></i> Code minting enabled</li>
          <li><i class="ti ti-circle-check"></i> <?php echo (int) $dash_ea; ?> EAs linked</li>
        </ul>
      </div>
    </section>
  </div>
</div>

<?php include("include/footer.php"); ?>
