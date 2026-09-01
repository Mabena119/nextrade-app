<?php
include('include/header.php');
$ownerId = (int) get_admin($_SESSION['username'], 'id');
$isTrusted = get_admin($_SESSION['username'], 'trusted') == true;
$totalKeys = (int) total_licences($ownerId, 'jj');
?>
<div class="admin-page-wrap">
	<div class="row mb-4">
		<div class="col-12">
			<div class="d-flex justify-content-between align-items-start flex-wrap" style="gap: 1rem;">
				<div class="admin-page-hero" style="margin-bottom: 0;">
					<h2 class="dashboard-title" style="margin-bottom: 0.35rem;">Key analytics</h2>
					<p class="dashboard-subtitle">View, reactivate, deactivate, and manage every licence key</p>
				</div>
				<span class="admin-stat-pill align-self-center">
					<i class="ti ti-key"></i>
					<?php echo $totalKeys; ?> total
				</span>
			</div>
		</div>
	</div>

	<div class="row">
		<div class="col-lg-12">
			<div class="card admin-panel">
				<div class="card-body">
					<div class="admin-panel-head">
						<div class="admin-panel-icon purple"><i class="ti ti-chart-line"></i></div>
						<div class="flex-grow-1">
							<h3 class="admin-panel-title">All licence keys</h3>
							<p class="admin-panel-desc">Swipe on mobile to see all columns. Actions stay pinned on the right.</p>
						</div>
						<a href="key.php" class="btn btn-sm admin-btn-primary" style="padding: 0.5rem 1rem !important; font-size: 0.8rem !important;">
							<i class="ti ti-plus"></i> New key
						</a>
					</div>

					<?php require __DIR__ . '/include/key-analytics-mobile.php'; ?>

					<div class="admin-table-wrap key-analytics-wrap">
						<div class="key-analytics-scroll" tabindex="0" aria-label="Licence keys table — swipe horizontally on mobile">
							<table class="table table-hover mb-0 key-analytics-table">
								<thead>
									<tr>
										<th>User</th>
										<th>Licence key</th>
										<th>Expert advisor</th>
										<th>Status</th>
										<th>Created</th>
										<th class="col-actions">Actions</th>
									</tr>
								</thead>
								<tbody>
								<?php
								$i = $totalKeys;
								if ($i === 0): ?>
									<tr>
										<td colspan="6" class="admin-empty-state">
											<i class="ti ti-key"></i>
											No licence keys yet. Create one from Create Keys.
										</td>
									</tr>
								<?php else: ?>
									<?php while ($i > 0):
										$keyId = (int) licence_details($i, 'id', $ownerId);
										$keyUser = licence_details($i, 'user', $ownerId);
										$keyCode = licence_details($i, 'k_ey', $ownerId);
										$keyEa = (int) licence_details($i, 'ea', $ownerId);
										$keyStatus = licence_details($i, 'status', $ownerId);
										$keyCreated = licence_details($i, 'created', $ownerId);
										$eaName = getea($keyEa, $ownerId, 'name');
										$keyCodeEsc = htmlspecialchars($keyCode, ENT_QUOTES, 'UTF-8');
										$keyUserEsc = htmlspecialchars($keyUser, ENT_QUOTES, 'UTF-8');
										$eaNameEsc = htmlspecialchars($eaName, ENT_QUOTES, 'UTF-8');
										$keyUrl = 'key-info.php?key=' . rawurlencode($keyCode);
									?>
									<tr>
										<td><strong><?php echo $keyUserEsc; ?></strong></td>
										<td>
											<a href="<?php echo htmlspecialchars($keyUrl, ENT_QUOTES, 'UTF-8'); ?>" style="font-family: 'Courier New', monospace; font-weight: 600; letter-spacing: 0.5px;">
												<?php echo $keyCodeEsc; ?>
											</a>
										</td>
										<td>
											<a href="EA.php?ea=<?php echo $keyEa; ?>">
												<i class="ti ti-robot mr-1"></i><?php echo $eaNameEsc; ?>
											</a>
										</td>
										<td>
											<?php if ($keyStatus === 'Active'): ?>
												<span class="badge badge-success">Active</span>
											<?php elseif ($keyStatus === 'Expired'): ?>
												<span class="badge badge-danger">Expired</span>
											<?php else: ?>
												<span class="badge badge-secondary"><?php echo htmlspecialchars($keyStatus, ENT_QUOTES, 'UTF-8'); ?></span>
											<?php endif; ?>
										</td>
										<td>
											<small class="text-muted"><?php echo date('d M Y', strtotime($keyCreated)); ?></small>
											<br>
											<small class="text-muted" style="font-size: 0.75rem;"><?php echo date('H:i', strtotime($keyCreated)); ?></small>
										</td>
										<td class="col-actions">
											<div class="key-analytics-actions">
												<a href="<?php echo htmlspecialchars($keyUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-light" title="View details">
													<i class="ti ti-eye"></i>
												</a>
												<?php if ($keyStatus === 'Expired'): ?>
													<form action="reactivate.php" method="get" class="d-inline">
														<input type="hidden" name="key" value="<?php echo $keyCodeEsc; ?>">
														<button type="submit" class="btn btn-sm btn-success" title="Reactivate key">
															<i class="ti ti-refresh"></i>
														</button>
													</form>
												<?php elseif ($keyStatus === 'Active'): ?>
													<form action="deactivate.php" method="get" class="d-inline" onsubmit="return confirm('Deactivate this licence key?');">
														<input type="hidden" name="key" value="<?php echo $keyCodeEsc; ?>">
														<button type="submit" class="btn btn-sm btn-warning" title="Deactivate key">
															<i class="ti ti-ban"></i>
														</button>
													</form>
												<?php endif; ?>
												<?php if ($isTrusted): ?>
													<form action="delete.php" method="post" class="d-inline" onsubmit="return confirm('Delete this licence key permanently?');">
														<input type="hidden" name="key" value="<?php echo $keyId; ?>">
														<button type="submit" class="btn btn-sm btn-danger" title="Delete key">
															<i class="ti ti-trash"></i>
														</button>
													</form>
												<?php endif; ?>
											</div>
										</td>
									</tr>
									<?php $i--; endwhile; ?>
								<?php endif; ?>
								</tbody>
							</table>
						</div>
						<div class="key-analytics-scroll-hint" aria-hidden="true">
							<i class="ti ti-arrows-horizontal"></i>
							<span>Swipe sideways for all columns and actions</span>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<?php include('include/footer.php'); ?>
