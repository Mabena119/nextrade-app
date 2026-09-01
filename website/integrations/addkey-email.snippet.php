<?php
/**
 * INTEGRATION — admin/home/addkey.php (License Key Details — send via email)
 *
 * Replace the existing "Send Key Via Email" JavaScript in addkey.php with this block.
 * Ensure the page outputs the license key and EA name into JS variables (see below).
 *
 * Required PHP variables on the page before this script:
 *   $k_ey or $licenseKey — the license key string
 *   $ea_name or $eaName — expert advisor name (optional)
 */

/**
 * PART A — In addkey.php HTML, ensure the email input and status element exist:
 *
 *   <input type="email" id="license_email" class="form-control" value="...">
 *   <button type="button" id="sendLicenseEmailBtn" class="btn btn-primary">Send email</button>
 *   <small id="licenseEmailStatus" class="text-muted"></small>
 *
 * PART B — Add before </body> (or in the existing script section):
 */
?>
<script>
(function () {
  var btn = document.getElementById('sendLicenseEmailBtn');
  var input = document.getElementById('license_email');
  var status = document.getElementById('licenseEmailStatus');
  if (!btn || !input) return;

  var licenseKey = <?php echo json_encode($k_ey ?? $licenseKey ?? '', JSON_UNESCAPED_UNICODE); ?>;
  var eaName = <?php echo json_encode($ea_name ?? $eaName ?? '', JSON_UNESCAPED_UNICODE); ?>;

  btn.addEventListener('click', function () {
    var email = (input.value || '').trim();
    if (!email) {
      if (status) status.textContent = 'Enter a recipient email.';
      return;
    }
    if (!licenseKey) {
      if (status) status.textContent = 'License key missing.';
      return;
    }

    btn.disabled = true;
    if (status) status.textContent = 'Sending…';

    fetch('send_license_email.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({
        license_key: licenseKey,
        recipient_email: email,
        ea_name: eaName
      })
    })
      .then(function (res) {
        return res.json().catch(function () { return { ok: false, error: 'Invalid server response' }; });
      })
      .then(function (data) {
        if (data && data.ok) {
          if (status) status.textContent = data.message || 'License key sent.';
          if (status) status.style.color = '#86efac';
        } else {
          if (status) status.textContent = (data && data.error) ? data.error : 'Failed to send email';
          if (status) status.style.color = '#fca5a5';
        }
      })
      .catch(function () {
        if (status) status.textContent = 'Network error. Try again.';
        if (status) status.style.color = '#fca5a5';
      })
      .finally(function () {
        btn.disabled = false;
      });
  });
})();
</script>
<?php
/**
 * If addkey.php still calls aemail.php for license emails, change it to send_license_email.php.
 */
