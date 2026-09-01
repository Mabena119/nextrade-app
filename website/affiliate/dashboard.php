<?php
require dirname(__DIR__) . '/admin/php-includes/security-bridge.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/affiliate.php';
auraai_sec_bootstrap();
auraai_sec_session_start();

$session = auraai_affiliate_require_login();
require dirname(__DIR__) . '/admin/php-includes/connect.php';

$affiliate = auraai_affiliate_assert_portal_access($con, $session['id']);

$stats = auraai_affiliate_dashboard_stats($con, (int) $affiliate['id']);
$balance = auraai_affiliate_balance_info($con, (int) $affiliate['id']);
$payoutMethods = auraai_affiliate_payout_methods($con, (int) $affiliate['id']);
$withdrawals = auraai_affiliate_withdrawals($con, (int) $affiliate['id']);
$refLink = auraai_affiliate_shop_link($affiliate['code']);
$commission = auraai_affiliate_commission_info($con, (int) $affiliate['id']);
$commissionPct = $commission['current_pct'];
$firstName = explode(' ', trim($affiliate['full_name']))[0];
$csrfField = auraai_sec_csrf_field();
$canWithdraw = $balance['available_cents'] >= $balance['min_withdrawal_cents'] && !empty($payoutMethods);
$tierProgress = $commission['at_max']
    ? 100
    : (int) round(min(100, ($commission['confirmed_sales'] / AURAAI_AFFILIATE_SALES_FOR_MAX) * 100));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#020B18">
    <title>Affiliate Dashboard | NexTradeAI</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="../admin/assets/sitelogo.png">
    <?php include __DIR__ . '/include/styles.php'; ?>
</head>
<body>
<div class="page">
    <?php include __DIR__ . '/include/nav.php'; ?>

    <div class="wrap">
        <?php if (isset($_GET['from']) && $_GET['from'] === 'mentor'): ?>
            <div class="alert alert-success">
                <i class="ti ti-layout-dashboard"></i>
                <span>Opened from your mentor dashboard. Use <strong>Mentor Dashboard</strong> in the top bar to return.</span>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['welcome'])): ?>
            <div class="alert alert-success">
                <i class="ti ti-circle-check"></i>
                <span>Your affiliate account is ready. Share your link below to start earning.</span>
            </div>
        <?php endif; ?>
        <?php if (!empty($_GET['payout_saved'])): ?>
            <div class="alert alert-success"><i class="ti ti-circle-check"></i><span>Payout method saved.</span></div>
        <?php endif; ?>
        <?php if (!empty($_GET['payout_deleted'])): ?>
            <div class="alert alert-success"><i class="ti ti-circle-check"></i><span>Payout method removed.</span></div>
        <?php endif; ?>
        <?php if (!empty($_GET['withdraw_requested'])): ?>
            <div class="alert alert-success"><i class="ti ti-circle-check"></i><span>Withdrawal request submitted. We will process it shortly.</span></div>
        <?php endif; ?>
        <?php if (!empty($_GET['payout_error'])): ?>
            <div class="alert alert-danger"><i class="ti ti-alert-circle"></i><span><?php echo auraai_sec_escape(urldecode((string) $_GET['payout_error'])); ?></span></div>
        <?php endif; ?>
        <?php if (!empty($_GET['withdraw_error'])): ?>
            <div class="alert alert-danger"><i class="ti ti-alert-circle"></i><span><?php echo auraai_sec_escape(urldecode((string) $_GET['withdraw_error'])); ?></span></div>
        <?php endif; ?>
        <?php if (strtolower((string) ($affiliate['status'] ?? 'active')) === 'paused'): ?>
            <div class="alert alert-danger">
                <i class="ti ti-alert-triangle"></i>
                <span>Your affiliate account is <strong>suspended</strong>. You can still sign in and view your dashboard, but you will not earn new commissions until your account is reactivated.</span>
            </div>
        <?php endif; ?>

        <section class="dash-hero">
            <div class="dash-hero-main">
                <span class="tagline">Affiliate Dashboard</span>
                <h1>Welcome back, <?php echo auraai_sec_escape($firstName); ?></h1>
                <p class="muted" style="margin-bottom:0;">Track referrals, monitor conversions, and grow your commission earnings with NexTradeAI.</p>
                <div class="dash-hero-meta">
                    <span class="meta-pill"><i class="ti ti-mail"></i> <?php echo auraai_sec_escape($affiliate['email']); ?></span>
                </div>
            </div>
            <div class="commission-badge">
                <div class="rate"><?php echo auraai_sec_escape((string) $commissionPct); ?>%</div>
                <div class="label">Your Commission</div>
                <div class="rate-range"><?php echo (int) $commission['min_pct']; ?>% – <?php echo (int) $commission['max_pct']; ?>%</div>
            </div>
        </section>

        <div class="card ref-card ref-card-primary">
            <h2><i class="ti ti-link"></i> Your Referral Link</h2>
            <p class="muted">Share this link anywhere. When someone joins NexTradeAI through your link and completes membership, you earn commission — confirmed automatically on each successful referral.</p>
            <div class="link-row">
                <div class="link-box" id="refLink"><?php echo auraai_sec_escape($refLink); ?></div>
                <button type="button" class="btn copy-btn" id="copyBtn" onclick="copyRefLink()">
                    <i class="ti ti-copy"></i> Copy Link
                </button>
            </div>
            <div class="steps">
                <div class="step">
                    <span class="step-num">1</span>
                    <div class="step-text"><strong>Share your link</strong>Post on social, send to friends, or add to your bio.</div>
                </div>
                <div class="step">
                    <span class="step-num">2</span>
                    <div class="step-text"><strong>They join NexTradeAI</strong>They visit the site and pay for membership when ready.</div>
                </div>
                <div class="step">
                    <span class="step-num">3</span>
                    <div class="step-text"><strong>You earn commission</strong>Your current <?php echo auraai_sec_escape((string) $commissionPct); ?>% rate applies to every confirmed referral.</div>
                </div>
            </div>
        </div>

        <div class="card tier-card">
            <h2><i class="ti ti-trending-up" style="color:var(--eat-blue);"></i> Commission Tiers</h2>
            <p class="muted" style="margin-bottom:14px;">Start at <?php echo (int) $commission['min_pct']; ?>% and grow to <?php echo (int) $commission['max_pct']; ?>% over your first <?php echo number_format(AURAAI_AFFILIATE_SALES_FOR_MAX); ?> confirmed sales. Your rate increases gradually with each referral.</p>
            <p class="tier-sales-count"><strong><?php echo number_format((int) $commission['confirmed_sales']); ?></strong> / <?php echo number_format(AURAAI_AFFILIATE_SALES_FOR_MAX); ?> confirmed sales</p>
            <div class="tier-track" aria-hidden="true">
                <span class="tier-min"><?php echo (int) $commission['min_pct']; ?>%</span>
                <div class="tier-bar"><div class="tier-fill" style="width:<?php echo $tierProgress; ?>%;"></div></div>
                <span class="tier-max"><?php echo (int) $commission['max_pct']; ?>%</span>
            </div>
            <?php if ($commission['at_max']): ?>
                <p class="tier-note tier-note-max"><i class="ti ti-star"></i> You've reached the maximum <?php echo (int) $commission['max_pct']; ?>% commission rate. Keep sharing your link to earn more.</p>
            <?php else: ?>
                <p class="tier-note"><i class="ti ti-arrow-up"></i> <?php echo (int) $commission['sales_to_next']; ?> more confirmed <?php echo $commission['sales_to_next'] === 1 ? 'sale' : 'sales'; ?> to reach <strong><?php echo auraai_sec_escape((string) $commission['next_pct']); ?>%</strong></p>
            <?php endif; ?>
        </div>

        <div class="stats">
            <div class="stat">
                <div class="stat-icon"><i class="ti ti-wallet"></i></div>
                <div class="stat-label">Available Balance</div>
                <div class="stat-value highlight"><?php echo auraai_sec_escape(auraai_affiliate_format_money((int) $balance['available_cents'])); ?></div>
            </div>
            <div class="stat">
                <div class="stat-icon"><i class="ti ti-clock"></i></div>
                <div class="stat-label">Pending Withdrawals</div>
                <div class="stat-value"><?php echo auraai_sec_escape(auraai_affiliate_format_money((int) $balance['pending_cents'])); ?></div>
            </div>
            <div class="stat">
                <div class="stat-icon"><i class="ti ti-cash"></i></div>
                <div class="stat-label">Total Paid Out</div>
                <div class="stat-value"><?php echo auraai_sec_escape(auraai_affiliate_format_money((int) $balance['paid_out_cents'])); ?></div>
            </div>
            <div class="stat">
                <div class="stat-icon"><i class="ti ti-click"></i></div>
                <div class="stat-label">Link Clicks</div>
                <div class="stat-value"><?php echo number_format((int) $stats['clicks']); ?></div>
            </div>
        </div>

        <div class="payout-grid">
            <div class="card payout-card">
                <h2><i class="ti ti-building-bank" style="color:var(--eat-blue);"></i> Payout Methods</h2>
                <p class="muted">Add where you want to receive funds — Bitcoin, USDT (TRC20), or a bank account.</p>

                <?php if (!empty($payoutMethods)): ?>
                    <ul class="payout-list">
                    <?php foreach ($payoutMethods as $method): ?>
                        <li class="payout-item">
                            <div class="payout-item-main">
                                <span class="payout-type-icon">
                                    <?php if ($method['method_type'] === 'btc'): ?><i class="ti ti-currency-bitcoin"></i>
                                    <?php elseif ($method['method_type'] === 'usdt_trc20'): ?><i class="ti ti-currency-dollar"></i>
                                    <?php else: ?><i class="ti ti-building-bank"></i><?php endif; ?>
                                </span>
                                <div>
                                    <strong><?php echo auraai_sec_escape(auraai_affiliate_payout_method_label($method)); ?></strong>
                                    <?php if (!empty($method['is_default'])): ?><span class="badge badge-confirmed">Default</span><?php endif; ?>
                                    <div class="payout-item-detail"><?php echo auraai_sec_escape(auraai_affiliate_payout_method_summary($method)); ?></div>
                                </div>
                            </div>
                            <form method="post" action="payout-method-delete.php" onsubmit="return confirm('Remove this payout method?');">
                                <?php echo $csrfField; ?>
                                <input type="hidden" name="method_id" value="<?php echo (int) $method['id']; ?>">
                                <button type="submit" class="btn btn-secondary btn-sm" title="Remove"><i class="ti ti-trash"></i></button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="muted empty-inline">No payout methods yet. Add one below to request withdrawals.</p>
                <?php endif; ?>

                <details class="payout-form-wrap">
                    <summary class="btn btn-ghost btn-block" style="margin-top:16px;cursor:pointer;">+ Add Payout Method</summary>
                    <form method="post" action="payout-method.php" class="payout-form" id="payoutForm">
                        <?php echo $csrfField; ?>
                        <div class="form-group">
                            <label for="method_type">Method type</label>
                            <select id="method_type" name="method_type" required onchange="togglePayoutFields()">
                                <option value="btc">Bitcoin (BTC)</option>
                                <option value="usdt_trc20">USDT (TRC20)</option>
                                <option value="bank">Bank Account</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="label">Label (optional)</label>
                            <input id="label" name="label" maxlength="80" placeholder="e.g. My main wallet">
                        </div>
                        <div id="cryptoFields">
                            <div class="form-group">
                                <label for="wallet_address">Wallet address</label>
                                <input id="wallet_address" name="wallet_address" maxlength="128" placeholder="Paste your wallet address">
                            </div>
                        </div>
                        <div id="bankFields" style="display:none;">
                            <div class="form-group"><label for="account_name">Account holder name</label><input id="account_name" name="account_name" maxlength="120"></div>
                            <div class="form-group"><label for="bank_name">Bank name</label><input id="bank_name" name="bank_name" maxlength="120"></div>
                            <div class="form-group"><label for="account_number">Account number</label><input id="account_number" name="account_number" maxlength="64" inputmode="numeric"></div>
                            <div class="form-group"><label for="branch_code">Branch code (optional)</label><input id="branch_code" name="branch_code" maxlength="32"></div>
                        </div>
                        <label class="checkbox-row"><input type="checkbox" name="is_default" value="1" checked> Set as default payout method</label>
                        <button type="submit" class="btn btn-block">Save Payout Method</button>
                    </form>
                </details>
            </div>

            <div class="card payout-card">
                <h2><i class="ti ti-arrow-bar-up" style="color:var(--eat-blue);"></i> Request Withdrawal</h2>
                <p class="muted">Withdraw your available balance to a saved payout method. Minimum <?php echo auraai_sec_escape(auraai_affiliate_format_money((int) $balance['min_withdrawal_cents'])); ?>.</p>

                <?php if (empty($payoutMethods)): ?>
                    <div class="empty-state" style="padding:24px;">
                        <i class="ti ti-wallet-off"></i>
                        <p>Add a payout method first before requesting a withdrawal.</p>
                    </div>
                <?php else: ?>
                    <form method="post" action="request-withdrawal.php" class="payout-form">
                        <?php echo $csrfField; ?>
                        <div class="form-group">
                            <label for="payout_method_id">Pay to</label>
                            <select id="payout_method_id" name="payout_method_id" required>
                                <?php foreach ($payoutMethods as $method): ?>
                                    <option value="<?php echo (int) $method['id']; ?>" <?php echo !empty($method['is_default']) ? 'selected' : ''; ?>>
                                        <?php echo auraai_sec_escape(auraai_affiliate_payout_method_label($method) . ' — ' . auraai_affiliate_payout_method_summary($method)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="amount">Amount (ZAR)</label>
                            <input type="number" id="amount" name="amount" min="<?php echo $balance['min_withdrawal_cents'] / 100; ?>" max="<?php echo max($balance['min_withdrawal_cents'] / 100, $balance['available_cents'] / 100); ?>" step="0.01" placeholder="0.00" <?php echo $canWithdraw ? 'required' : 'disabled'; ?>>
                            <small class="field-hint">Available: <?php echo auraai_sec_escape(auraai_affiliate_format_money((int) $balance['available_cents'])); ?></small>
                        </div>
                        <button type="submit" class="btn btn-block" <?php echo $canWithdraw ? '' : 'disabled'; ?>>
                            <i class="ti ti-send"></i> Request Withdrawal
                        </button>
                        <?php if (!$canWithdraw && !empty($payoutMethods)): ?>
                            <p class="field-hint" style="margin-top:12px;text-align:center;">
                                <?php echo $balance['pending_cents'] > 0 ? 'A withdrawal is already in progress.' : 'Balance below minimum withdrawal amount.'; ?>
                            </p>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($withdrawals)): ?>
        <div class="card">
            <h2><i class="ti ti-history" style="color:var(--eat-blue);"></i> Withdrawal History</h2>
            <div class="table-wrap">
                <table>
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
                        $wStatus = strtolower($w['status']);
                        if ($wStatus === 'paid') {
                            $wClass = 'badge-confirmed';
                        } elseif ($wStatus === 'rejected') {
                            $wClass = 'badge-rejected';
                        } else {
                            $wClass = 'badge-pending';
                        }
                    ?>
                        <tr>
                            <td><?php echo auraai_sec_escape(date('M j, Y · H:i', strtotime($w['requested_at']))); ?></td>
                            <td><?php echo auraai_sec_escape(auraai_affiliate_payout_method_label($w)); ?></td>
                            <td class="amount"><?php echo auraai_sec_escape(auraai_affiliate_format_money((int) $w['amount_cents'])); ?></td>
                            <td><span class="badge <?php echo auraai_sec_escape($wClass); ?>"><?php echo auraai_sec_escape(ucfirst($w['status'])); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <div class="stats stats-secondary">
            <div class="stat">
                <div class="stat-icon"><i class="ti ti-shopping-cart-check"></i></div>
                <div class="stat-label">Confirmed Sales</div>
                <div class="stat-value"><?php echo number_format((int) $stats['conversions']); ?></div>
            </div>
            <div class="stat">
                <div class="stat-icon"><i class="ti ti-coin"></i></div>
                <div class="stat-label">Lifetime Earnings</div>
                <div class="stat-value"><?php echo auraai_sec_escape(auraai_affiliate_format_money((int) $balance['earned_cents'])); ?></div>
            </div>
        </div>

        <div class="card">
            <h2><i class="ti ti-receipt" style="color:var(--eat-blue);"></i> Recent Conversions</h2>
            <?php if (empty($stats['recent'])): ?>
                <div class="empty-state">
                    <i class="ti ti-chart-bar-off"></i>
                    <p>No confirmed member payments yet.<br>Share your referral link to get started.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Member</th>
                                <th>Product</th>
                                <th>Sale</th>
                                <th>Commission</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($stats['recent'] as $row):
                            $statusClass = 'badge-' . strtolower($row['status']);
                        ?>
                            <tr>
                                <td><?php echo auraai_sec_escape(date('M j, Y · H:i', strtotime($row['converted_at']))); ?></td>
                                <td><?php echo auraai_sec_escape($row['email']); ?></td>
                                <td><?php echo auraai_sec_escape(strtoupper($row['product_type'])); ?></td>
                                <td class="amount"><?php echo auraai_sec_escape(auraai_affiliate_format_money((int) $row['amount_cents'])); ?></td>
                                <td class="amount"><?php echo auraai_sec_escape(auraai_affiliate_format_money((int) $row['commission_cents'])); ?></td>
                                <td><span class="badge <?php echo auraai_sec_escape($statusClass); ?>"><?php echo auraai_sec_escape(ucfirst($row['status'])); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <footer class="site-footer">
            &copy; <?php echo date('Y'); ?> <a href="<?php echo htmlspecialchars(NEXTRADE_SITE_URL, ENT_QUOTES, 'UTF-8'); ?>/">NexTradeAI</a> · Affiliate Program
        </footer>
    </div>
</div>
<script>
function copyRefLink() {
    var text = document.getElementById('refLink').textContent.trim();
    var btn = document.getElementById('copyBtn');
    navigator.clipboard.writeText(text).then(function () {
        btn.classList.add('copied');
        btn.innerHTML = '<i class="ti ti-check"></i> Copied!';
        setTimeout(function () {
            btn.classList.remove('copied');
            btn.innerHTML = '<i class="ti ti-copy"></i> Copy Link';
        }, 2000);
    });
}

function togglePayoutFields() {
    var type = document.getElementById('method_type').value;
    var crypto = document.getElementById('cryptoFields');
    var bank = document.getElementById('bankFields');
    var wallet = document.getElementById('wallet_address');
    if (type === 'bank') {
        crypto.style.display = 'none';
        bank.style.display = 'block';
        wallet.removeAttribute('required');
    } else {
        crypto.style.display = 'block';
        bank.style.display = 'none';
        wallet.setAttribute('required', 'required');
    }
}
togglePayoutFields();
</script>
</body>
</html>
