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

<div class="aura-console-page">
    <header class="aura-console-head">
        <div>
            <p class="aura-kicker">Security</p>
            <h1>Blocked IPs</h1>
            <p>Block abusive visitors from the public site. Your console always stays reachable.</p>
        </div>
    </header>

    <?php if (!empty($_GET['added'])): ?>
        <div class="aura-alert aura-alert-success" style="margin-bottom:1rem;">IP address blocked.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['removed'])): ?>
        <div class="aura-alert aura-alert-success" style="margin-bottom:1rem;">IP address unblocked.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['error'])): ?>
        <div class="aura-alert aura-alert-danger" style="margin-bottom:1rem;"><?php echo htmlspecialchars(urldecode((string) $_GET['error']), ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="aura-panel" style="margin-bottom:1rem;">
        <h2 style="margin:0 0 1rem;font-family:var(--aura-font-display);font-size:1.1rem;"><i class="ti ti-ban"></i> Block an IP</h2>
        <form method="post" action="blocked-ip-update.php">
            <input type="hidden" name="action" value="add">
            <div class="ip-form-grid">
                <div class="ip-field">
                    <label for="ip_address">IP address</label>
                    <input class="aura-input" type="text" id="ip_address" name="ip_address" placeholder="e.g. 203.0.113.45" required>
                </div>
                <div class="ip-field">
                    <label for="expires_days">Expires after (optional)</label>
                    <select class="aura-input" id="expires_days" name="expires_days">
                        <option value="">Never (permanent)</option>
                        <option value="1">1 day</option>
                        <option value="7">7 days</option>
                        <option value="30">30 days</option>
                        <option value="90">90 days</option>
                    </select>
                </div>
                <div class="ip-field" style="grid-column: 1 / -1;">
                    <label for="reason">Reason (optional)</label>
                    <textarea class="aura-input" id="reason" name="reason" placeholder="Spam, abuse, fraud attempt…"></textarea>
                </div>
            </div>
            <div class="ip-actions">
                <button type="submit" class="aura-btn aura-btn-primary"><i class="ti ti-ban"></i> Block IP</button>
                <button type="button" class="aura-btn aura-btn-ghost" onclick="document.getElementById('ip_address').value='<?php echo htmlspecialchars($visitorIp, ENT_QUOTES, 'UTF-8'); ?>';">
                    Use my IP (<?php echo htmlspecialchars($visitorIp, ENT_QUOTES, 'UTF-8'); ?>)
                </button>
            </div>
            <p class="ip-note">Your IP: <span class="ip-current"><?php echo htmlspecialchars($visitorIp, ENT_QUOTES, 'UTF-8'); ?></span></p>
        </form>
    </section>

    <section class="aura-panel">
        <h2 style="margin:0 0 1rem;font-family:var(--aura-font-display);font-size:1.1rem;"><i class="ti ti-shield-off"></i> Active blocks (<?php echo count($blocks); ?>)</h2>
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
                                    <button type="submit" class="aura-btn aura-btn-ghost" style="padding:0.4rem 0.65rem;font-size:0.78rem;color:#fda4af;">Unblock</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php include 'include/footer.php'; ?>
