<?php
require __DIR__ . '/include/require_super.php';
require dirname(__DIR__) . '/php-includes/security-bridge.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/affiliate.php';
auraai_sec_bootstrap();

require dirname(__DIR__) . '/php-includes/connect.php';

$filter = auraai_sec_enum($_GET['status'] ?? '', ['pending', 'processing', 'paid', 'rejected']);
$affiliates = auraai_affiliate_admin_overview($con);
$withdrawals = auraai_affiliate_admin_withdrawals($con, $filter);
$pendingCount = count(auraai_affiliate_admin_withdrawals($con, 'pending'));
$processingCount = count(auraai_affiliate_admin_withdrawals($con, 'processing'));

// Total still owed to affiliates: all confirmed earnings minus withdrawals already paid out.
$owedCents = 0;
foreach ($affiliates as $affRow) {
    $owedCents += max(0, (int) $affRow['earned_cents'] - (int) $affRow['paid_cents']);
}

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
.af-lead { color: rgba(255,255,255,0.65); margin: 0; max-width: 42rem; }
.af-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 1.5rem; }
.af-stat { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 14px; padding: 16px; }
.af-stat-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.08em; color: rgba(255,255,255,0.5); }
.af-stat-value { font-size: 1.4rem; font-weight: 700; color: #fff; margin-top: 4px; }
.af-stat-value.warn { color: #fde047; }
.af-stat-value.owed { color: #86efac; }
.af-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; padding: 1.25rem; margin-bottom: 1.5rem; }
.af-card h3 { color: #fff; font-size: 1.1rem; margin: 0 0 1rem; display: flex; align-items: center; gap: 8px; }
.af-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 1rem; }
.af-tab { padding: 8px 14px; border-radius: 999px; border: 1px solid rgba(255,255,255,0.12); color: rgba(255,255,255,0.75); text-decoration: none; font-size: 0.85rem; }
.af-tab.active, .af-tab:hover { background: rgba(99,102,241,0.2); border-color: rgba(99,102,241,0.45); color: #fff; }
.af-table-wrap { overflow-x: auto; }
.af-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; min-width: 720px; }
.af-table th, .af-table td { padding: 10px 8px; border-bottom: 1px solid rgba(255,255,255,0.08); text-align: left; color: rgba(255,255,255,0.85); }
.af-table th { color: rgba(255,255,255,0.45); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; }
.af-pill { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 0.72rem; font-weight: 600; }
.af-pill-pending { background: rgba(234,179,8,0.15); color: #fde047; }
.af-pill-processing { background: rgba(59,130,246,0.15); color: #93c5fd; }
.af-pill-paid { background: rgba(34,197,94,0.15); color: #86efac; }
.af-pill-rejected { background: rgba(239,68,68,0.15); color: #fca5a5; }
.af-pill-active { background: rgba(34,197,94,0.12); color: #86efac; }
.af-pill-paused { background: rgba(234,179,8,0.12); color: #fde047; }
.af-pill-rejected { background: rgba(239,68,68,0.15); color: #fca5a5; }
.af-actions { display: flex; flex-wrap: wrap; gap: 6px; }
.af-btn { border: 0; border-radius: 8px; padding: 6px 10px; font-size: 0.78rem; font-weight: 600; cursor: pointer; color: #fff; }
.af-btn-pay { background: #16a34a; }
.af-btn-process { background: #2563eb; }
.af-btn-reject { background: #7f1d1d; }
.af-detail { font-size: 0.78rem; color: rgba(255,255,255,0.55); margin-top: 2px; word-break: break-all; }
.af-empty { text-align: center; padding: 2rem; color: rgba(255,255,255,0.45); }
.af-link { color: #93c5fd; text-decoration: none; }
.af-link:hover { text-decoration: underline; color: #fff; }
.af-row-link { cursor: pointer; }
.af-row-link:hover td { background: rgba(99,102,241,0.08); }
.af-search-wrap { margin-bottom: 1rem; }
.af-search-field { position: relative; max-width: 420px; }
.af-search-field i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,0.45); font-size: 1rem; pointer-events: none; }
.af-search-input {
    width: 100%; padding: 11px 14px 11px 42px; border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.12); background: rgba(255,255,255,0.05);
    color: #fff; font-size: 0.9rem;
}
.af-search-input:focus { outline: none; border-color: rgba(99,102,241,0.55); box-shadow: 0 0 0 3px rgba(99,102,241,0.15); }
.af-search-input::placeholder { color: rgba(255,255,255,0.38); }
.af-search-meta { margin-top: 8px; font-size: 0.78rem; color: rgba(255,255,255,0.45); }
.af-row-hidden { display: none; }
</style>

<div class="af-page">
    <div class="af-hero">
        <h1 class="af-title">Affiliate Program</h1>
        <p class="af-lead">Monitor affiliate performance, review withdrawal requests, and manage payouts.</p>
    </div>

    <?php if (!empty($_GET['updated'])): ?>
        <div class="alert alert-success">Withdrawal updated.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['deleted'])): ?>
        <div class="alert alert-success">Affiliate deleted.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars(urldecode((string) $_GET['error']), ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="af-stats">
        <div class="af-stat"><div class="af-stat-label">Affiliates</div><div class="af-stat-value"><?php echo count($affiliates); ?></div></div>
        <div class="af-stat"><div class="af-stat-label">Pending Withdrawals</div><div class="af-stat-value warn"><?php echo $pendingCount; ?></div></div>
        <div class="af-stat"><div class="af-stat-label">Processing</div><div class="af-stat-value"><?php echo $processingCount; ?></div></div>
        <div class="af-stat"><div class="af-stat-label">Owed to Affiliates</div><div class="af-stat-value owed"><?php echo htmlspecialchars(auraai_affiliate_format_money($owedCents), ENT_QUOTES, 'UTF-8'); ?></div></div>
    </div>

    <div class="af-card">
        <h3><i class="ti ti-cash"></i> Withdrawal Requests</h3>
        <div class="af-tabs">
            <a class="af-tab <?php echo $filter === null ? 'active' : ''; ?>" href="affiliates.php">All</a>
            <a class="af-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>" href="affiliates.php?status=pending">Pending</a>
            <a class="af-tab <?php echo $filter === 'processing' ? 'active' : ''; ?>" href="affiliates.php?status=processing">Processing</a>
            <a class="af-tab <?php echo $filter === 'paid' ? 'active' : ''; ?>" href="affiliates.php?status=paid">Paid</a>
            <a class="af-tab <?php echo $filter === 'rejected' ? 'active' : ''; ?>" href="affiliates.php?status=rejected">Rejected</a>
        </div>

        <?php if (empty($withdrawals)): ?>
            <div class="af-empty">No withdrawal requests<?php echo $filter ? ' with this status' : ''; ?>.</div>
        <?php else: ?>
            <div class="af-table-wrap">
                <table class="af-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Affiliate</th>
                            <th>Method</th>
                            <th>Payout Details</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($withdrawals as $w):
                        $st = strtolower($w['status']);
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars(date('M j, Y H:i', strtotime($w['requested_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <a class="af-link" href="affiliate-detail.php?id=<?php echo (int) $w['affiliate_id']; ?>">
                                    <strong><?php echo htmlspecialchars($w['full_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                </a>
                                <div class="af-detail"><?php echo htmlspecialchars($w['affiliate_email'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($w['affiliate_code'], ENT_QUOTES, 'UTF-8'); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars(auraai_affiliate_payout_method_label($w), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><span class="af-detail"><?php echo htmlspecialchars(auraai_affiliate_admin_payout_details($w), ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td><strong><?php echo htmlspecialchars(auraai_affiliate_format_money((int) $w['amount_cents']), ENT_QUOTES, 'UTF-8'); ?></strong></td>
                            <td><span class="af-pill af-pill-<?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(ucfirst($st), ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td>
                                <?php if (in_array($st, ['pending', 'processing'], true)): ?>
                                <div class="af-actions">
                                    <?php if ($st === 'pending'): ?>
                                    <form method="post" action="affiliate-withdrawal-update.php" style="display:inline;">
                                        <input type="hidden" name="id" value="<?php echo (int) $w['id']; ?>">
                                        <input type="hidden" name="status" value="processing">
                                        <button type="submit" class="af-btn af-btn-process">Processing</button>
                                    </form>
                                    <?php endif; ?>
                                    <form method="post" action="affiliate-withdrawal-update.php" style="display:inline;">
                                        <input type="hidden" name="id" value="<?php echo (int) $w['id']; ?>">
                                        <input type="hidden" name="status" value="paid">
                                        <button type="submit" class="af-btn af-btn-pay">Mark Paid</button>
                                    </form>
                                    <form method="post" action="affiliate-withdrawal-update.php" style="display:inline;" onsubmit="return confirm('Reject this withdrawal?');">
                                        <input type="hidden" name="id" value="<?php echo (int) $w['id']; ?>">
                                        <input type="hidden" name="status" value="rejected">
                                        <button type="submit" class="af-btn af-btn-reject">Reject</button>
                                    </form>
                                </div>
                                <?php else: ?>
                                    <span class="af-detail"><?php echo !empty($w['processed_at']) ? htmlspecialchars(date('M j, Y', strtotime($w['processed_at'])), ENT_QUOTES, 'UTF-8') : '—'; ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="af-card">
        <h3><i class="ti ti-chart-bar"></i> Affiliate Performance</h3>
        <?php if (!empty($affiliates)): ?>
        <div class="af-search-wrap">
            <div class="af-search-field">
                <i class="ti ti-search"></i>
                <input
                    type="search"
                    id="affiliateSearch"
                    class="af-search-input"
                    placeholder="Search by name, email, or referral code…"
                    autocomplete="off"
                    aria-label="Search affiliates"
                >
            </div>
            <div class="af-search-meta" id="affiliateSearchMeta"><?php echo count($affiliates); ?> affiliate<?php echo count($affiliates) === 1 ? '' : 's'; ?></div>
        </div>
        <?php endif; ?>
        <?php if (empty($affiliates)): ?>
            <div class="af-empty">No affiliates registered yet.</div>
        <?php else: ?>
            <div class="af-table-wrap">
                <table class="af-table" id="affiliatePerformanceTable">
                    <thead>
                        <tr>
                            <th>Affiliate</th>
                            <th>Code</th>
                            <th>Rate</th>
                            <th>Clicks</th>
                            <th>Sales</th>
                            <th>Earned</th>
                            <th>Pending</th>
                            <th>Paid Out</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($affiliates as $a):
                        $rate = round(((float) $a['commission_rate']) * 100, 1);
                        $st = strtolower($a['status'] ?? 'active');
                    ?>
                        <tr
                            class="af-row-link af-affiliate-row"
                            data-search="<?php echo htmlspecialchars(strtolower(trim(($a['full_name'] ?? '') . ' ' . ($a['email'] ?? '') . ' ' . ($a['code'] ?? '') . ' ' . ($a['status'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?>"
                            onclick="window.location='affiliate-detail.php?id=<?php echo (int) $a['id']; ?>'"
                        >
                            <td>
                                <a class="af-link" href="affiliate-detail.php?id=<?php echo (int) $a['id']; ?>" onclick="event.stopPropagation();">
                                    <strong><?php echo htmlspecialchars($a['full_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                </a>
                                <div class="af-detail"><?php echo htmlspecialchars($a['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($a['code'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $rate, ENT_QUOTES, 'UTF-8'); ?>%</td>
                            <td><?php echo number_format((int) $a['clicks']); ?></td>
                            <td><?php echo number_format((int) $a['conversions']); ?></td>
                            <td><?php echo htmlspecialchars(auraai_affiliate_format_money((int) $a['earned_cents']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(auraai_affiliate_format_money((int) $a['pending_cents']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(auraai_affiliate_format_money((int) $a['paid_cents']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><span class="af-pill af-pill-<?php echo htmlspecialchars(auraai_affiliate_status_pill_class($st), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(auraai_affiliate_status_label($st), ENT_QUOTES, 'UTF-8'); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="af-empty af-row-hidden" id="affiliateSearchEmpty">No affiliates match your search.</div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    var input = document.getElementById('affiliateSearch');
    if (!input) return;

    var rows = Array.prototype.slice.call(document.querySelectorAll('.af-affiliate-row'));
    var meta = document.getElementById('affiliateSearchMeta');
    var empty = document.getElementById('affiliateSearchEmpty');
    var total = rows.length;

    function applyFilter() {
        var q = (input.value || '').trim().toLowerCase();
        var visible = 0;

        rows.forEach(function (row) {
            var hay = row.getAttribute('data-search') || '';
            var show = q === '' || hay.indexOf(q) !== -1;
            row.classList.toggle('af-row-hidden', !show);
            if (show) visible++;
        });

        if (meta) {
            if (q === '') {
                meta.textContent = total + ' affiliate' + (total === 1 ? '' : 's');
            } else {
                meta.textContent = 'Showing ' + visible + ' of ' + total;
            }
        }

        if (empty) {
            empty.classList.toggle('af-row-hidden', visible > 0 || q === '');
        }
    }

    input.addEventListener('input', applyFilter);
    applyFilter();
})();
</script>

<?php include 'include/footer.php'; ?>
