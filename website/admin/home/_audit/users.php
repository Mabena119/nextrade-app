<?php
    require __DIR__ . '/include/require_super.php';
    include("include/header.php");

    $trusted = (get_admin($_SESSION['username'], 'trusted') == true);
?>

<style>
/* Members — modern layout (scoped) */
.mu-page { animation: acSoftIn .55s cubic-bezier(.22,1,.36,1) both;
 max-width: 1440px; margin: 0 auto; padding: 0 2px 2.5rem; }
.mu-hero { transition: box-shadow .4s ease;

    position: relative;
    border-radius: 20px;
    overflow: hidden;
    margin-bottom: 1.75rem;
    padding: 2rem 2rem 2rem 2.25rem;
    background: linear-gradient(145deg, #12161c 0%, #1a222d 42%, #0e1116 100%);
    border: 1px solid rgba(255,255,255,0.07);
    box-shadow: 0 20px 50px rgba(0,0,0,0.4);
}
.mu-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse 80% 60% at 90% 20%, rgba(0, 229, 255, 0.14), transparent 55%),
                radial-gradient(ellipse 60% 50% at 10% 90%, rgba(14, 165, 233, 0.1), transparent 50%);
    pointer-events: none;
}
.mu-hero-inner { position: relative; z-index: 1; }
.mu-kicker {
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: rgba(129, 140, 248, 0.95);
    margin-bottom: 0.5rem;
}
.mu-title {
    font-size: 1.65rem;
    font-weight: 700;
    letter-spacing: -0.03em;
    color: #fff;
    margin: 0 0 0.5rem;
    line-height: 1.2;
}
.mu-lead {
    margin: 0;
    max-width: 42rem;
    font-size: 0.95rem;
    line-height: 1.55;
    color: rgba(255,255,255,0.48);
}

.mu-shell { transition: transform .35s cubic-bezier(.22,1,.36,1), box-shadow .35s ease;

    background: rgba(22, 25, 32, 0.85);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 18px;
    box-shadow: 0 16px 48px rgba(0,0,0,0.35);
    overflow: hidden;
}
.mu-shell-hd {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1.25rem;
    padding: 1.5rem 1.5rem 1.25rem;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    background: rgba(0,0,0,0.15);
}
.mu-shell-hd-text h3 {
    margin: 0 0 0.35rem;
    font-size: 1.05rem;
    font-weight: 600;
    color: rgba(255,255,255,0.95);
}
.mu-shell-hd-text p {
    margin: 0;
    font-size: 0.82rem;
    color: rgba(255,255,255,0.4);
    line-height: 1.45;
}

.mu-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.5rem;
    background: rgba(0,0,0,0.2);
    border-bottom: 1px solid rgba(255,255,255,0.05);
}
.mu-search {
    position: relative;
    flex: 1;
    min-width: 260px;
    max-width: 420px;
}
.mu-search i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(255,255,255,0.28);
    font-size: 1.05rem;
    pointer-events: none;
}
.mu-search input[type="search"] {
    width: 100%;
    padding: 0.72rem 1rem 0.72rem 2.65rem;
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.1);
    background: rgba(0,0,0,0.35);
    color: #fff;
    font-size: 0.9rem;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.mu-search input[type="search"]::placeholder { color: rgba(255,255,255,0.32); }
.mu-search input[type="search"]:focus {
    outline: none;
    border-color: rgba(129, 140, 248, 0.55);
    box-shadow: 0 0 0 3px rgba(129, 140, 241, 0.12);
}
.mu-count {
    margin: 0;
    font-size: 0.8rem;
    font-weight: 500;
    color: rgba(255,255,255,0.42);
    white-space: nowrap;
}

.mu-scroll {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.mu-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.82rem;
}
.mu-table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    padding: 0.85rem 1rem;
    text-align: left;
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: rgba(255,255,255,0.38);
    background: rgba(10, 12, 16, 0.97);
    border-bottom: 1px solid rgba(255,255,255,0.07);
    white-space: nowrap;
}
.mu-table tbody td {
    padding: 0.75rem 1rem;
    vertical-align: middle;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    color: rgba(255,255,255,0.88);
}
.mu-table tbody tr:hover td {
    background: rgba(255,255,255,0.02);
}
.mu-table tbody tr:last-child td { border-bottom: none; }

.mu-name-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: #67e8f9 !important;
    text-decoration: none !important;
}
.mu-name-link:hover { color: #c7d2fe !important; }
.mu-id-tag {
    font-size: 0.65rem;
    font-weight: 600;
    color: rgba(255,255,255,0.35);
    background: rgba(255,255,255,0.06);
    padding: 2px 7px;
    border-radius: 6px;
    letter-spacing: 0.02em;
}
.mu-email { color: rgba(255,255,255,0.5); font-size: 0.78rem; }
.mu-cell-dim { color: rgba(255,255,255,0.45); font-size: 0.78rem; max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mu-table a:not(.mu-name-link):not(.mu-pill) { color: #7dd3fc !important; text-decoration: none !important; }
.mu-table a:not(.mu-name-link):not(.mu-pill):hover { text-decoration: underline !important; }
.mu-table a.mu-pill {
    color: rgba(255,255,255,0.72) !important;
    text-decoration: none !important;
}
.mu-table a.mu-pill:hover {
    background: rgba(255,255,255,0.12) !important;
    color: #fff !important;
}

.mu-input-num {
    width: 4.25rem;
    padding: 0.35rem 0.45rem;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.12);
    background: rgba(0,0,0,0.35);
    color: #fff;
    font-size: 0.8rem;
    font-weight: 600;
    text-align: center;
}
.mu-input-num:focus {
    outline: none;
    border-color: rgba(129, 140, 241, 0.5);
}
.mu-select {
    padding: 0.38rem 0.55rem;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.12);
    background: rgba(0,0,0,0.4);
    color: #fff;
    font-size: 0.78rem;
    cursor: pointer;
    max-width: 7.5rem;
}
.mu-select:focus {
    outline: none;
    border-color: rgba(129, 140, 241, 0.5);
}

.mu-pill {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.55rem;
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.mu-pill--active { background: rgba(34, 197, 94, 0.15); color: #86efac; border: 1px solid rgba(34, 197, 94, 0.35); }
.mu-pill--pending { background: rgba(251, 191, 36, 0.12); color: #fcd34d; border: 1px solid rgba(251, 191, 36, 0.35); }
.mu-pill--blocked { background: rgba(248, 113, 113, 0.12); color: #fca5a5; border: 1px solid rgba(248, 113, 113, 0.35); }
.mu-pill--super { background: rgba(167, 139, 250, 0.12); color: #d8b4fe; border: 1px solid rgba(167, 139, 250, 0.35); }
.mu-pill--plain { background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.45); border: 1px solid rgba(255,255,255,0.08); text-transform: none; font-weight: 600; }

.mu-stat { font-weight: 700; font-variant-numeric: tabular-nums; color: rgba(255,255,255,0.9); }

.mu-btn-save {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.4rem 0.85rem;
    border-radius: 8px;
    border: none;
    font-size: 0.72rem;
    font-weight: 600;
    background: linear-gradient(135deg, #00e5ff 0%, #00b8d4 100%);
    color: #fff !important;
    box-shadow: 0 2px 10px rgba(79, 70, 229, 0.35);
    cursor: pointer;
    transition: transform 0.15s, box-shadow 0.15s;
}
.mu-btn-save:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.45);
    color: #fff !important;
}
.mu-float-actions {
    position: fixed;
    right: 1.5rem;
    bottom: 1.5rem;
    z-index: 100;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.65rem;
}
.mu-float-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.75rem 1.35rem;
    border-radius: 999px;
    border: none;
    font-size: 0.85rem;
    font-weight: 600;
    color: #fff !important;
    cursor: pointer;
    transition: transform 0.15s, box-shadow 0.15s, opacity 0.15s;
}
.mu-float-btn i {
    font-size: 1rem;
    line-height: 1;
}
.mu-float-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    color: #fff !important;
}
.mu-float-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}
.mu-float-btn--save {
    background: linear-gradient(135deg, #00e5ff 0%, #00b8d4 100%);
    box-shadow: 0 8px 28px rgba(79, 70, 229, 0.45), 0 2px 8px rgba(0, 0, 0, 0.35);
}
.mu-float-btn--save:hover:not(:disabled) {
    box-shadow: 0 12px 32px rgba(79, 70, 229, 0.55), 0 4px 12px rgba(0, 0, 0, 0.4);
}
.mu-float-btn--delete {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    box-shadow: 0 8px 28px rgba(220, 38, 38, 0.4), 0 2px 8px rgba(0, 0, 0, 0.35);
}
.mu-float-btn--delete:hover:not(:disabled) {
    box-shadow: 0 12px 32px rgba(220, 38, 38, 0.5), 0 4px 12px rgba(0, 0, 0, 0.4);
}
@media (max-width: 640px) {
    .mu-float-actions {
        right: 1rem;
        bottom: 1rem;
    }
    .mu-float-btn {
        padding: 0.65rem 1.1rem;
        font-size: 0.8rem;
    }
}
.mu-flash {
    margin: 0 0 1rem;
    padding: 0.85rem 1rem;
    border-radius: 10px;
    font-size: 0.85rem;
    font-weight: 500;
}
.mu-flash--success {
    background: rgba(34, 197, 94, 0.12);
    border: 1px solid rgba(34, 197, 94, 0.35);
    color: #86efac;
}
.mu-flash--error {
    background: rgba(248, 113, 113, 0.12);
    border: 1px solid rgba(248, 113, 113, 0.35);
    color: #fca5a5;
}

.mu-back {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.82rem;
    font-weight: 500;
    color: rgba(255,255,255,0.45) !important;
    text-decoration: none !important;
    margin-bottom: 1rem;
}
.mu-back:hover { color: #67e8f9 !important; }

.mu-empty {
    text-align: center;
    padding: 3rem 1.5rem !important;
    color: rgba(255,255,255,0.38) !important;
}
.mu-empty i { display: block; font-size: 2rem; margin-bottom: 0.75rem; opacity: 0.35; }

.mu-code {
    font-family: ui-monospace, 'Cascadia Code', 'Courier New', monospace;
    font-size: 0.72rem;
    padding: 0.35rem 0.5rem;
    border-radius: 6px;
    background: rgba(0,0,0,0.35);
    border: 1px solid rgba(255,255,255,0.08);
    color: rgba(165, 243, 252, 0.95);
    word-break: break-all;
}
</style>

<div class="mu-page ac-ops">
<?php if(!isset($_GET['user'])){ ?>

	<?php
	$flashType = null;
	$flashMessage = null;
	if (!empty($_GET['success']) && is_string($_GET['success'])) {
		$flashType = 'success';
		$flashMessage = $_GET['success'] === 'updated' ? 'User saved successfully.' : rawurldecode($_GET['success']);
	} elseif (!empty($_GET['error']) && is_string($_GET['error'])) {
		$flashType = 'error';
		$errorMessages = [
			'database_error' => 'Database error. Please try again.',
			'update_failed' => 'Update failed. Please try again.',
			'post_request_failed' => 'User saved but activation webhook failed.',
			'user_not_found' => 'User not found.',
			'missing_fields' => 'Missing required fields.',
			'unauthorized' => 'You do not have permission to update users.',
			'delete_failed' => 'Could not delete pending users. Please try again.',
		];
		$flashMessage = $errorMessages[$_GET['error']] ?? 'Something went wrong.';
	}
	if ($flashType && $flashMessage): ?>
	<div class="mu-flash mu-flash--<?php echo htmlspecialchars($flashType, ENT_QUOTES, 'UTF-8'); ?>" role="status">
		<?php echo htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8'); ?>
	</div>
	<?php endif; ?>

	<div class="mu-hero">
		<div class="mu-hero-inner">
			<p class="mu-kicker">Directory</p>
			<h1 class="mu-title">Members</h1>
			<p class="mu-lead">Search by name, email, phone, or ID. Open a host to see their automations. Trusted staff can adjust capacity and account status.</p>
		</div>
	</div>

	<div class="mu-shell">
		<div class="mu-shell-hd">
			<div class="mu-shell-hd-text">
				<h3>All accounts</h3>
				<p>Each row is a dashboard login. Social links open in a new tab.</p>
			</div>
		</div>

		<div class="mu-toolbar">
			<div class="mu-search">
				<i class="ti ti-search"></i>
				<input type="search" id="mentorAdminSearch" placeholder="Filter by name, email, phone, ID…" autocomplete="off" aria-label="Search mentor admins">
			</div>
			<p class="mu-count" id="mentorAdminSearchCount" aria-live="polite"></p>
		</div>

		<form id="mentor-update-all" action="updateusers.php" method="post"></form>

		<div class="mu-scroll">
			<table class="mu-table" id="mentorAdminTable">
				<thead>
					<tr>
						<th>Mentor</th>
						<th>Contact</th>
						<th>Social</th>
						<th>EA file</th>
						<th>Max</th>
						<th>Used</th>
						<th>Subs</th>
						<th>Status</th>
						<th>Super</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach(get_all_users() as $user){
						$uid = (int) $user['id'];
						$fid = 'mentor-update-all';
						$searchBlob = strtolower(trim(preg_replace('/\s+/', ' ', implode(' ', array_filter([
							(string)($user['fullname'] ?? ''),
							(string)($user['email'] ?? ''),
							(string)($user['phone'] ?? ''),
							(string)($user['id'] ?? ''),
							(string)($user['instagram'] ?? ''),
							(string)($user['tiktok'] ?? ''),
							(string)($user['telegram'] ?? ''),
						])))));
						$st = $user['status'] ?? '';
						$pillClass = 'mu-pill--pending';
						if ($st === 'active') $pillClass = 'mu-pill--active';
						if ($st === 'blocked') $pillClass = 'mu-pill--blocked';
						$socialBits = array_filter([
							$user['instagram'] ?? '',
							$user['tiktok'] ?? '',
							$user['telegram'] ?? '',
						]);
					?>
					<tr class="mentor-admin-row" data-mentor-search="<?php echo htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8'); ?>">
						<td>
							<a class="mu-name-link" href="users.php?user=<?php echo $uid; ?>&amp;uname=<?php echo urlencode($user['email']); ?>">
								<?php echo htmlspecialchars($user['fullname']); ?>
							</a>
							<div style="margin-top:4px;">
								<span class="mu-id-tag">ID <?php echo $uid; ?></span>
							</div>
						</td>
						<td>
							<div class="mu-email"><?php echo htmlspecialchars($user['email']); ?></div>
							<div class="mu-cell-dim" style="margin-top:2px; max-width:none;"><?php echo htmlspecialchars($user['phone']); ?></div>
						</td>
						<td>
							<?php if (empty($socialBits)): ?>
								<span class="mu-cell-dim">—</span>
							<?php else: ?>
								<div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
									<?php if (!empty($user['instagram'])): ?>
										<a href="<?php echo htmlspecialchars($user['instagram']); ?>" target="_blank" rel="noopener" class="mu-pill mu-pill--plain" style="font-size:0.65rem;">Instagram</a>
									<?php endif; ?>
									<?php if (!empty($user['tiktok'])): ?>
										<a href="<?php echo htmlspecialchars($user['tiktok']); ?>" target="_blank" rel="noopener" class="mu-pill mu-pill--plain" style="font-size:0.65rem;">TikTok</a>
									<?php endif; ?>
									<?php if (!empty($user['telegram'])): ?>
										<a href="<?php echo htmlspecialchars($user['telegram']); ?>" target="_blank" rel="noopener" class="mu-pill mu-pill--plain" style="font-size:0.65rem;">Telegram</a>
									<?php endif; ?>
								</div>
							<?php endif; ?>
						</td>
						<td>
							<?php echo !empty($user['ea_file'])
								? '<a href="' . htmlspecialchars($user['ea_file']) . '" download>Download</a>'
								: '<span class="mu-cell-dim">—</span>'; ?>
						</td>
						<td>
							<input class="mu-input-num" type="number" name="users[<?php echo $uid; ?>][total_keys]" form="<?php echo htmlspecialchars($fid, ENT_QUOTES, 'UTF-8'); ?>"
								value="<?php echo htmlspecialchars($user['total_keys']); ?>" required min="0" <?php echo $trusted ? '' : 'disabled'; ?> />
						</td>
						<td><span class="mu-stat"><?php echo (int) total_licences($user['id'], "jj"); ?></span></td>
						<td>
							<a class="mu-stat" href="subscriptions.php?m=<?php echo $uid; ?>"><?php echo (int) total_subscriptions($user['id'], true); ?></a>
						</td>
						<td>
							<select class="mu-select" name="users[<?php echo $uid; ?>][status]" form="<?php echo htmlspecialchars($fid, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $trusted ? '' : 'disabled'; ?>>
								<option value="pending" <?php if($st == 'pending') echo 'selected'; ?>>Pending</option>
								<option value="active" <?php if($st == 'active') echo 'selected'; ?>>Active</option>
								<option value="blocked" <?php if($st == 'blocked') echo 'selected'; ?>>Blocked</option>
							</select>
							<div style="margin-top:6px;"><span class="mu-pill <?php echo htmlspecialchars($pillClass); ?>"><?php echo htmlspecialchars(ucfirst($st)); ?></span></div>
						</td>
						<td>
							<?php if (!empty($user['super'])): ?>
								<span class="mu-pill mu-pill--super">Yes</span>
							<?php else: ?>
								<span class="mu-pill mu-pill--plain">No</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ($trusted) { ?>
								<button type="button" class="mu-btn-save" onclick="saveMentorUser(<?php echo $uid; ?>)">Save</button>
							<?php } else { ?>
								<span class="mu-cell-dim">—</span>
							<?php } ?>
						</td>
					</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
	</div>

	<?php if ($trusted) {
		$pendingDeleteCount = count(array_filter(get_all_users(), function ($u) {
			return ($u['status'] ?? '') === 'pending';
		}));
	?>
	<div class="mu-float-actions">
		<form id="delete-pending-form" action="delete_pending_users.php" method="post">
			<input type="hidden" name="confirm_delete_pending" value="1">
		</form>
		<button type="button" class="mu-float-btn mu-float-btn--delete" id="deletePendingBtn"
			<?php echo $pendingDeleteCount > 0 ? '' : 'disabled'; ?>
			data-pending-count="<?php echo (int) $pendingDeleteCount; ?>"
			aria-label="Delete all pending users">
			<i class="ti ti-trash"></i>
			Delete pending<?php echo $pendingDeleteCount > 0 ? ' (' . (int) $pendingDeleteCount . ')' : ''; ?>
		</button>
		<button type="submit" class="mu-float-btn mu-float-btn--save" form="mentor-update-all" aria-label="Save all user changes">
			<i class="ti ti-device-floppy"></i>
			Save all
		</button>
	</div>
	<?php } ?>

<script>
(function () {
	var input = document.getElementById('mentorAdminSearch');
	var countEl = document.getElementById('mentorAdminSearchCount');
	if (!input || !countEl) return;
	var rows = document.querySelectorAll('tr.mentor-admin-row[data-mentor-search]');
	var total = rows.length;
	function run() {
		var q = input.value.trim().toLowerCase().replace(/\s+/g, ' ');
		var n = 0;
		for (var i = 0; i < rows.length; i++) {
			var tr = rows[i];
			var hay = tr.getAttribute('data-mentor-search') || '';
			var show = !q || hay.indexOf(q) !== -1;
			tr.style.display = show ? '' : 'none';
			if (show) n++;
		}
		countEl.textContent = q ? ('Showing ' + n + ' of ' + total) : (total + ' mentor' + (total === 1 ? '' : 's'));
	}
	input.addEventListener('input', run);
	run();
})();

(function () {
	var deleteBtn = document.getElementById('deletePendingBtn');
	var deleteForm = document.getElementById('delete-pending-form');
	if (!deleteBtn || !deleteForm) return;

	deleteBtn.addEventListener('click', function () {
		if (deleteBtn.disabled) return;
		var count = parseInt(deleteBtn.getAttribute('data-pending-count') || '0', 10);
		var noun = count === 1 ? 'user' : 'users';
		var message = count > 0
			? 'Delete ' + count + ' pending ' + noun + '? This cannot be undone.'
			: 'Delete all pending users? This cannot be undone.';
		if (window.confirm(message)) {
			deleteForm.submit();
		}
	});
})();

function saveMentorUser(uid) {
	var totalKeys = document.querySelector('input[name="users[' + uid + '][total_keys]"]');
	var status = document.querySelector('select[name="users[' + uid + '][status]"]');
	if (!totalKeys || !status) return;

	var form = document.createElement('form');
	form.method = 'post';
	form.action = 'updateuser.php';

	var fields = {
		id: String(uid),
		total_keys: totalKeys.value,
		status: status.value
	};

	for (var key in fields) {
		if (!Object.prototype.hasOwnProperty.call(fields, key)) continue;
		var input = document.createElement('input');
		input.type = 'hidden';
		input.name = key;
		input.value = fields[key];
		form.appendChild(input);
	}

	document.body.appendChild(form);
	form.submit();
}
</script>

<?php } else {
    $user_id = (int) $_GET['user'];
    $user_name = isset($_GET['uname']) ? $_GET['uname'] : '';
    $mentorFullname = get_admin($user_name, 'fullname');
?>

	<a href="users.php" class="mu-back"><i class="ti ti-arrow-left"></i> All mentor admins</a>

	<div class="mu-hero" style="margin-bottom:1.25rem;">
		<div class="mu-hero-inner">
			<p class="mu-kicker">Mentor</p>
			<h1 class="mu-title"><?php echo htmlspecialchars($mentorFullname); ?></h1>
			<p class="mu-lead">Automations and access codes for this account.</p>
		</div>
	</div>

	<div class="mu-shell">
		<div class="mu-shell-hd">
			<div class="mu-shell-hd-text">
				<h3>Expert advisors</h3>
				<p>Keys shown are EA secret codes for API authentication.</p>
			</div>
		</div>
		<div class="mu-scroll">
			<table class="mu-table">
				<thead>
					<tr>
						<th>EA</th>
						<th>Licence code</th>
						<th>Users</th>
						<th>Active</th>
						<th>Created</th>
					</tr>
				</thead>
				<tbody>
					<?php
					$i = total_EAs($user_id);
					if ($i == 0): ?>
					<tr>
						<td colspan="5" class="mu-empty">
							<i class="ti ti-robot"></i>
							No expert advisors for this mentor.
						</td>
					</tr>
					<?php else: while($i > 0) { ?>
					<tr>
						<td><strong style="font-weight:600; color:rgba(255,255,255,0.92);"><?php echo htmlspecialchars(EA_details($i, 'name', $user_id)); ?></strong></td>
						<td><span class="mu-code"><?php echo htmlspecialchars(EA_details($i, 'secret_code', $user_id)); ?></span></td>
						<td><span class="mu-stat"><?php echo (int) users_from_EA(EA_details($i, 'id', $user_id)); ?></span></td>
						<td><span class="mu-pill mu-pill--active"><?php echo (int) users_from_EA(EA_details($i, 'id', $user_id), 'active'); ?> active</span></td>
						<td><span class="mu-cell-dim"><?php echo htmlspecialchars(EA_details($i, 'date', get_admin($user_name, 'id'))); ?></span></td>
					</tr>
					<?php $i--; } endif; ?>
				</tbody>
			</table>
		</div>
	</div>

<?php } ?>
</div>
<?php
    include("include/footer.php");
?>
