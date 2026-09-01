<?php
	include("include/header.php");
?>

<div class="admin-page-wrap admin-page-wrap--center">
	<div class="row admin-page-hero">
		<div class="col-12">
			<h2 class="dashboard-title" style="margin-bottom: 0.35rem;">Create licence key</h2>
			<p class="dashboard-subtitle">Enter the user and expert advisor, then choose a plan duration</p>
		</div>
	</div>

	<div class="row justify-content-center" id="new">
		<div class="col-12 col-lg-10 col-xl-8 grid-margin stretch-card">
			<div class="card admin-panel">
				<div class="card-body">
					<div class="admin-panel-head">
						<div class="admin-panel-icon"><i class="ti ti-key"></i></div>
						<div>
							<h3 class="admin-panel-title">Generate key</h3>
							<p class="admin-panel-desc">The key is tied to your EA and the customer name you enter below.</p>
						</div>
					</div>

					<?php if(isset($_GET['error'])): ?>
					<div class="alert admin-alert-modern alert-dismissible fade show" role="alert">
						<?php
						switch($_GET['error']) {
							case 'invalid_user':
								echo 'Invalid user session. Please log in again.';
								break;
							case 'db_error':
								echo 'Error while generating key. Please try again.';
								break;
							case 'max_keys_reached':
								echo 'You have reached the maximum number of keys allowed.';
								break;
							case 'missing_fields':
								echo 'Please fill in all required fields.';
								break;
							default:
								echo 'An error occurred. Please try again.';
						}
						?>
						<button type="button" class="close" data-dismiss="alert" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<?php endif; ?>

					<form class="admin-form-stack" action="addkey.php" method="post">
						<div class="form-group">
							<label class="admin-form-label" for="inlineFormInputName2">Customer / user name</label>
							<input name="name" type="text" class="form-control" id="inlineFormInputName2" placeholder="Name shown on the licence" required>
						</div>

						<div class="form-group">
							<label class="admin-form-label" for="ea">Expert advisor</label>
							<select name="ea" class="form-control" id="ea" required>
								<option disabled selected value="">Select an EA</option>
								<?php $i = total_EAs(get_admin($_SESSION['username'],"id")); while($i > 0){ ?>
								<option value="<?php echo EA_details($i,'id',get_admin($_SESSION['username'],"id")); ?>"><?php echo htmlspecialchars(EA_details($i,'name',get_admin($_SESSION['username'],"id"))); ?></option>
								<?php $i--;} ?>
							</select>
						</div>

						<div class="form-group">
							<label class="admin-form-label" for="plan">Plan duration</label>
							<select name="plan" class="form-control" id="plan" required>
								<option disabled selected value="">Select a plan</option>
								<option value="3">3 days</option>
								<option value="5">5 days</option>
								<option value="30">30 days</option>
								<option value="90">3 months</option>
								<option value="180">6 months</option>
								<option value="365">1 year</option>
								<option value="3652">Lifetime</option>
							</select>
						</div>

						<div class="form-group">
							<div class="form-check" style="background: rgba(0, 123, 255, 0.06); padding: 1rem 1.1rem; border-radius: 10px; border: 1px solid rgba(0, 123, 255, 0.22);">
								<label class="form-check-label" style="display: flex; align-items: center; cursor: pointer; margin: 0; color: rgba(255,255,255,0.88);">
									<input type="checkbox" class="form-check-input" name="send_email" id="send_email" value="1" style="margin-right: 0.75rem; margin-top: 0;">
									<span style="font-weight: 500;">Send license key via email after generation</span>
								</label>
							</div>
						</div>

						<div class="form-group" id="licenseEmailWrap" style="display:none;">
							<label class="admin-form-label" for="license_email">Recipient email</label>
							<input name="license_email" type="email" class="form-control" id="license_email" placeholder="client@example.com">
							<small class="form-text text-muted">The generated key will be emailed to this address.</small>
						</div>

						<div class="form-group">
							<div class="form-check" style="background: rgba(0, 123, 255, 0.06); padding: 1rem 1.1rem; border-radius: 10px; border: 1px solid rgba(0, 123, 255, 0.22);">
								<label class="form-check-label" style="display: flex; align-items: center; cursor: pointer; margin: 0; color: rgba(255,255,255,0.88);">
									<input type="checkbox" class="form-check-input" required style="margin-right: 0.75rem; margin-top: 0;">
									<span style="font-weight: 500;">I confirm that I have permission to generate this licence key</span>
								</label>
							</div>
						</div>

						<div class="d-flex flex-wrap align-items-center" style="gap: 12px;">
							<button type="submit" class="btn btn-primary admin-btn-primary"><i class="ti ti-sparkles"></i> Generate key</button>
							<a href="stats.php" class="btn admin-btn-primary admin-btn-ghost">View all keys</a>
						</div>
					</form>
				</div>
			</div>
		</div>
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

<?php
	include("include/footer.php");
?>
