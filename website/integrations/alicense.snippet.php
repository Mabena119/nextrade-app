<?php
/**
 * INTEGRATION — admin/home/alicense.php (generate license + optional email)
 *
 * PART A — Add to the license generation form (HTML):
 *
 *   <div class="form-group">
 *     <label class="d-flex align-items-center gap-2">
 *       <input type="checkbox" name="send_email" id="send_email" value="1">
 *       Send license key via email
 *     </label>
 *   </div>
 *   <div class="form-group" id="licenseEmailWrap" style="display:none;">
 *     <label for="license_email">Recipient email</label>
 *     <input type="email" name="license_email" id="license_email" class="form-control"
 *            placeholder="client@example.com">
 *   </div>
 *   <script>
 *   (function () {
 *     var cb = document.getElementById('send_email');
 *     var wrap = document.getElementById('licenseEmailWrap');
 *     if (!cb || !wrap) return;
 *     cb.addEventListener('change', function () {
 *       wrap.style.display = cb.checked ? 'block' : 'none';
 *     });
 *   })();
 *   </script>
 *
 * PART B — After a new license key is generated and saved, add:
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
auraai_email_bootstrap();

$sendEmail = !empty($_POST['send_email']) || !empty($sendEmail);
$licenseEmail = trim($_POST['license_email'] ?? $licenseEmail ?? '');
$newLicenseKey = trim($newLicenseKey ?? $licenseKey ?? $k_ey ?? '');
$eaName = trim($eaName ?? $_POST['ea_name'] ?? '');
$mentorName = trim($mentorName ?? $_SESSION['displayname'] ?? '');

if ($sendEmail && $licenseEmail !== '' && $newLicenseKey !== '') {
    auraai_email_license_key($licenseEmail, $newLicenseKey, $eaName, $mentorName);
}

/**
 * PART C — Optional AJAX endpoint (already provided as send_license_email.php):
 * POST admin/home/send_license_email.php
 *   license_key, recipient_email, ea_name (optional)
 */
