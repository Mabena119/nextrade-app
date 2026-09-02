<?php include("include/header.php"); ?>

<div class="aura-console-page" style="max-width:560px;">
  <header class="aura-console-head">
    <div>
      <p class="aura-kicker">Licence keys</p>
      <h1>Mint a new code</h1>
      <p>Pick the customer, automation, and plan — we’ll handle the rest.</p>
    </div>
  </header>

  <div class="aura-panel">
    <?php if(isset($_GET['error'])): ?>
    <div class="aura-alert aura-alert-danger" style="margin-bottom:1rem;">
      <?php
      switch($_GET['error']) {
        case 'invalid_user': echo 'Please sign in again.'; break;
        case 'db_error': echo 'Could not mint the code. Try again.'; break;
        case 'max_keys_reached': echo 'You’ve reached your code limit.'; break;
        case 'missing_fields': echo 'Please fill in every field.'; break;
        default: echo 'Something went wrong. Try again.';
      }
      ?>
    </div>
    <?php endif; ?>

    <form action="addkey.php" method="post">
      <div class="aura-field">
        <label for="inlineFormInputName2">Customer name</label>
        <input class="aura-input" name="name" type="text" id="inlineFormInputName2" placeholder="Name on the licence" required>
      </div>

      <div class="aura-field">
        <label for="ea">Automation</label>
        <select class="aura-input" name="ea" id="ea" required>
          <option disabled selected value="">Choose automation</option>
          <?php $i = total_EAs(get_admin($_SESSION['username'],"id")); while($i > 0){ ?>
          <option value="<?php echo EA_details($i,'id',get_admin($_SESSION['username'],"id")); ?>"><?php echo htmlspecialchars(EA_details($i,'name',get_admin($_SESSION['username'],"id"))); ?></option>
          <?php $i--;} ?>
        </select>
      </div>

      <div class="aura-field">
        <label for="plan">Plan length</label>
        <select class="aura-input" name="plan" id="plan" required>
          <option disabled selected value="">Choose plan</option>
          <option value="3">3 days</option>
          <option value="5">5 days</option>
          <option value="30">30 days</option>
          <option value="90">3 months</option>
          <option value="180">6 months</option>
          <option value="365">1 year</option>
          <option value="3652">Lifetime</option>
        </select>
      </div>

      <div class="aura-field">
        <div class="aura-check">
          <label>
            <input type="checkbox" name="send_email" id="send_email" value="1">
            <span>Email the code to the customer after minting</span>
          </label>
        </div>
      </div>

      <div class="aura-field" id="licenseEmailWrap" style="display:none;">
        <label for="license_email">Customer email</label>
        <input class="aura-input" name="license_email" type="email" id="license_email" placeholder="client@example.com">
      </div>

      <div class="aura-field">
        <div class="aura-check">
          <label>
            <input type="checkbox" required>
            <span>I confirm I’m allowed to mint this access code</span>
          </label>
        </div>
      </div>

      <button class="aura-btn aura-btn-primary aura-btn-block" type="submit"><i class="ti ti-sparkles"></i> Mint access code</button>
      <p style="text-align:center;margin:1rem 0 0;"><a href="stats.php" style="color:var(--aura-cyan);font-weight:600;">View all codes</a></p>
    </form>
  </div>
</div>

<script>
(function () {
  var cb = document.getElementById('send_email');
  var wrap = document.getElementById('licenseEmailWrap');
  var emailInput = document.getElementById('license_email');
  if (!cb || !wrap) return;
  cb.addEventListener('change', function () {
    wrap.style.display = cb.checked ? 'block' : 'none';
    if (emailInput) emailInput.required = cb.checked;
  });
})();
</script>

<?php include("include/footer.php"); ?>
