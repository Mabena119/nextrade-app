<?php
require __DIR__ . '/include/require_super.php';
require dirname(__DIR__) . '/php-includes/security-bridge.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/affiliate.php';
auraai_sec_bootstrap();

require dirname(__DIR__) . '/php-includes/connect.php';

$affiliateId = auraai_sec_int($_GET['id'] ?? 0, 1, 999999);
if ($affiliateId === null) {
    header('Location: affiliates.php?error=' . rawurlencode('Invalid affiliate.'));
    exit;
}

$affiliate = auraai_affiliate_by_id($con, $affiliateId);
if (!$affiliate) {
    header('Location: affiliates.php?error=' . rawurlencode('Affiliate not found.'));
    exit;
}

$stats = auraai_affiliate_dashboard_stats($con, $affiliateId);
$balance = auraai_affiliate_balance_info($con, $affiliateId);
$conversions = auraai_affiliate_admin_conversions($con, $affiliateId);
$withdrawals = auraai_affiliate_withdrawals($con, $affiliateId, 50);
$rate = round(((float) ($affiliate['commission_rate'] ?? 0)) * 100, 1);
$status = strtolower((string) ($affiliate['status'] ?? 'active'));

include 'include/header.php';
?>

<div class="aura-console-page">
    <a class="af-back" href="affiliates.php"><i class="ti ti-arrow-left"></i> All affiliates</a>

    <?php if (!empty($_GET['updated'])): ?>
        <div class="aura-alert aura-alert-success" style="margin-bottom:1rem;">Affiliate status updated.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['error'])): ?>
        <div class="aura-alert aura-alert-danger" style="margin-bottom:1rem;"><?php echo htmlspecialchars(urldecode((string) $_GET['error']), ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <header class="aura-console-head">
        <div>
            <p class="aura-kicker">Affiliate</p>
            <h1><?php echo htmlspecialchars($affiliate['full_name'], ENT_QUOTES, 'UTF-8'); ?></h1>
            <p>Commissions, balance, and payout history for this partner.</p>
        </div>
    </header>

    <div class="af-hero-panel">
        <div class="af-meta">
            <span>Email: <strong><?php echo htmlspecialchars($affiliate['email'], ENT_QUOTES, 'UTF-8'); ?></strong></span>
            <span>Code: <strong><?php echo htmlspecialchars($affiliate['code'], ENT_QUOTES, 'UTF-8'); ?></strong></span>
            <span>Rate: <strong><?php echo htmlspecialchars((string) $rate, ENT_QUOTES, 'UTF-8'); ?>%</strong></span>
            <span>Status: <span class="af-pill af-pill-<?php echo htmlspecialchars(auraai_affiliate_status_pill_class($status), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(auraai_affiliate_status_label($status), ENT_QUOTES, 'UTF-8'); ?></span></span>
            <span>Referral: <a class="af-link" href="<?php echo htmlspecialchars(auraai_affiliate_shop_link($affiliate['code']), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Open shop link</a></span>
        </div>

        <div class="af-actions-bar">
            <?php if ($status !== 'active'): ?>
            <form method="post" action="affiliate-status-update.php">
                <input type="hidden" name="affiliate_id" value="<?php echo (int) $affiliateId; ?>">
                <input type="hidden" name="action" value="activate">
                <button type="submit" class="af-btn af-btn-activate">Activate</button>
            </form>
            <?php endif; ?>
            <?php if ($status !== 'paused'): ?>
            <form method="post" action="affiliate-status-update.php" onsubmit="return confirm('Suspend this affiliate? They can still log in but will not earn new commissions.');">
                <input type="hidden" name="affiliate_id" value="<?php echo (int) $affiliateId; ?>">
                <input type="hidden" name="action" value="suspend">
                <button type="submit" class="af-btn af-btn-suspend">Suspend</button>
            </form>
            <?php endif; ?>
            <?php if ($status !== 'blocked'): ?>
            <form method="post" action="affiliate-status-update.php" onsubmit="return confirm('Block this affiliate? They will not be able to log in.');">
                <input type="hidden" name="affiliate_id" value="<?php echo (int) $affiliateId; ?>">
                <input type="hidden" name="action" value="block">
                <button type="submit" class="af-btn af-btn-block">Block</button>
            </form>
            <?php endif; ?>
            <form method="post" action="affiliate-status-update.php" onsubmit="return confirm('Permanently delete this affiliate and all related data? This cannot be undone.');">
                <input type="hidden" name="affiliate_id" value="<?php echo (int) $affiliateId; ?>">
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="af-btn af-btn-delete">Delete</button>
            </form>
        </div>
    </div>

    <div class="af-stats">
        <div class="af-stat"><div class="af-stat-label">Clicks</div><div class="af-stat-value"><?php echo number_format((int) $stats['clicks']); ?></div></div>
        <div class="af-stat"><div class="af-stat-label">Confirmed Sales</div><div class="af-stat-value"><?php echo number_format((int) $stats['conversions']); ?></div></div>
        <div class="af-stat"><div class="af-stat-label">Total Commission</div><div class="af-stat-value earned"><?php echo htmlspecialchars(auraai_affiliate_format_money((int) $stats['commission_cents']), ENT_QUOTES, 'UTF-8'); ?></div></div>
        <div class="af-stat"><div class="af-stat-label">Available Balance</div><div class="af-stat-value"><?php echo htmlspecialchars(auraai_affiliate_format_money((int) $balance['available_cents']), ENT_QUOTES, 'UTF-8'); ?></div></div>
        <div class="af-stat"><div class="af-stat-label">Paid Out</div><div class="af-stat-value"><?php echo htmlspecialchars(auraai_affiliate_format_money((int) $balance['paid_out_cents']), ENT_QUOTES, 'UTF-8'); ?></div></div>
    </div>

    <div class="af-card">
        <h3><i class="ti ti-receipt"></i> Commissions (<?php echo count($conversions); ?>)</h3>
        <?php if (empty($conversions)): ?>
            <div class="af-empty">No commissions recorded for this affiliate yet.</div>
        <?php else: ?>
            <div class="af-table-wrap">
                <table class="af-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Member Email</th>
                            <th>Product</th>
                            <th>Gateway</th>
                            <th>Payment Ref</th>
                            <th>Sale</th>
                            <th>Commission</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($conversions as $row):
                        $st = strtolower((string) ($row['status'] ?? 'confirmed'));
                        $pillClass = $st === 'confirmed' ? 'confirmed' : ($st === 'rejected' ? 'rejected' : 'pending');
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars(date('M j, Y · H:i', strtotime($row['converted_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(strtoupper((string) $row['product_type']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(strtoupper((string) $row['gateway']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><span class="af-detail"><?php echo htmlspecialchars((string) $row['gateway_ref'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td class="amount"><?php echo htmlspecialchars(auraai_affiliate_format_money((int) $row['amount_cents']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="amount"><?php echo htmlspecialchars(auraai_affiliate_format_money((int) $row['commission_cents']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><span class="af-pill af-pill-<?php echo htmlspecialchars($pillClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(ucfirst($st), ENT_QUOTES, 'UTF-8'); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($withdrawals)): ?>
    <div class="af-card">
        <h3><i class="ti ti-cash"></i> Withdrawal History</h3>
        <div class="af-table-wrap">
            <table class="af-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Method</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($withdrawals as $w):
                    $wst = strtolower((string) ($w['status'] ?? 'pending'));
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars(date('M j, Y · H:i', strtotime($w['requested_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars(auraai_affiliate_payout_method_label($w), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="amount"><?php echo htmlspecialchars(auraai_affiliate_format_money((int) $w['amount_cents']), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><span class="af-pill af-pill-<?php echo htmlspecialchars($wst, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(ucfirst($wst), ENT_QUOTES, 'UTF-8'); ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include 'include/footer.php'; ?>
