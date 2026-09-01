<?php
	include("include/header.php");

?>
      <!-- partial -->
          <div class="row mb-4">
            <div class="col-12">
              <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                  <h2 class="dashboard-title">Reactivate License Key</h2>
                  <p class="dashboard-subtitle">Reactivate a license key to restore user access</p>
                </div>
              </div>
            </div>
          </div>

<div class="row">
	<div class="col-lg-8 col-xl-6">
	  <div class="card">
		<div class="card-body">
				<div class="d-flex align-items-center mb-4">
					<div class="icon-badge mr-3" style="width: 56px; height: 56px; border-radius: 12px; background: rgba(0, 123, 255, 0.2); display: flex; align-items: center; justify-content: center;">
						<i class="ti ti-key" style="font-size: 1.75rem; color: #007bff;"></i>
					</div>
					<div>
						<h4 class="card-title mb-1">Reactivate License Key</h4>
						<p class="text-muted mb-0">Enter the license key you want to reactivate</p>
					</div>
				</div>
				
				<form action="reactivate_key.php" method="get">
					<div class="form-group">
						<label for="keyInput">License Key</label>
						<input name="key" type="text" class="form-control" id="keyInput" placeholder="XXXX-XXXX-XXXX-XXXX" required style="font-family: 'Courier New', monospace; letter-spacing: 1px;">
						<small class="form-text text-muted">Enter the full license key in the format shown above</small>
					</div>
					
					<div class="form-group">
						<div class="form-check" style="background: rgba(0, 123, 255, 0.05); padding: 1rem; border-radius: 8px; border: 1px solid rgba(0, 123, 255, 0.2);">
							<label class="form-check-label" style="display: flex; align-items: center; cursor: pointer; margin: 0;">
								<input type="checkbox" class="form-check-input" required style="margin-right: 0.75rem; margin-top: 0;">
								<span style="font-weight: 500;">I confirm that I have permission to reactivate this license key</span>
			  </label>
			</div>
					</div>
					
					<div class="d-flex gap-2">
						<button type="submit" class="btn btn-primary">
							<i class="ti ti-refresh mr-2"></i>Reactivate Key
						</button>
						<a href="index.php" class="btn btn-light">Cancel</a>
					</div>
		  </form>
		</div>
	  </div>
	</div>
</div>

<?php
	include("include/footer.php");
?>