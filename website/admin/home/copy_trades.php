<?php
include("include/header.php");
require("../php-includes/connect.php");

$admin_id = get_admin($_SESSION['username'], "id");
$owner_id = (int) $admin_id;
$copy_trades_preset_ea = isset($_GET['ea_id']) ? (int) $_GET['ea_id'] : 0;
?>

<div class="aura-console-page copy-trades-container">
        
        <?php if (isset($_GET['success'])): ?>
            <?php $succ = preg_replace('/[^a-z_]/', '', strtolower((string) ($_GET['success'] ?? ''))); ?>
            <div class="aura-alert aura-alert-success" style="margin-bottom:1rem;">
                <?php if ($succ === 'trade_removed'): ?>Signal removed from the database.
                <?php else: ?>Signal published successfully.<?php endif; ?>
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
            ];
            $err_msg = isset($err_human[$err_code]) ? $err_human[$err_code] : 'Something went wrong.';
            ?>
            <div class="aura-alert aura-alert-danger" style="margin-bottom:1rem;"><?php echo htmlspecialchars($err_msg, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <header class="aura-console-head">
            <div>
                <p class="aura-kicker">Signal sync</p>
                <h1>Publish trades</h1>
                <p>Send live signals to clients running your automations.</p>
            </div>
            <span class="aura-chip"><i class="ti ti-activity"></i> Live</span>
        </header>
        
        <div class="signal-card aura-panel">
            <!-- Publish signal Form -->
            <form method="POST" action="create_signal.php">
                <div class="form-row-flex form-row-top">
                    <div class="form-col form-col-wide">
                        <label><i class="ti ti-robot text-info"></i> Automation</label>
                        <select name="ea_id" id="ea_id" required>
                            <option value="">Select automation</option>
                            <?php
                            $ea_query = "SELECT id, name, martingale FROM eas WHERE owner = ".$owner_id;
                            $ea_result = mysqli_query($con, $ea_query);
                            while($ea = mysqli_fetch_assoc($ea_result)) {
                                $eid = (int) $ea['id'];
                                $sel = ($copy_trades_preset_ea === $eid) ? ' selected' : '';
                                $mg = (int) ($ea['martingale'] ?? 0);
                                echo "<option value='{$eid}' data-martingale='{$mg}'{$sel}>" . htmlspecialchars($ea['name'], ENT_QUOTES, 'UTF-8') . ($mg === 1 ? ' (Copy trading)' : '') . "</option>";
                            }
                            ?>
                        </select>
                        <small id="lot-martingale-hint" style="display:none;margin-top:8px;font-size:11px;color:rgba(255,193,7,0.85);">Copy trading selected — enter lot size below before creating the signal.</small>
                    </div>
                    
                    <div class="form-col form-col-narrow">
                        <label><i class="ti ti-target text-success"></i> Take Profit (pips)</label>
                        <input type="number" name="take_profit" id="take_profit" value="0" placeholder="0" step="0.1" required>
                    </div>
                    
                    <div class="form-col form-col-narrow">
                        <label><i class="ti ti-shield text-danger"></i> Stop Loss (pips)</label>
                        <input type="number" name="stop_loss" id="stop_loss" value="0" placeholder="0" step="0.1" required>
                    </div>
                </div>
                
                <div class="form-row-flex">
                    <div class="form-col">
                        <label><i class="ti ti-chart-line text-success"></i> Symbol</label>
                        <select name="symbol" id="symbol" required disabled>
                            <option value="">Select automation first</option>
                        </select>
                    </div>
                    
                    <div class="form-col form-col-narrow">
                        <label><i class="ti ti-arrow-up text-warning"></i> Direction</label>
                        <select name="trade_type" id="trade_type" required>
                            <option value="">Select</option>
                            <option value="buy">Buy</option>
                            <option value="sell">Sell</option>
                        </select>
                    </div>

                    <div class="form-col form-col-narrow lot-martingale-field" id="lot_field_wrap" style="display:none;">
                        <label><i class="ti ti-scale text-warning"></i> Lot size</label>
                        <input type="number" name="lot" id="lot" value="" placeholder="e.g. 0.01" step="0.00001" min="0.00001">
                        <small style="display:block;margin-top:6px;font-size:11px;color:rgba(255,255,255,0.45);">Required for copy trading — sent to clients with the signal.</small>
                    </div>
                </div>
                
                <div class="form-row-submit">
                    <button type="submit" class="btn-create">
                        <i class="ti ti-rocket"></i> Publish signal
                    </button>
                </div>
            </form>
            
            <!-- Live signals Section -->
            <div class="signals-section">
                <div class="signals-header">
                    <h5><i class="ti ti-chart-line text-info"></i> All trades (your automations)</h5>
                    <p style="margin: 6px 0 0; font-size: 13px; color: rgba(255,255,255,0.45); font-weight: 400;">Every signal recorded for automations on this account.</p>
                </div>
                
                <div class="signals-table">
                <table>
                    <thead>
                        <tr>
                            <th>AUTOMATION</th>
                            <th>SYMBOL</th>
                            <th>TYPE</th>
                            <th>TP (PIPS)</th>
                            <th>SL (PIPS)</th>
                            <th>LOT</th>
                            <th>STATUS</th>
                            <th>CREATED</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $signals_query = "SELECT s.*, e.name AS ea_name 
                                         FROM signals s 
                                         INNER JOIN eas e ON s.ea = e.id 
                                         WHERE e.owner = ".$owner_id."
                                         ORDER BY s.time DESC";
                        $signals_result = mysqli_query($con, $signals_query);

                        if (!$signals_result) {
                            echo "<tr><td colspan='9' class='signals-empty'><p style='padding:24px;color:rgba(246,78,96,0.9);'>Unable to load trades right now.</p></td></tr>";
                        } elseif (mysqli_num_rows($signals_result) > 0) {
                            while ($signal = mysqli_fetch_assoc($signals_result)) {
                                $trade_badge_class = (isset($signal['action']) && $signal['action'] === 'buy') ? 'badge-buy' : 'badge-sell';
                                $act = isset($signal['action']) ? strtoupper(htmlspecialchars((string) $signal['action'], ENT_QUOTES, 'UTF-8')) : '—';
                                $ea_nm = htmlspecialchars((string) ($signal['ea_name'] ?? ''), ENT_QUOTES, 'UTF-8');
                                $sym = htmlspecialchars((string) ($signal['asset'] ?? ''), ENT_QUOTES, 'UTF-8');
                                $tp_disp = htmlspecialchars((string) ($signal['tp'] ?? ''), ENT_QUOTES, 'UTF-8');
                                $sl_disp = htmlspecialchars((string) ($signal['sl'] ?? ''), ENT_QUOTES, 'UTF-8');
$lot_disp = isset($signal['lot']) && trim((string) $signal['lot']) !== '' ? htmlspecialchars((string) $signal['lot'], ENT_QUOTES, 'UTF-8') : '—';
                                $result_raw = isset($signal['results']) ? strtolower(trim((string) $signal['results'])) : '';
                                $result_label = htmlspecialchars($result_raw !== '' ? $result_raw : 'unknown', ENT_QUOTES, 'UTF-8');
                                $is_active = ($result_raw === 'active');
                                $status_class = $is_active ? 'badge-active' : ($result_raw === 'closed' ? 'badge-result-closed' : 'badge-result-other');
                                $signal_id_esc = (int) ($signal['id'] ?? 0);
                                $created = isset($signal['time']) ? date('M d, H:i', strtotime($signal['time'])) : '—';
                                echo "<tr>";
                                echo "<td class='ea-name'>" . $ea_nm . "</td>";
                                echo "<td><span class='badge-symbol'>" . $sym . "</span></td>";
                                echo "<td><span class='" . $trade_badge_class . "'>" . $act . "</span></td>";
                                echo "<td class='col-tp'>" . $tp_disp . "</td>";
                                echo "<td class='col-sl'>" . $sl_disp . "</td>";
                                echo "<td>" . $lot_disp . "</td>";
                                echo "<td><span class='" . htmlspecialchars($status_class, ENT_QUOTES, 'UTF-8') . "'>" . $result_label . "</span></td>";
                                echo "<td class='col-date'>" . htmlspecialchars($created, ENT_QUOTES, 'UTF-8') . "</td>";
                                echo "<td>";
                                if ($is_active) {
                                    echo "<form method='POST' action='close_signal.php' style='display:inline;' onsubmit='return confirm(\"Delete this signal from the database? Clients will stop seeing this trade.\");'>";
                                    echo "<input type='hidden' name='signal_id' value='" . $signal_id_esc . "'>";
                                    echo "<button type='submit' class='btn-close-signal'>Close trade</button>";
                                    echo "</form>";
                                } else {
                                    echo "<span style='font-size: 12px; color: rgba(255,255,255,0.35);'>—</span>";
                                }
                                echo "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='9' class='signals-empty'>
                                    <div style='text-align:center; padding: 48px 24px;'>
                                        <i class='ti ti-chart-dots' style='font-size: 48px; color: rgba(255,255,255,0.2);'></i>
                                        <p style='margin: 16px 0 0; color: rgba(255,255,255,0.5); font-size: 15px;'>No trade signals yet</p>
                                        <p style='margin: 4px 0 0; color: rgba(255,255,255,0.35); font-size: 13px;'>Create one with the form above</p>
                                    </div>
                                  </td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            </div>
        </div>
        
    </div>
    
    <script>
    // Load symbols when EA is selected
    function updateLotFieldForEa() {
        const eaSelect = document.getElementById('ea_id');
        const lotWrap = document.getElementById('lot_field_wrap');
        const lotInput = document.getElementById('lot');
        const lotHint = document.getElementById('lot-martingale-hint');
        if (!eaSelect || !lotWrap || !lotInput) return;
        const opt = eaSelect.options[eaSelect.selectedIndex];
        const isMartingale = opt && opt.getAttribute('data-martingale') === '1';
        lotWrap.style.display = isMartingale ? 'block' : 'none';
        lotInput.required = isMartingale;
        if (!isMartingale) lotInput.value = '';
        if (lotHint) lotHint.style.display = isMartingale ? 'block' : 'none';
    }

    document.getElementById('ea_id').addEventListener('change', function() {
        updateLotFieldForEa();
        const eaId = this.value;
        const symbolSelect = document.getElementById('symbol');
        
        if(!eaId) {
            symbolSelect.innerHTML = '<option value="">Select automation first</option>';
            symbolSelect.disabled = true;
            return;
        }
        
        symbolSelect.innerHTML = '<option value="">Loading...</option>';
        symbolSelect.disabled = true;
        
        fetch('get_ea_symbols.php?ea_id=' + eaId)
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    symbolSelect.innerHTML = '<option value="">Select symbol</option>';
                    data.symbols.forEach(symbol => {
                        symbolSelect.innerHTML += `<option value="${symbol.name}">${symbol.name}</option>`;
                    });
                    symbolSelect.disabled = false;
                } else {
                    symbolSelect.innerHTML = '<option value="">No symbols found</option>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                symbolSelect.innerHTML = '<option value="">Error loading</option>';
            });
    });

    document.addEventListener('DOMContentLoaded', updateLotFieldForEa);
    (function preloadEaSymbols() {
        var sel = document.getElementById('ea_id');
        if (!sel || !sel.value) return;
        sel.dispatchEvent(new Event('change'));
        updateLotFieldForEa();
    })();
    </script>
    
<?php include("include/footer.php"); ?>
