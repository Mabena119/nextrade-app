<?php
	include("include/header.php");
	require("../php-includes/connect.php");
?>
<div class="aura-console-page">
	<header class="aura-console-head">
		<div>
			<?php if(isset($_GET['ea'])){ ?>
				<a href="EA.php" class="aura-back"><i class="ti ti-arrow-left"></i> All automations</a>
				<p class="aura-kicker">EAs</p>
				<h1><?php echo htmlspecialchars(getea(mysqli_real_escape_string($con,$_GET['ea']),get_admin($_SESSION['username'],"id"),"name"), ENT_QUOTES, 'UTF-8'); ?></h1>
				<p>Secret code, download, and allowed symbols.</p>
			<?php } else { ?>
				<p class="aura-kicker">EAs</p>
				<h1>Your automations</h1>
				<p>Create automations, copy secret codes, and manage trading symbols.</p>
			<?php } ?>
		</div>
		<?php if(!isset($_GET['ea'])){ ?>
		<span class="aura-chip"><?php echo (int) total_EAs(get_admin($_SESSION['username'], "id")); ?> total</span>
		<?php } ?>
	</header>

<?php if(isset($_GET['ea'])){ $eaid = mysqli_real_escape_string($con,$_GET['ea']); ?>

	<section class="aura-panel" style="margin-bottom:1rem;">
		<div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;align-items:flex-start;margin-bottom:1rem;">
			<div>
				<?php $eaMartingale = (int) getea($eaid,get_admin($_SESSION["username"],"id"),"martingale"); ?>
				<?php if ($eaMartingale === 1) { ?>
					<span class="aura-badge aura-badge-warn">Copy trading</span>
				<?php } else { ?>
					<span class="aura-badge aura-badge-muted">Standard</span>
				<?php } ?>
				<p style="margin:0.65rem 0 0;color:var(--aura-muted);max-width:36rem;">Embed the secret code in your MT5 build. Never share it publicly.</p>
			</div>
			<form action="delete.php" method="post" onsubmit="return confirm('Delete this automation permanently?');">
				<input type="hidden" name="ea" value="<?php echo htmlspecialchars($_GET['ea'], ENT_QUOTES, 'UTF-8'); ?>" />
				<button type="submit" class="aura-btn aura-btn-ghost" style="color:#fda4af;border-color:rgba(251,113,133,.35);"><i class="ti ti-trash"></i> Delete</button>
			</form>
		</div>

		<p class="aura-kicker" style="margin-bottom:0.45rem;">Secret code</p>
		<div class="aura-copy" style="margin-bottom:1.25rem;">
			<code><?php echo htmlspecialchars(getea($eaid,get_admin($_SESSION['username'],"id"),"secret_code"), ENT_QUOTES, 'UTF-8');?></code>
			<button type="button" class="aura-copy-btn"><i class="ti ti-copy"></i> Copy</button>
		</div>

		<a href="../downloads/EATRADE.ex5" class="aura-btn aura-btn-ghost"><i class="ti ti-download"></i> Download MT5 publisher (EATRADE.ex5)</a>
		<p style="margin:0.65rem 0 0;color:var(--aura-muted);font-size:0.82rem;">Attach to a chart, paste your secret code, and allow WebRequest for <strong>https://www.nextradeai.io</strong>.</p>
	</section>

	<section class="aura-panel">
		<h2 style="margin:0 0 0.35rem;font-family:var(--aura-font-display);font-size:1.15rem;">Trading symbols</h2>
		<p style="margin:0 0 1rem;color:var(--aura-muted);font-size:0.92rem;">Pairs this automation is allowed to trade.</p>

		<?php if (!empty($_GET['symbol_added'])): ?>
		<div class="aura-alert" style="margin-bottom:1rem;background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.28);color:#86efac;">Symbol added.</div>
		<?php endif; ?>
		<?php if (!empty($_GET['symbol_error'])): ?>
		<div class="aura-alert aura-alert-danger" style="margin-bottom:1rem;">
			<?php
			switch ((string) $_GET['symbol_error']) {
				case 'duplicate': echo 'That symbol is already on this automation.'; break;
				case 'invalid': echo 'Enter a valid symbol name (e.g. EURUSD, XAUUSD).'; break;
				case 'forbidden': echo 'You do not have access to this automation.'; break;
				case 'db': echo 'Could not save the symbol. Try again.'; break;
				default: echo 'Could not update symbols.';
			}
			?>
		</div>
		<?php endif; ?>

		<?php
			$ea = $_GET['ea'];
			$res = json_decode(get_symbols($ea));
			$signals = (isset($res->data) && is_array($res->data)) ? $res->data : [];
			$url = "EA.php?ea=".$ea;
		?>

		<form action="add_symbol.php" method="post" style="display:flex;flex-wrap:wrap;gap:0.65rem;margin-bottom:1rem;">
			<input name="name" type="text" class="aura-input" style="flex:1;min-width:180px;" placeholder="e.g. EURUSD, XAUUSD" required>
			<input type="hidden" name="returnUrl" value="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" />
			<input type="hidden" name="ea" value="<?php echo htmlspecialchars($ea, ENT_QUOTES, 'UTF-8'); ?>"/>
			<button type="submit" class="aura-btn aura-btn-primary"><i class="ti ti-plus"></i> Add symbol</button>
		</form>

		<div class="aura-table-wrap">
			<table class="aura-table">
				<thead><tr><th>Symbol</th><th></th></tr></thead>
				<tbody>
				<?php if(empty($signals)): ?>
					<tr><td colspan="2" style="text-align:center;padding:2rem;color:var(--aura-muted);">No symbols yet — add one above.</td></tr>
				<?php else: foreach($signals as $signal): ?>
					<tr>
						<td><strong><?php echo htmlspecialchars($signal->name); ?></strong></td>
						<td>
							<form action="close_trade.php" method="post" style="display:inline;" onsubmit="return confirm('Remove this symbol?');">
								<input type="hidden" name="returnUrl" value="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" />
								<input type="hidden" name="ea" value="<?php echo htmlspecialchars($ea, ENT_QUOTES, 'UTF-8'); ?>"/>
								<input type="hidden" name="id" value="<?php echo (int) $signal->id; ?>"/>
								<button type="submit" name="close" class="aura-icon-btn" title="Remove"><i class="ti ti-trash"></i></button>
							</form>
						</td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
	</section>

<?php } else { ?>

	<div class="aura-split">
		<section class="aura-panel">
			<h2 style="margin:0 0 0.35rem;font-family:var(--aura-font-display);font-size:1.15rem;">New automation</h2>
			<p style="margin:0 0 1.1rem;color:var(--aura-muted);font-size:0.92rem;">Each automation gets its own secret code for customers.</p>

			<?php if (!empty($_GET['error'])): ?>
			<div class="aura-alert aura-alert-danger" style="margin-bottom:1rem;">
				<?php
				switch ((string) $_GET['error']) {
					case 'duplicate_name': echo 'That name is taken — try adding a version number.'; break;
					case 'invalid_name': echo 'Enter a valid name (1–50 characters).'; break;
					case 'invalid_owner': echo 'Session expired. Sign in again.'; break;
					case 'db_error': echo 'Could not save. Try again.'; break;
					default: echo 'Something went wrong.';
				}
				?>
			</div>
			<?php endif; ?>
			<?php if (isset($_GET['notice']) && $_GET['notice'] === 'existing_ea'): ?>
			<div class="aura-alert" style="margin-bottom:1rem;background:rgba(251,191,36,.12);border:1px solid rgba(251,191,36,.28);color:#fde68a;">You already have this name — opened the existing automation below.</div>
			<?php endif; ?>

			<form action="addea.php" method="post">
				<div class="aura-field">
					<label for="ea-name-input">Automation name</label>
					<input class="aura-input" name="ea" type="text" id="ea-name-input" placeholder="e.g. Aura Automation v2" required>
				</div>
				<div class="aura-field">
					<div class="aura-check" style="background:rgba(251,191,36,.08);border-color:rgba(251,191,36,.22);">
						<label>
							<input type="checkbox" name="martingale" value="1">
							<span><strong>Copy trading</strong><br><small style="color:var(--aura-muted);">Copy trade only — no automatic analysis. Clients follow your published signals.</small></span>
						</label>
					</div>
				</div>
				<div class="aura-field">
					<div class="aura-check">
						<label><input type="checkbox" required><span>I confirm I’m authorised to add this automation</span></label>
					</div>
				</div>
				<button type="submit" class="aura-btn aura-btn-primary aura-btn-block"><i class="ti ti-cpu"></i> Add automation</button>
			</form>
		</section>

		<section class="aura-panel">
			<h2 style="margin:0 0 0.35rem;font-family:var(--aura-font-display);font-size:1.15rem;">Your list</h2>
			<p style="margin:0 0 1rem;color:var(--aura-muted);font-size:0.92rem;">Open one to copy its secret code or manage symbols.</p>

			<div class="aura-table-wrap">
				<table class="aura-table">
					<thead>
						<tr><th>Name</th><th>Type</th><th>Users</th><th>Active</th><th></th></tr>
					</thead>
					<tbody>
					<?php
					$i = total_EAs(get_admin($_SESSION['username'], "id"));
					if($i == 0): ?>
						<tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--aura-muted);">No automations yet.</td></tr>
					<?php else: while ($i > 0):
						$eaId = (int) EA_details($i, 'id', get_admin($_SESSION['username'], "id"));
						$eaName = EA_details($i, 'name', get_admin($_SESSION['username'], "id"));
					?>
						<tr>
							<td><a href="EA.php?ea=<?php echo $eaId; ?>" style="font-weight:700;color:var(--aura-cyan);"><?php echo htmlspecialchars($eaName); ?></a></td>
							<td><?php if ((int) EA_details($i, 'martingale', get_admin($_SESSION['username'], "id")) === 1) { ?><span class="aura-badge aura-badge-warn">Copy trading</span><?php } else { ?><span class="aura-badge aura-badge-muted">Standard</span><?php } ?></td>
							<td><?php echo (int) users_from_EA($eaId); ?></td>
							<td><?php
								$users = get_all_users();
								$activeCount = 0;
								foreach ($users as $user) {
									if ($user["id"] == get_admin($_SESSION['username'], "id")) {
										$activeCount = total_subscriptions($user["id"], true);
										break;
									}
								}
								echo (int) $activeCount;
							?></td>
							<td><a class="aura-btn aura-btn-ghost" style="padding:0.45rem 0.7rem;font-size:0.8rem;" href="copy_trades.php?ea_id=<?php echo $eaId; ?>">Signals</a></td>
						</tr>
					<?php $i--; endwhile; endif; ?>
					</tbody>
				</table>
			</div>
		</section>
	</div>

<?php } ?>
</div>
<?php include("include/footer.php"); ?>
