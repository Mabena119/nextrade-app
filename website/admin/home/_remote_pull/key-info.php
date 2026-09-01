<?php
	include("include/header.php");
	require("../php-includes/connect.php");
	
?>
      <!-- partial -->
          <div class="row mb-4">
            <div class="col-12">
              <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                  <h2 class="dashboard-title">License Key Details</h2>
                  <p class="dashboard-subtitle">View and manage license key information</p>
                </div>
              </div>
            </div>
          </div>

<?php if(isset($_GET['key'])){ $key= mysqli_real_escape_string($con,$_GET['key']); $key_is_valid = true; ?>
<div class="row">
	<div class="col-lg-8 mb-4">
		<div class="card">
                <div class="card-body">
				<div class="d-flex align-items-center mb-4">
					<div class="flex-grow-1">
						<h4 class="card-title mb-2">License Key</h4>
						<div class="d-flex align-items-center gap-3 flex-wrap">
							<h1 class="text-primary mb-0" style="font-size: 2rem; font-weight: 700; letter-spacing: 2px; font-family: 'Courier New', monospace;"><?php echo $_GET['key'];?></h1>
							<div class="d-flex gap-2">
								<?php if(licence_details_key($key,'status')=="Active"){ ?>
									<span class="badge badge-success" style="font-size: 0.85rem; padding: 0.5rem 1rem;">Active</span>
								<?php } else if(licence_details_key($key,'status')=="Expired"){ ?>
									<span class="badge badge-danger" style="font-size: 0.85rem; padding: 0.5rem 1rem;">Expired</span>
								<?php } ?>
								
								<?php if(licence_details_key($key,'phone_secret_code')=="None"){ ?>
									<span class="badge badge-secondary" style="font-size: 0.85rem; padding: 0.5rem 1rem;">Not Yet Used</span>
								<?php } else { ?>
									<span class="badge badge-primary" style="font-size: 0.85rem; padding: 0.5rem 1rem;">Used</span>
								<?php } ?>
                              </div>
                            </div>
                          </div>
                        </div>
				
				<div class="license-info-section">
					<div class="info-item mb-3">
						<label class="text-muted mb-1" style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Expert Advisor</label>
						<h5 class="mb-0" style="color: #007bff; font-weight: 600;"><?php echo getea(licence_details_key($key,'ea'),get_admin($_SESSION['username'],'id'),'name');?></h5>
					</div>
					
					<div class="info-item mb-3">
						<label class="text-muted mb-1" style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">User</label>
						<?php if(licence_details_key($key,'status')=="Active"){ ?>
							<h5 class="mb-0 text-success" style="font-weight: 600;"><?php echo licence_details_key($key,'user');?></h5>
						<?php } else if(licence_details_key($key,'status')=="Expired"){ ?>
							<h5 class="mb-0 text-danger" style="font-weight: 600;"><?php echo licence_details_key($key,'user');?></h5>
						<?php } ?>
					</div>
					
					<p class="text-muted mb-4" style="line-height: 1.6;">This is a license key for <?php echo getea(licence_details_key($key,'ea'),get_admin($_SESSION['username'],'id'),'name');?>. Share this key with the user to grant them access to the trading signals.</p>
				</div>
			</div>
                            </div>  
                            </div>
                          
	<div class="col-lg-4 mb-4">
		<div class="card">
			<div class="card-body">
				<h4 class="card-title mb-4">Key Information</h4>
				
				<div class="info-details">
					<div class="detail-item mb-4 pb-3" style="border-bottom: 1px solid #333;">
						<label class="text-muted mb-1" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Plan Duration</label>
						<h5 class="mb-0" style="font-weight: 600;"><?php echo licence_details_key($key,'plan');?> Days</h5>
                        </div>
					
					<div class="detail-item mb-4 pb-3" style="border-bottom: 1px solid #333;">
						<label class="text-muted mb-1" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Created Date</label>
						<h5 class="mb-0" style="font-weight: 600;"><?php echo date('d M Y',strtotime(licence_details_key($key,'created')));?></h5>
						<small class="text-muted"><?php echo date('H:i:s',strtotime(licence_details_key($key,'created')));?></small>
                      </div>
                      
					<div class="detail-item mb-4">
						<label class="text-muted mb-1" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Expiry Date</label>
						<h5 class="mb-0" style="font-weight: 600;"><?php echo date('d M Y',strtotime(licence_details_key($key,'expires')));?></h5>
						<small class="text-muted"><?php echo date('H:i:s',strtotime(licence_details_key($key,'expires')));?></small>
					</div>
                    </div>
				
				<div class="action-buttons mt-4">
					<div class="mb-3" style="background:rgba(0,123,255,0.06);border:1px solid rgba(0,123,255,0.22);border-radius:10px;padding:1rem;">
						<label for="licenseEmailSend" class="text-muted mb-2" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;display:block;">Send key via email</label>
						<div class="d-flex flex-wrap" style="gap:8px;">
							<input type="email" id="licenseEmailSend" class="form-control" placeholder="client@example.com" style="flex:1;min-width:200px;font-family:inherit;">
							<button type="button" id="licenseEmailBtn" class="btn btn-primary" style="white-space:nowrap;">
								<i class="ti ti-mail mr-1"></i> Send email
							</button>
						</div>
						<small id="licenseEmailStatus" class="form-text text-muted mt-2"></small>
					</div>

					<?php if(licence_details_key($key,'status')=="Expired"){ ?>
						<form action="reactivate.php" method="get" class="mb-2">
							<input type="hidden" name="key" value="<?php echo $_GET['key']; ?>"/>
							<button type="submit" class="btn btn-success btn-block" style="padding: 0.75rem;">
								<i class="ti ti-refresh mr-2"></i>Reactivate Key
							</button>
						</form>
					<?php } ?>
					
					<?php if(licence_details_key($key,'status')=="Active"){ ?>
						<form action="deactivate.php" method="get">
							<input type="hidden" name="key" value="<?php echo $_GET['key']; ?>"/>
							<button type="submit" class="btn btn-danger btn-block" style="padding: 0.75rem;">
								<i class="ti ti-ban mr-2"></i>Deactivate Key
							</button>
						</form>
					<?php } ?>
                  </div>
                </div>
              </div>
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
			status.textContent = 'Enter a recipient email address.';
			status.style.color = '#fca5a5';
			return;
		}
		btn.disabled = true;
		status.textContent = 'Sending…';
		status.style.color = '#a1a1aa';
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
			if (data.ok) {
				status.textContent = data.message || 'Email sent.';
				status.style.color = '#86efac';
			} else {
				status.textContent = data.error || 'Failed to send email.';
				status.style.color = '#fca5a5';
			}
		}).catch(function () {
			btn.disabled = false;
			status.textContent = 'Network error. Try again.';
			status.style.color = '#fca5a5';
		});
	});
})();
</script>
<?php
	include("include/footer.php");
?>	