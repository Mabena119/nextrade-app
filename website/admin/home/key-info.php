<?php
	include("include/header.php");
	require("../php-includes/connect.php");
?>

<?php if(isset($_GET['key'])){ $key= mysqli_real_escape_string($con,$_GET['key']); ?>
<div class="aura-console-page">
  <a class="aura-back" href="stats.php"><i class="ti ti-arrow-left"></i> Back to insights</a>
  <header class="aura-console-head">
    <div>
      <p class="aura-kicker">Access code</p>
      <h1>Code details</h1>
      <p>Share or copy the code below — one tap.</p>
    </div>
  </header>

  <div class="aura-split" style="grid-template-columns:1.2fr 0.8fr;">
    <section class="aura-panel">
      <div class="aura-copy" style="margin-bottom:1.25rem;">
        <code id="licenseKeyValue"><?php echo htmlspecialchars($_GET['key'], ENT_QUOTES, 'UTF-8'); ?></code>
        <button type="button" class="aura-copy-btn"><i class="ti ti-copy"></i> Copy code</button>
      </div>

      <div style="display:flex;flex-wrap:wrap;gap:0.45rem;margin-bottom:1.25rem;">
        <?php if(licence_details_key($key,'status')=="Active"){ ?>
          <span class="aura-badge aura-badge-ok">Active</span>
        <?php } else if(licence_details_key($key,'status')=="Expired"){ ?>
          <span class="aura-badge aura-badge-bad">Expired</span>
        <?php } ?>
        <?php if(licence_details_key($key,'phone_secret_code')=="None"){ ?>
          <span class="aura-badge aura-badge-muted">Not used yet</span>
        <?php } else { ?>
          <span class="aura-badge aura-badge-ok">In use</span>
        <?php } ?>
      </div>

      <div class="aura-field">
        <label class="ac-meta-label">Automation</label>
        <p style="margin:0;font-weight:700;color:var(--aura-cyan);"><?php echo htmlspecialchars(getea(licence_details_key($key,'ea'),get_admin($_SESSION['username'],'id'),'name'), ENT_QUOTES, 'UTF-8');?></p>
      </div>
      <div class="aura-field">
        <label class="ac-meta-label">Customer</label>
        <p style="margin:0;font-weight:700;"><?php echo htmlspecialchars(licence_details_key($key,'user'), ENT_QUOTES, 'UTF-8');?></p>
      </div>
      <p style="margin:0;color:var(--aura-muted);line-height:1.55;">Send this code to your customer so they can activate in the app.</p>
    </section>

    <section class="aura-panel">
      <p class="aura-kicker" style="margin-bottom:0.45rem;">Plan</p>
      <h2 style="margin:0 0 1rem;font-family:var(--aura-font-display);font-size:1.2rem;"><?php echo htmlspecialchars((string) licence_details_key($key,'plan'), ENT_QUOTES, 'UTF-8'); ?> days</h2>

      <p style="margin:0 0 0.35rem;color:var(--aura-muted);font-size:0.82rem;">Created</p>
      <p style="margin:0 0 1rem;font-weight:600;"><?php echo date('d M Y, H:i',strtotime(licence_details_key($key,'created')));?></p>

      <p style="margin:0 0 0.35rem;color:var(--aura-muted);font-size:0.82rem;">Expires</p>
      <p style="margin:0 0 1.25rem;font-weight:600;"><?php echo date('d M Y, H:i',strtotime(licence_details_key($key,'expires')));?></p>

      <div class="aura-field">
        <label for="licenseEmailSend">Email this code</label>
        <input type="email" id="licenseEmailSend" class="aura-input" placeholder="client@example.com">
        <button type="button" id="licenseEmailBtn" class="aura-btn aura-btn-primary aura-btn-block" style="margin-top:0.65rem;"><i class="ti ti-mail"></i> Send email</button>
        <small id="licenseEmailStatus" style="display:block;margin-top:0.5rem;color:var(--aura-muted);"></small>
      </div>

      <?php if(licence_details_key($key,'status')=="Expired"){ ?>
        <form action="reactivate.php" method="get" style="margin-top:0.75rem;">
          <input type="hidden" name="key" value="<?php echo htmlspecialchars($_GET['key'], ENT_QUOTES, 'UTF-8'); ?>"/>
          <button type="submit" class="aura-btn aura-btn-primary aura-btn-block"><i class="ti ti-refresh"></i> Restore access</button>
        </form>
      <?php } ?>
      <?php if(licence_details_key($key,'status')=="Active"){ ?>
        <form action="deactivate.php" method="get" style="margin-top:0.75rem;">
          <input type="hidden" name="key" value="<?php echo htmlspecialchars($_GET['key'], ENT_QUOTES, 'UTF-8'); ?>"/>
          <button type="submit" class="aura-btn aura-btn-ghost aura-btn-block"><i class="ti ti-player-pause"></i> Pause access</button>
        </form>
      <?php } ?>
    </section>
  </div>
</div>
<?php }else{ header("location:index.php"); exit();} ?>

<script>
(function () {
  var btn = document.getElementById('licenseEmailBtn');
  var input = document.getElementById('licenseEmailSend');
  var status = document.getElementById('licenseEmailStatus');
  if (!btn || !input || !status) return;
  btn.addEventListener('click', function () {
    var email = (input.value || '').trim();
    if (!email) {
      status.textContent = 'Enter an email address first.';
      status.style.color = '#fca5a5';
      return;
    }
    btn.disabled = true;
    status.textContent = 'Sending…';
    status.style.color = 'var(--aura-muted)';
    fetch('send_license_email.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({
        license_key: <?php echo json_encode($_GET['key'] ?? ''); ?>,
        recipient_email: email,
        ea_name: <?php echo json_encode(getea(licence_details_key($key,'ea'),get_admin($_SESSION['username'],'id'),'name')); ?>
      })
    }).then(function (r) { return r.json(); }).then(function (data) {
      btn.disabled = false;
      status.textContent = data.ok ? (data.message || 'Email sent.') : (data.error || 'Could not send.');
      status.style.color = data.ok ? '#86efac' : '#fca5a5';
    }).catch(function () {
      btn.disabled = false;
      status.textContent = 'Network error — try again.';
      status.style.color = '#fca5a5';
    });
  });
})();
</script>
<?php include("include/footer.php"); ?>
