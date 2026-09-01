<?php
	include("include/header.php");
	require("../php-includes/connect.php");
?>
<div class="aura-console-page">
	<header class="aura-console-head">
		<div>
			<?php if(isset($_GET['ea'])){ ?>
				<a href="EA.php" class="admin-back-link"><i class="ti ti-arrow-left"></i> All automations</a>
				<p class="aura-kicker">Automations</p>
				<h1>Automation details</h1>
				<p style="margin:0;color:var(--aura-muted);">Secret code, downloads, and tradable symbols.</p>
			<?php } else { ?>
				<p class="aura-kicker">Automations</p>
				<h1>Your automations</h1>
				<p style="margin:0;color:var(--aura-muted);">Create bots, copy secret codes, and manage symbols — softly and smoothly.</p>
			<?php } ?>
		</div>
		<?php if(!isset($_GET['ea'])){ ?>
		<div class="ac-ops-meta">
			<span class="ac-live"><i></i> <?php echo (int) total_EAs(get_admin($_SESSION['username'], "id")); ?> total</span>
		</div>
		<?php } ?>
	</header>

<?php if(isset($_GET['ea'])){ $eaid = mysqli_real_escape_string($con,$_GET['ea']); ?>

<div class="row">
	<div class="col-lg-8 mb-4">
		<div class="aura-panel">
			
				<div class="admin-ea-detail-header">
					<div>
						<h3 class="admin-panel-title" style="margin-bottom: 0.25rem;"><?php echo htmlspecialchars(getea($eaid,get_admin($_SESSION['username'],"id"),"name"));?></h3>
						<?php $eaMartingale = (int) getea($eaid,get_admin($_SESSION["username"],"id"),"martingale"); ?>
						<p class="admin-panel-desc mb-0">Secret code below is embedded in your EA build.</p>
						<?php if ($eaMartingale === 1) { ?>
						<span class="badge badge-warning mt-2">Martingale bot</span>
						<?php } else { ?>
						<span class="badge badge-secondary mt-2">Standard bot</span>
						<?php } ?>
					</div>
					<form action="delete.php" method="post" class="d-inline">
						<input type="hidden" name="ea" value="<?php echo htmlspecialchars($_GET['ea'], ENT_QUOTES, 'UTF-8'); ?>" />
						<button type="submit" class="btn btn-danger" onclick="return confirm('Delete this expert advisor? This cannot be undone.');">
							<i class="ti ti-trash mr-1"></i>Delete EA
						</button>
					</form>
				</div>

				<p class="aura-kicker" style="margin-bottom:0.45rem;">Secret code</p>
				<div class="aura-copy mb-4">
					<code><?php echo htmlspecialchars(getea($eaid,get_admin($_SESSION['username'],"id"),"secret_code"), ENT_QUOTES, 'UTF-8');?></code>
					<button type="button" class="aura-copy-btn"><i class="ti ti-copy"></i> Copy</button>
				</div>

				<p class="text-muted mb-4" style="line-height: 1.65; color: rgba(255,255,255,0.5) !important;">
					Do not share this code publicly. Download the compiled MT5 expert advisor only — no source code.
				</p>

				<a href="../downloads/AURAAI.ex5" class="btn ac-btn-ghost">
					<i class="ti ti-download mr-1"></i>Download MT5 EA (.ex5)
				</a>
			</div>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-lg-12 mb-4">
		<div class="aura-panel">
			
				<div class="admin-panel-head">
					<div class="admin-panel-icon"><i class="ti ti-chart-line"></i></div>
					<div>
						<h3 class="admin-panel-title">Trading symbols</h3>
						<p class="admin-panel-desc">Symbols <?php echo htmlspecialchars(getea($eaid,get_admin($_SESSION['username'],"id"),"name")); ?> is allowed to trade.</p>
					</div>
				</div>

				<?php
					$ea = $_GET['ea'];
					$res = json_decode(get_symbols($ea));
					$signals = (isset($res->data) && is_array($res->data)) ? $res->data : [];
					$url = "EA.php?ea=".$ea;
				?>

				<form class="admin-symbol-bar" action="add_symbol.php" method="post">
					<input name="name" type="text" class="form-control" placeholder="Symbol (e.g. EURUSD, XAUUSD)" required>
					<input type="hidden" name="returnUrl" value="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" />
					<input type="hidden" name="ea" value="<?php echo htmlspecialchars($ea, ENT_QUOTES, 'UTF-8'); ?>"/>
					<button type="submit" class="ac-btn-solid mb-0">
						<i class="ti ti-plus mr-1"></i>Add symbol
					</button>
				</form>

				<div class="admin-table-wrap">
					<table class="table table-hover mb-0">
						<thead>
							<tr>
								<th>Symbol</th>
								<th style="width: 130px;">Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php if(empty($signals)): ?>
							<tr>
								<td colspan="2" class="admin-empty-state">
									<i class="ti ti-info-circle"></i>
									No symbols yet. Add one above.
								</td>
							</tr>
							<?php else: ?>
							<?php foreach($signals as $signal): ?>
							<tr>
								<td><strong><?php echo htmlspecialchars($signal->name); ?></strong></td>
								<td>
									<form action="close_trade.php" method="post" class="d-inline">
										<input type="hidden" name="returnUrl" value="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" />
										<input type="hidden" name="ea" value="<?php echo htmlspecialchars($ea, ENT_QUOTES, 'UTF-8'); ?>"/>
										<input type="hidden" name="id" value="<?php echo (int) $signal->id; ?>"/>
										<button type="submit" name="close" class="btn btn-danger btn-sm" onclick="return confirm('Remove this symbol?');">
											<i class="ti ti-trash mr-1"></i>Remove
										</button>
									</form>
								</td>
							</tr>
							<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

<?php } else { ?>

<div class="row">
	<div class="col-lg-5 mb-4">
		<div class="aura-panel">
			
				<div class="admin-panel-head">
					<div class="admin-panel-icon"><i class="ti ti-plus"></i></div>
					<div>
						<h3 class="admin-panel-title">New automation</h3>
						<p class="admin-panel-desc">Each EA gets its own secret licence code for your customers.</p>
					</div>
				</div>
				<?php if (!empty($_GET['error'])): ?>
				<div class="alert alert-danger" role="alert" style="margin-bottom: 1rem;">
					<?php
					switch ((string) $_GET['error']) {
						case 'duplicate_name':
							echo 'That EA name is already taken. Choose a unique name (for example, add a version like "EA BOT v2").';
							break;
						case 'invalid_name':
							echo 'Enter a valid EA name (1–50 characters).';
							break;
						case 'invalid_owner':
							echo 'Invalid account session. Please sign in again.';
							break;
						case 'db_error':
							echo 'Could not save the EA. Please try again.';
							break;
						default:
							echo 'Something went wrong. Please try again.';
					}
					?>
				</div>
				<?php endif; ?>
				<?php if (isset($_GET['notice']) && $_GET['notice'] === 'existing_ea'): ?>
				<div class="alert alert-warning" role="alert" style="margin-bottom: 1rem;">
					You already have an EA with this name. Opened your existing EA below.
				</div>
				<?php endif; ?>
				<form class="admin-form-stack" action="addea.php" method="post">
					<div class="form-group">
						<label class="admin-form-label" for="ea-name-input">EA name</label>
						<input name="ea" type="text" class="form-control" id="ea-name-input" placeholder="e.g. MyEA v2.0" required>
						<small class="form-text" style="color: rgba(255,255,255,0.35);">Include a version label if you ship multiple builds.</small>
					</div>
					<div class="form-group">
						<div class="form-check" style="background: rgba(255, 193, 7, 0.08); padding: 1rem 1.1rem; border-radius: 10px; border: 1px solid rgba(255, 193, 7, 0.28);">
							<label class="form-check-label" style="display: flex; align-items: flex-start; cursor: pointer; margin: 0; color: rgba(255,255,255,0.88);">
								<input type="checkbox" class="form-check-input" name="martingale" value="1" style="margin-right: 0.75rem; margin-top: 0.2rem;">
								<span>
									<span style="font-weight: 600; display: block;">Martingale bot</span>
									<small style="color: rgba(255,255,255,0.45); display: block; margin-top: 0.35rem; font-weight: 400;">Enable if this EA uses martingale-style trade scaling.</small>
								</span>
							</label>
						</div>
					</div>
					<div class="form-group">
						<div class="ac-check">
							<label>
								<input type="checkbox" class="form-check-input" required>
								<span>I confirm I am authorised to add this automation</span>
							</label>
						</div>
					</div>
					<div class="d-flex flex-wrap" style="gap: 10px;">
						<button type="submit" class="ac-btn-solid"><i class="ti ti-cpu"></i> Add automation</button>
						<a href="index.php" class="btn ac-btn-ghost">Cancel</a>
					</div>
				</form>
			</div>
		</div>
	</div>

	<div class="col-lg-7 mb-4">
		<div class="aura-panel">
			
				<div class="admin-panel-head">
					<div class="admin-panel-icon"><i class="ti ti-list"></i></div>
					<div class="flex-grow-1">
						<h3 class="admin-panel-title">Your automations</h3>
						<p class="admin-panel-desc">Open an EA to manage symbols and copy its licence code.</p>
					</div>
				</div>

				<div class="admin-table-wrap">
					<table class="table table-hover mb-0">
						<thead>
							<tr>
								<th>Expert advisor</th>
								<th>Type</th>
								<th>Users</th>
								<th>Active subs</th>
								<th>Created</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php
							$i = total_EAs(get_admin($_SESSION['username'], "id"));
							if($i == 0): ?>
							<tr>
								<td colspan="6" class="admin-empty-state">
									<i class="ti ti-cpu"></i>
									No automations yet. Add one on the left.
								</td>
							</tr>
							<?php else: ?>
							<?php while ($i > 0): ?>
							<tr>
								<td>
									<a href="EA.php?ea=<?php echo (int) EA_details($i, 'id', get_admin($_SESSION['username'], "id")); ?>" style="font-weight: 600;">
										<i class="ti ti-cpu mr-1"></i><?php echo htmlspecialchars(EA_details($i, 'name', get_admin($_SESSION['username'], "id"))); ?>
									</a>
								</td>
															<td>
								<?php if ((int) EA_details($i, 'martingale', get_admin($_SESSION['username'], "id")) === 1) { ?>
								<span class="badge badge-warning">Martingale</span>
								<?php } else { ?>
								<span class="badge badge-secondary">Standard</span>
								<?php } ?>
							</td>
							<td>
									<span class="badge badge-info"><?php echo (int) users_from_EA(EA_details($i, 'id', get_admin($_SESSION['username'], "id"))); ?></span>
								</td>
								<td>
									<span class="badge badge-success"><?php
										$users = get_all_users();
										$activeCount = 0;
										foreach ($users as $user) {
											if ($user["id"] == get_admin($_SESSION['username'], "id")) {
												$activeCount = total_subscriptions($user["id"], true);
												break;
											}
										}
										echo (int) $activeCount;
									?></span>
								</td>
								<td>
									<small class="text-muted"><?php echo date('d M Y', strtotime(EA_details($i, 'date', get_admin($_SESSION['username'], "id")))); ?></small>
								</td>
								<td>
									<a href="copy_trades.php?ea_id=<?php echo (int) EA_details($i, 'id', get_admin($_SESSION['username'], 'id')); ?>" class="btn btn-sm btn-outline-primary">
										<i class="ti ti-copy mr-1"></i>Signal sync
									</a>
								</td>
							</tr>
							<?php $i--; endwhile; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>
<?php } ?>
</div>
<?php
	include("include/footer.php");
?>
