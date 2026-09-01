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

<style>
.af-page { max-width: 1440px; margin: 0 auto; padding: 0 2px 2.5rem; }
.af-hero {
    border-radius: 20px; margin-bottom: 1.75rem; padding: 2rem 2.25rem;
    background: linear-gradient(145deg, #12161c 0%, #1a222d 42%, #0e1116 100%);
    border: 1px solid rgba(255,255,255,0.07);
}
.af-title { font-size: 1.65rem; font-weight: 700; color: #fff; margin: 0 0 0.5rem; }
.af-lead { color: rgba(255,255,255,0.65); margin: 0; max-width: 52rem; }
.af-back { display: inline-flex; align-items: center; gap: 6px; color: rgba(255,255,255,0.65); text-decoration: none; font-size: 0.88rem; margin-bottom: 1rem; }
.af-back:hover { color: #fff; }
.af-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 1.5rem; }
.af-stat { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 14px; padding: 16px; }
.af-stat-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.08em; color: rgba(255,255,255,0.5); }
.af-stat-value { font-size: 1.35rem; font-weight: 700; color: #fff; margin-top: 4px; }
.af-stat-value.earned { color: #86efac; }
.af-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; padding: 1.25rem; margin-bottom: 1.5rem; }
.af-card h3 { color: #fff; font-size: 1.1rem; margin: 0 0 1rem; display: flex; align-items: center; gap: 8px; }
.af-table-wrap { overflow-x: auto; }
.af-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; min-width: 860px; }
.af-table th, .af-table td { padding: 10px 8px; border-bottom: 1px solid rgba(255,255,255,0.08); text-align: left; color: rgba(255,255,255,0.85); vertical-align: top; }
.af-table th { color: rgba(255,255,255,0.45); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; }
.af-table .amount { font-weight: 600; color: #fff; white-space: nowrap; }
.af-pill { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 0.72rem; font-weight: 600; }
.af-pill-confirmed { background: rgba(34,197,94,0.15); color: #86efac; }
.af-pill-pending { background: rgba(234,179,8,0.15); color: #fde047; }
.af-pill-processing { background: rgba(59,130,246,0.15); color: #93c5fd; }
.af-pill-paid { background: rgba(34,197,94,0.15); color: #86efac; }
.af-pill-rejected { background: rgba(239,68,68,0.15); color: #fca5a5; }
.af-pill-active { background: rgba(34,197,94,0.12); color: #86efac; }
.af-pill-paused { background: rgba(234,179,8,0.12); color: #fde047; }
.af-actions-bar { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 1.25rem; padding-top: 1.25rem; border-top: 1px solid rgba(255,255,255,0.08); }
.af-btn { border: 0; border-radius: 10px; padding: 10px 16px; font-size: 0.85rem; font-weight: 600; cursor: pointer; color: #fff; }
.af-btn-activate { background: #16a34a; }
.af-btn-suspend { background: #ca8a04; }
.af-btn-block { background: #dc2626; }
.af-btn-delete { background: transparent; border: 1px solid rgba(239,68,68,0.45); color: #fca5a5; margin-left: auto; }
.af-btn-delete:hover { background: rgba(239,68,68,0.12); }
.af-alert { border-radius: 12px; padding: 12px 16px; margin-bottom: 1rem; font-size: 0.9rem; }
.af-alert-success { background: rgba(34,197,94,0.12); border: 1px solid rgba(34,197,94,0.25); color: #86efac; }
.af-alert-danger { background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.25); color: #fca5a5; }
.af-detail { font-size: 0.78rem; color: rgba(255,255,255,0.55); margin-top: 2px; word-break: break-all; }
.af-empty { text-align: center; padding: 2rem; color: rgba(255,255,255,0.45); }
.af-meta { display: flex; flex-wrap: wrap; gap: 10px 18px; margin-top: 0.75rem; font-size: 0.85rem; color: rgba(255,255,255,0.65); }
.af-meta strong { color: #fff; }
.af-link { color: #93c5fd; text-decoration: none; }
.af-link:hover { text-decoration: underline; }
</style>

<div class="af-page">
    <a class="af-back" href="affiliates.php"><i class="ti ti-arrow-left"></i> Back to Affiliates</a>

    <?php if (!empty($_GET['updated'])): ?>
        <div class="af-alert af-alert-success">Affiliate status updated.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['error'])): ?>
        <div class="af-alert af-alert-danger"><?php echo htmlspecialchars(urldecode((string) $_GET['error']), ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="af-hero">
        <h1 class="af-title"><?php echo htmlspecialchars($affiliate['full_name'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="af-lead">All commissions earned by this affiliate from member payments.</p>
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
