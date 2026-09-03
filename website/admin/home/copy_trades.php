<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/security.php';
auraai_sec_bootstrap();
include("include/header.php");
require("../php-includes/connect.php");
require dirname(__DIR__, 2) . '/api/ea-copy-lib.php';

$admin_id = get_admin($_SESSION['username'], "id");
$owner_id = (int) $admin_id;
$copy_trades_preset_ea = isset($_GET['ea_id']) ? (int) $_GET['ea_id'] : 0;

nextrade_purge_expired_copy_signals($con);

$activeWindowSql = nextrade_signal_open_where_sql('s.results', 's.time');
$ct_ea_count = 0;
$ct_total_signals = 0;
$ct_active_signals = 0;
$ct_ea_res = mysqli_query($con, "SELECT COUNT(*) AS c FROM eas WHERE owner = " . $owner_id);
if ($ct_ea_res && ($ct_ea_row = mysqli_fetch_assoc($ct_ea_res))) {
    $ct_ea_count = (int) ($ct_ea_row['c'] ?? 0);
}
$ct_sig_res = mysqli_query(
    $con,
    "SELECT COUNT(*) AS total,
            SUM(CASE WHEN {$activeWindowSql} THEN 1 ELSE 0 END) AS active_cnt
     FROM signals s
     INNER JOIN eas e ON s.ea = e.id
     WHERE e.owner = " . $owner_id
);
if ($ct_sig_res && ($ct_sig_row = mysqli_fetch_assoc($ct_sig_res))) {
    $ct_total_signals = (int) ($ct_sig_row['total'] ?? 0);
    $ct_active_signals = (int) ($ct_sig_row['active_cnt'] ?? 0);
}

$signals_query = "SELECT s.*, e.name AS ea_name, e.martingale AS ea_martingale
                  FROM signals s
                  INNER JOIN eas e ON s.ea = e.id
                  WHERE e.owner = " . $owner_id . "
                  ORDER BY s.time DESC
                  LIMIT 100";
$signals_result = mysqli_query($con, $signals_query);
?>

<div class="aura-console-page ct-page">

    <?php if (isset($_GET['success'])): ?>
        <?php $succ = preg_replace('/[^a-z_]/', '', strtolower((string) ($_GET['success'] ?? ''))); ?>
        <div class="ct-flash ct-flash--success" role="status">
            <i class="ti ti-circle-check" aria-hidden="true"></i>
            <span><?php echo $succ === 'trade_removed' ? 'Signal removed — clients will no longer see this trade.' : 'Signal published — clients with automation running will receive it.'; ?></span>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <?php
        $err_code = preg_replace('/[^a-z_]/', '', strtolower((string) ($_GET['error'] ?? '')));
        $err_human = [
            'invalid_signal' => 'This trade does not belong to your account or is invalid.',
            'delete_failed' => 'The database could not remove this trade.',
            'missing_signal' => 'That trade may have already been deleted.',
            'close_failed' => 'Could not close the trade.',
            'invalid_lot' => 'Lot size is required for copy trading (must be greater than 0).',
            'database_error' => 'Could not save the signal. Please try again.',
            'missing_fields' => 'Please complete all required fields.',
        ];
        $err_msg = isset($err_human[$err_code]) ? $err_human[$err_code] : 'Something went wrong.';
        ?>
        <div class="ct-flash ct-flash--error" role="alert">
            <i class="ti ti-alert-triangle" aria-hidden="true"></i>
            <span><?php echo htmlspecialchars($err_msg, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
    <?php endif; ?>

    <header class="ct-hero">
        <div class="ct-hero__copy">
            <p class="aura-kicker">Copy trades</p>
            <h1>Publish live signals</h1>
            <p>Send buy and sell instructions to every client running your automations. Active signals execute immediately when members start their bots.</p>
        </div>
        <div class="ct-hero__badge" aria-label="Live publishing">
            <span class="ct-live-dot" aria-hidden="true"></span>
            Live
        </div>
    </header>

    <div class="ct-metrics">
        <div class="ct-metric">
            <span class="ct-metric__label">Active now</span>
            <strong class="ct-metric__value ct-metric__value--live"><?php echo (int) $ct_active_signals; ?></strong>
            <span class="ct-metric__hint">Open copy signals</span>
        </div>
        <div class="ct-metric">
            <span class="ct-metric__label">All signals</span>
            <strong class="ct-metric__value"><?php echo (int) $ct_total_signals; ?></strong>
            <span class="ct-metric__hint">Recorded on your account</span>
        </div>
        <div class="ct-metric">
            <span class="ct-metric__label">Automations</span>
            <strong class="ct-metric__value"><?php echo (int) $ct_ea_count; ?></strong>
            <span class="ct-metric__hint">EAs you can publish to</span>
        </div>
    </div>

    <div class="ct-layout">
        <section class="ct-panel ct-panel--publish aura-panel">
            <div class="ct-panel__head">
                <div>
                    <h2><i class="ti ti-rocket" aria-hidden="true"></i> New signal</h2>
                    <p>Choose automation, levels, and direction — then publish to all linked clients.</p>
                </div>
            </div>

            <form method="POST" action="create_signal.php" class="ct-form" id="ctPublishForm" data-ct-action="publish">
                <div class="ct-form__section">
                    <p class="ct-form__section-label">Automation</p>
                    <div class="ct-field ct-field--full">
                        <label for="ea_id"><i class="ti ti-robot" aria-hidden="true"></i> Target automation</label>
                        <select name="ea_id" id="ea_id" required>
                            <option value="">Select automation</option>
                            <?php
                            $ea_query = "SELECT id, name, martingale FROM eas WHERE owner = " . $owner_id;
                            $ea_result = mysqli_query($con, $ea_query);
                            while ($ea = mysqli_fetch_assoc($ea_result)) {
                                $eid = (int) $ea['id'];
                                $sel = ($copy_trades_preset_ea === $eid) ? ' selected' : '';
                                $mg = (int) ($ea['martingale'] ?? 0);
                                $mg_label = $mg === 1 ? ' · Copy trading' : '';
                                echo '<option value="' . $eid . '" data-martingale="' . $mg . '"' . $sel . '>'
                                    . htmlspecialchars($ea['name'], ENT_QUOTES, 'UTF-8') . $mg_label
                                    . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <p class="ct-form__hint" id="lot-martingale-hint" hidden>
                        <i class="ti ti-info-circle" aria-hidden="true"></i>
                        Copy trading selected — lot size is required and sent with the signal.
                    </p>
                </div>

                <div class="ct-form__section">
                    <p class="ct-form__section-label">Trade setup</p>
                    <div class="ct-form__grid ct-form__grid--3">
                        <div class="ct-field">
                            <label for="symbol"><i class="ti ti-chart-line" aria-hidden="true"></i> Symbol</label>
                            <select name="symbol" id="symbol" required disabled>
                                <option value="">Select automation first</option>
                            </select>
                        </div>
                        <div class="ct-field">
                            <label for="trade_type"><i class="ti ti-arrows-left-right" aria-hidden="true"></i> Direction</label>
                            <select name="trade_type" id="trade_type" required>
                                <option value="">Select</option>
                                <option value="buy">Buy</option>
                                <option value="sell">Sell</option>
                            </select>
                        </div>
                        <div class="ct-field ct-field--lot" id="lot_field_wrap" hidden>
                            <label for="lot"><i class="ti ti-scale" aria-hidden="true"></i> Lot size</label>
                            <input type="number" name="lot" id="lot" value="" placeholder="e.g. 0.01" step="0.00001" min="0.00001">
                        </div>
                    </div>
                </div>

                <div class="ct-form__section">
                    <p class="ct-form__section-label">Risk levels <span style="color:var(--aura-muted);font-weight:400;">(optional)</span></p>
                    <div class="ct-form__grid ct-form__grid--2">
                        <div class="ct-field ct-field--tp">
                            <label for="take_profit"><i class="ti ti-trending-up" aria-hidden="true"></i> Take profit</label>
                            <input type="number" name="take_profit" id="take_profit" value="" placeholder="Leave blank for none" step="0.1" min="0">
                        </div>
                        <div class="ct-field ct-field--sl">
                            <label for="stop_loss"><i class="ti ti-shield" aria-hidden="true"></i> Stop loss</label>
                            <input type="number" name="stop_loss" id="stop_loss" value="" placeholder="Leave blank for none" step="0.1" min="0">
                        </div>
                    </div>
                </div>

                <div class="ct-form__actions">
                    <button type="submit" class="ct-btn-publish">
                        <i class="ti ti-bolt" aria-hidden="true"></i>
                        Publish signal
                    </button>
                </div>
            </form>
        </section>

        <aside class="ct-panel ct-panel--guide aura-panel">
            <div class="ct-panel__head">
                <div>
                    <h2><i class="ti ti-bulb" aria-hidden="true"></i> How it works</h2>
                    <p>Signals reach the NexTradeAI app in seconds.</p>
                </div>
            </div>
            <ol class="ct-steps">
                <li>
                    <span class="ct-steps__num">1</span>
                    <div>
                        <strong>Publish</strong>
                        <p>Create a signal for one of your automations and symbols on Quotes.</p>
                    </div>
                </li>
                <li>
                    <span class="ct-steps__num">2</span>
                    <div>
                        <strong>Clients receive</strong>
                        <p>Members with automation running get the trade instantly via the app.</p>
                    </div>
                </li>
                <li>
                    <span class="ct-steps__num">3</span>
                    <div>
                        <strong>Close when done</strong>
                        <p>Remove active signals when the trade is finished so bots stop copying it.</p>
                    </div>
                </li>
            </ol>
            <div class="ct-guide-note">
                <i class="ti ti-clock" aria-hidden="true"></i>
                <span>Signals stay active for <?php echo (int) NEXTRADE_SIGNAL_ACTIVE_MINUTES; ?> minutes, then go inactive for another <?php echo (int) (NEXTRADE_SIGNAL_TTL_MINUTES - NEXTRADE_SIGNAL_ACTIVE_MINUTES); ?> minutes before they are removed automatically.</span>
            </div>
        </aside>
    </div>

    <section class="ct-panel ct-panel--history aura-panel">
        <div class="ct-panel__head ct-panel__head--split">
            <div>
                <h2><i class="ti ti-list" aria-hidden="true"></i> Signal history</h2>
                <p>All copy-trade signals for automations on your account.</p>
            </div>
            <div class="ct-filter-tabs" role="tablist" aria-label="Filter signals">
                <button type="button" class="ct-filter-tab is-active" data-filter="all" role="tab" aria-selected="true">All</button>
                <button type="button" class="ct-filter-tab" data-filter="active" role="tab" aria-selected="false">Active</button>
            </div>
        </div>

        <?php if (!$signals_result): ?>
            <div class="ct-empty ct-empty--error">
                <i class="ti ti-alert-circle" aria-hidden="true"></i>
                <p>Unable to load trades right now. Please refresh the page.</p>
            </div>
        <?php elseif (mysqli_num_rows($signals_result) === 0): ?>
            <div class="ct-empty">
                <i class="ti ti-chart-dots" aria-hidden="true"></i>
                <p>No trade signals yet</p>
                <span>Use the form above to publish your first copy signal.</span>
            </div>
        <?php else: ?>
            <div class="ct-signals-grid" id="ctSignalsGrid">
                <?php
                while ($signal = mysqli_fetch_assoc($signals_result)) {
                    $trade_badge_class = (isset($signal['action']) && $signal['action'] === 'buy') ? 'badge-buy' : 'badge-sell';
                    $act = isset($signal['action']) ? strtoupper(htmlspecialchars((string) $signal['action'], ENT_QUOTES, 'UTF-8')) : '—';
                    $ea_nm = htmlspecialchars((string) ($signal['ea_name'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $sym = htmlspecialchars((string) ($signal['asset'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $tp_disp = htmlspecialchars((string) ($signal['tp'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $sl_disp = htmlspecialchars((string) ($signal['sl'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $lot_disp = isset($signal['lot']) && trim((string) $signal['lot']) !== ''
                        ? htmlspecialchars((string) $signal['lot'], ENT_QUOTES, 'UTF-8')
                        : '—';
                    $result_raw = nextrade_signal_effective_status($signal);
                    $result_label = htmlspecialchars($result_raw, ENT_QUOTES, 'UTF-8');
                    $is_active = ($result_raw === 'active');
                    $status_class = $is_active ? 'badge-active' : ($result_raw === 'inactive' ? 'badge-result-closed' : 'badge-result-other');
                    $signal_id_esc = (int) ($signal['id'] ?? 0);
                    $created = isset($signal['time']) ? date('M j, Y · H:i', strtotime($signal['time'])) : '—';
                    $filter_state = $is_active ? 'active' : 'other';
                    ?>
                    <article class="ct-signal-card<?php echo $is_active ? ' ct-signal-card--live' : ''; ?>" data-status="<?php echo htmlspecialchars($filter_state, ENT_QUOTES, 'UTF-8'); ?>">
                        <header class="ct-signal-card__top">
                            <div class="ct-signal-card__ea">
                                <i class="ti ti-cpu" aria-hidden="true"></i>
                                <?php echo $ea_nm; ?>
                            </div>
                            <span class="<?php echo htmlspecialchars($status_class, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php if ($is_active): ?><span class="ct-live-dot" aria-hidden="true"></span><?php endif; ?>
                                <?php echo $result_label; ?>
                            </span>
                        </header>

                        <div class="ct-signal-card__main">
                            <span class="badge-symbol"><?php echo $sym; ?></span>
                            <span class="<?php echo $trade_badge_class; ?>"><?php echo $act; ?></span>
                        </div>

                        <dl class="ct-signal-card__meta">
                            <div>
                                <dt>Take profit</dt>
                                <dd class="ct-val-tp"><?php echo $tp_disp; ?></dd>
                            </div>
                            <div>
                                <dt>Stop loss</dt>
                                <dd class="ct-val-sl"><?php echo $sl_disp; ?></dd>
                            </div>
                            <div>
                                <dt>Lot</dt>
                                <dd><?php echo $lot_disp; ?></dd>
                            </div>
                            <div>
                                <dt>Created</dt>
                                <dd class="ct-val-date"><?php echo htmlspecialchars($created, ENT_QUOTES, 'UTF-8'); ?></dd>
                            </div>
                        </dl>

                        <footer class="ct-signal-card__foot">
                            <?php if ($is_active): ?>
                                <form method="POST" action="close_signal.php" class="ct-close-form" data-ct-action="close">
                                    <input type="hidden" name="signal_id" value="<?php echo $signal_id_esc; ?>">
                                    <button type="submit" class="btn-close-signal">
                                        <i class="ti ti-x" aria-hidden="true"></i> Close trade
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="ct-signal-card__closed-note"><?php echo $result_raw === 'inactive' ? 'Inactive — auto-removed after 20 min' : 'No action — signal closed'; ?></span>
                            <?php endif; ?>
                        </footer>
                    </article>
                    <?php
                }
                ?>
            </div>
            <p class="ct-signals-footnote" id="ctFilterEmpty" hidden>No active signals right now.</p>
        <?php endif; ?>
    </section>
</div>

<script>
(function () {
    function updateLotFieldForEa() {
        var eaSelect = document.getElementById('ea_id');
        var lotWrap = document.getElementById('lot_field_wrap');
        var lotInput = document.getElementById('lot');
        var lotHint = document.getElementById('lot-martingale-hint');
        if (!eaSelect || !lotWrap || !lotInput) return;
        var opt = eaSelect.options[eaSelect.selectedIndex];
        var isMartingale = opt && opt.getAttribute('data-martingale') === '1';
        lotWrap.hidden = !isMartingale;
        lotInput.required = isMartingale;
        if (!isMartingale) lotInput.value = '';
        if (lotHint) lotHint.hidden = !isMartingale;
    }

    var eaSelect = document.getElementById('ea_id');
    if (eaSelect) {
        eaSelect.addEventListener('change', function () {
            updateLotFieldForEa();
            var eaId = this.value;
            var symbolSelect = document.getElementById('symbol');
            if (!symbolSelect) return;

            if (!eaId) {
                symbolSelect.innerHTML = '<option value="">Select automation first</option>';
                symbolSelect.disabled = true;
                return;
            }

            symbolSelect.innerHTML = '<option value="">Loading symbols…</option>';
            symbolSelect.disabled = true;

            fetch('get_ea_symbols.php?ea_id=' + encodeURIComponent(eaId))
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.success && data.symbols && data.symbols.length) {
                        symbolSelect.innerHTML = '<option value="">Select symbol</option>';
                        data.symbols.forEach(function (symbol) {
                            var opt = document.createElement('option');
                            opt.value = symbol.name;
                            opt.textContent = symbol.name;
                            symbolSelect.appendChild(opt);
                        });
                        symbolSelect.disabled = false;
                    } else {
                        symbolSelect.innerHTML = '<option value="">No symbols on Quotes</option>';
                    }
                })
                .catch(function () {
                    symbolSelect.innerHTML = '<option value="">Error loading symbols</option>';
                });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        updateLotFieldForEa();
        if (eaSelect && eaSelect.value) {
            eaSelect.dispatchEvent(new Event('change'));
        }
    });

    var tabs = document.querySelectorAll('.ct-filter-tab');
    var grid = document.getElementById('ctSignalsGrid');
    var emptyNote = document.getElementById('ctFilterEmpty');
    if (tabs.length && grid) {
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var filter = tab.getAttribute('data-filter');
                tabs.forEach(function (t) {
                    t.classList.toggle('is-active', t === tab);
                    t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
                });
                var cards = grid.querySelectorAll('.ct-signal-card');
                var visible = 0;
                cards.forEach(function (card) {
                    var show = filter === 'all' || card.getAttribute('data-status') === 'active';
                    card.style.display = show ? '' : 'none';
                    if (show) visible += 1;
                });
                if (emptyNote) {
                    emptyNote.hidden = !(filter === 'active' && visible === 0);
                }
            });
        });
    }

    function submitCopyTradesForm(form, confirmMessage) {
        if (confirmMessage && !window.confirm(confirmMessage)) {
            return;
        }
        var submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
        }
        fetch(form.getAttribute('action') || 'copy_trades.php', {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'fetch'
            }
        })
            .then(function (response) {
                return response.json().then(function (payload) {
                    return { response: response, payload: payload };
                }).catch(function () {
                    return { response: response, payload: null };
                });
            })
            .then(function (result) {
                var payload = result.payload;
                if (payload && payload.redirect) {
                    window.location.replace(payload.redirect);
                    return;
                }
                if (result.response.ok) {
                    window.location.replace('copy_trades.php');
                    return;
                }
                throw new Error('request_failed');
            })
            .catch(function () {
                alert('Something went wrong. Please refresh the page and try again.');
                if (submitBtn) submitBtn.disabled = false;
            });
    }

    var publishForm = document.getElementById('ctPublishForm');
    if (publishForm) {
        publishForm.addEventListener('submit', function (event) {
            event.preventDefault();
            submitCopyTradesForm(publishForm, null);
        });
    }

    document.querySelectorAll('.ct-close-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            submitCopyTradesForm(form, 'Delete this signal from the database? Clients will stop seeing this trade.');
        });
    });
})();
</script>

<?php include("include/footer.php"); ?>
