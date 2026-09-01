<?php
require __DIR__ . '/include/require_super.php';
require dirname(__DIR__) . '/php-includes/security-bridge.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/ip-block.php';
auraai_sec_bootstrap();

require dirname(__DIR__) . '/php-includes/connect.php';
auraai_ip_block_bootstrap_cache($con);

$adminId = (int) get_admin($_SESSION['username'], 'id');
$blocks = auraai_ip_block_list($con);
$visitorIp = auraai_sec_client_ip();

include 'include/header.php';
?>

<style>
.ip-page { max-width: 1100px; margin: 0 auto; padding: 0 2px 2.5rem; }
.ip-hero {
    border-radius: 20px; margin-bottom: 1.75rem; padding: 2rem 2.25rem;
    background: linear-gradient(145deg, #12161c 0%, #1a222d 42%, #0e1116 100%);
    border: 1px solid rgba(255,255,255,0.07);
}
.ip-title { font-size: 1.65rem; font-weight: 700; color: #fff; margin: 0 0 0.5rem; }
.ip-lead { color: rgba(255,255,255,0.65); margin: 0; max-width: 46rem; }
.ip-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; padding: 1.25rem; margin-bottom: 1.5rem; }
.ip-card h3 { color: #fff; font-size: 1.1rem; margin: 0 0 1rem; display: flex; align-items: center; gap: 8px; }
.ip-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media (max-width: 767.98px) { .ip-form-grid { grid-template-columns: 1fr; } }
.ip-field label { display: block; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; color: rgba(255,255,255,0.45); margin-bottom: 6px; }
.ip-field input, .ip-field textarea, .ip-field select {
    width: 100%; border-radius: 10px; border: 1px solid rgba(255,255,255,0.12);
    background: rgba(0,0,0,0.28); color: #fff; padding: 0.65rem 0.75rem; font-size: 0.9rem;
}
.ip-field textarea { min-height: 72px; resize: vertical; }
.ip-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
.ip-btn { border: 0; border-radius: 10px; padding: 0.65rem 1rem; font-size: 0.85rem; font-weight: 600; cursor: pointer; color: #fff; }
.ip-btn-primary { background: #2563eb; }
.ip-btn-danger { background: #b91c1c; }
.ip-btn-ghost { background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.12); }
.ip-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
.ip-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; min-width: 720px; }
.ip-table th, .ip-table td { padding: 10px 8px; border-bottom: 1px solid rgba(255,255,255,0.08); text-align: left; color: rgba(255,255,255,0.85); vertical-align: top; }
.ip-table th { color: rgba(255,255,255,0.45); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; }
.ip-pill { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 0.72rem; font-weight: 600; background: rgba(239,68,68,0.15); color: #fca5a5; }
.ip-empty { text-align: center; padding: 2rem; color: rgba(255,255,255,0.45); }
.ip-note { font-size: 0.82rem; color: rgba(255,255,255,0.55); margin-top: 0.75rem; line-height: 1.5; }
.ip-current { font-family: ui-monospace, Menlo, Consolas, monospace; color: #93c5fd; }
</style>

<div class="ip-page">
    <div class="ip-hero">
        <h1 class="ip-title">Blocked IP addresses</h1>
        <p class="ip-lead">Stop abusive visitors from accessing the public site (shop, landing page, affiliate portal). The admin panel stays reachable so you can always unblock.</p>
    </div>

    <?php if (!empty($_GET['added'])): ?>
        <div class="alert alert-success">IP address blocked.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['removed'])): ?>
        <div class="alert alert-success">IP address unblocked.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars(urldecode((string) $_GET['error']), ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="ip-card">
        <h3><i class="ti ti-ban"></i> Block an IP address</h3>
        <form method="post" action="blocked-ip-update.php">
            <input type="hidden" name="action" value="add">
            <div class="ip-form-grid">
                <div class="ip-field">
                    <label for="ip_address">IP address</label>
                    <input type="text" id="ip_address" name="ip_address" placeholder="e.g. 203.0.113.45" required>
                </div>
                <div class="ip-field">
                    <label for="expires_days">Expires after (days, optional)</label>
                    <select id="expires_days" name="expires_days">
                        <option value="">Never (permanent)</option>
                        <option value="1">1 day</option>
                        <option value="7">7 days</option>
                        <option value="30">30 days</option>
                        <option value="90">90 days</option>
                    </select>
                </div>
                <div class="ip-field" style="grid-column: 1 / -1;">
                    <label for="reason">Reason (optional)</label>
                    <textarea id="reason" name="reason" placeholder="Spam, abuse, fraud attempt…"></textarea>
                </div>
            </div>
            <div class="ip-actions">
                <button type="submit" class="ip-btn ip-btn-primary"><i class="ti ti-ban"></i> Block IP</button>
                <button type="button" class="ip-btn ip-btn-ghost" onclick="document.getElementById('ip_address').value='<?php echo htmlspecialchars($visitorIp, ENT_QUOTES, 'UTF-8'); ?>';">
                    Use my IP (<?php echo htmlspecialchars($visitorIp, ENT_QUOTES, 'UTF-8'); ?>)
                </button>
            </div>
            <p class="ip-note">Your current IP: <span class="ip-current"><?php echo htmlspecialchars($visitorIp, ENT_QUOTES, 'UTF-8'); ?></span>. Blocked IPs receive a 403 on all public pages.</p>
        </form>
    </div>

    <div class="ip-card">
        <h3><i class="ti ti-shield-off"></i> Active blocks (<?php echo count($blocks); ?>)</h3>
        <?php if (empty($blocks)): ?>
            <div class="ip-empty">No IP addresses are blocked.</div>
        <?php else: ?>
            <div class="ip-table-wrap">
                <table class="ip-table">
                    <thead>
                        <tr>
                            <th>IP address</th>
                            <th>Reason</th>
                            <th>Blocked by</th>
                            <th>Created</th>
                            <th>Expires</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($blocks as $row): ?>
                        <tr>
                            <td><span class="ip-pill"><?php echo htmlspecialchars($row['ip_address'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td><?php echo htmlspecialchars($row['reason'] !== '' ? $row['reason'] : '—', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($row['blocked_by_name'] ?: '—', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(date('M j, Y · H:i', strtotime($row['created_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo !empty($row['expires_at']) ? htmlspecialchars(date('M j, Y · H:i', strtotime($row['expires_at'])), ENT_QUOTES, 'UTF-8') : 'Never'; ?></td>
                            <td>
                                <form method="post" action="blocked-ip-update.php" onsubmit="return confirm('Unblock this IP address?');">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                    <button type="submit" class="ip-btn ip-btn-danger" style="padding:0.4rem 0.65rem;font-size:0.78rem;">Unblock</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'include/footer.php'; ?>
