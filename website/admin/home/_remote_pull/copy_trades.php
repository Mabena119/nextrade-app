<?php
include("include/header.php");
require("../php-includes/connect.php");

$admin_id = get_admin($_SESSION['username'], "id");
$owner_id = (int) $admin_id;
$copy_trades_preset_ea = isset($_GET['ea_id']) ? (int) $_GET['ea_id'] : 0;
?>

<style>
.copy-trades-container { padding: 24px; }
.signal-card {
    background: var(--card-bg, #2a2e38);
    border-radius: 12px;
    padding: 28px;
    margin-bottom: 24px;
    border: 1px solid rgba(255,255,255,0.06);
}
.form-row-flex {
    display: flex;
    gap: 16px;
    align-items: flex-end;
    flex-wrap: wrap;
}
.form-row-top { margin-bottom: 16px; }
.form-row-submit { margin-top: 20px; }
.form-col { flex: 1; min-width: 140px; }
.form-col-wide { flex: 2; min-width: 200px; }
.form-col-narrow { flex: 0 0 140px; }
.lot-martingale-field { flex: 0 0 160px; border: 1px solid rgba(255, 193, 7, 0.35); border-radius: 10px; padding: 12px; background: rgba(255, 193, 7, 0.08); }
.form-col label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    font-size: 13px;
    color: rgba(255,255,255,0.9);
}
.form-col select,
.form-col input {
    width: 100%;
    padding: 12px 14px;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.1);
    background: rgba(0,0,0,0.2);
    color: #fff;
    font-size: 14px;
    transition: border-color 0.2s;
}
.form-col select:focus,
.form-col input:focus {
    outline: none;
    border-color: #9a55ff;
}
.btn-create {
    padding: 12px 28px;
    background: linear-gradient(135deg, #9a55ff 0%, #7c3aed 100%);
    border: none;
    border-radius: 8px;
    color: #fff;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    white-space: nowrap;
    transition: transform 0.2s, box-shadow 0.2s;
}
.btn-create:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(154,85,255,0.4);
}
/* Active Signals Section */
.signals-section {
    margin-top: 32px;
    padding-top: 24px;
    border-top: 1px solid rgba(255,255,255,0.08);
}
.signals-header {
    margin-bottom: 16px;
}
.signals-header h5 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: rgba(255,255,255,0.9);
}
.signals-table {
    width: 100%;
    overflow-x: auto;
    border-radius: 8px;
    overflow: hidden;
    background: rgba(0,0,0,0.2);
}
.signals-table table { width: 100%; border-collapse: collapse; }
.signals-table th {
    background: rgba(0,0,0,0.35);
    padding: 12px 16px;
    text-align: left;
    font-weight: 600;
    font-size: 11px;
    color: rgba(255,255,255,0.6);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.signals-table td {
    padding: 14px 16px;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    font-size: 13px;
    vertical-align: middle;
}
.signals-table tbody tr:last-child td { border-bottom: none; }
.signals-table tbody tr:hover td { background: rgba(255,255,255,0.02); }
.signals-table .ea-name { font-weight: 600; color: rgba(255,255,255,0.95); }
.signals-table .col-tp { color: #1bc5bd; font-weight: 600; }
.signals-table .col-sl { color: #f64e60; font-weight: 600; }
.signals-table .col-date { color: rgba(255,255,255,0.5); font-size: 12px; }
.signals-empty { background: rgba(0,0,0,0.2) !important; }
.badge-symbol { background: #3699ff; padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; display: inline-block; }
.badge-buy { background: #1bc5bd; color: #fff; padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; display: inline-block; }
.badge-sell { background: #f64e60; color: #fff; padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; display: inline-block; }
.badge-active { background: rgba(27,197,189,0.25); color: #1bc5bd; padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; display: inline-block; }
.badge-result-closed { background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.65); padding: 5px 12px; border-radius: 6px; font-size: 11px; font-weight: 600; display: inline-block; text-transform: capitalize; }
.badge-result-other { background: rgba(154,85,255,0.18); color: #c4b5fd; padding: 5px 12px; border-radius: 6px; font-size: 11px; font-weight: 600; display: inline-block; text-transform: capitalize; }
.btn-close-signal {
    background: #f64e60;
    border: none;
    color: #fff;
    padding: 6px 16px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.2s, transform 0.2s;
}
.btn-close-signal:hover { opacity: 0.9; transform: translateY(-1px); }
.header-flex {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 28px;
}
.header-flex h3 { font-size: 1.5rem; font-weight: 600; }
.live-badge {
    background: linear-gradient(135deg, #1bc5bd 0%, #0d9488 100%);
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
</style>

<div class="copy-trades-container">
        
        <?php if (isset($_GET['success'])): ?>
            <?php $succ = preg_replace('/[^a-z_]/', '', strtolower((string) ($_GET['success'] ?? ''))); ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php if ($succ === 'trade_removed'): ?>
                <strong><i class="ti ti-circle-check"></i> Removed.</strong> The trade signal was deleted from the database.
                <?php else: ?>
                <strong><i class="ti ti-circle-check"></i> Success!</strong> Signal created successfully.
                <?php endif; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
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
                'invalid_lot' => 'Lot size is required for martingale bots (must be greater than 0).',
            ];
            $err_msg = isset($err_human[$err_code])
                ? $err_human[$err_code]
                : 'Something went wrong.';
            ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong><i class="ti ti-alert-triangle"></i> Error!</strong> <?php echo htmlspecialchars($err_msg, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        
        <div class="signal-card">
            <div class="header-flex">
                <h3 style="margin: 0;"><i class="ti ti-copy"></i> Copy Trades</h3>
                <span class="live-badge"><i class="ti ti-activity"></i> Live</span>
            </div>
            
            <!-- Create Signal Form -->
            <form method="POST" action="create_signal.php">
                <div class="form-row-flex form-row-top">
                    <div class="form-col form-col-wide">
                        <label><i class="ti ti-robot text-info"></i> Expert Advisor</label>
                        <select name="ea_id" id="ea_id" required>
                            <option value="">Select Expert Advisor</option>
                            <?php
                            $ea_query = "SELECT id, name, martingale FROM eas WHERE owner = ".$owner_id;
                            $ea_result = mysqli_query($con, $ea_query);
                            while($ea = mysqli_fetch_assoc($ea_result)) {
                                $eid = (int) $ea['id'];
                                $sel = ($copy_trades_preset_ea === $eid) ? ' selected' : '';
                                $mg = (int) ($ea['martingale'] ?? 0);
                                echo "<option value='{$eid}' data-martingale='{$mg}'{$sel}>" . htmlspecialchars($ea['name'], ENT_QUOTES, 'UTF-8') . ($mg === 1 ? ' (Martingale)' : '') . "</option>";
                            }
                            ?>
                        </select>
                        <small id="lot-martingale-hint" style="display:none;margin-top:8px;font-size:11px;color:rgba(255,193,7,0.85);">Martingale EA selected — enter lot size below before creating the signal.</small>
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
                            <option value="">Select EA first</option>
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
                        <small style="display:block;margin-top:6px;font-size:11px;color:rgba(255,255,255,0.45);">Required for martingale bots — sent to clients with the signal.</small>
                    </div>
                </div>
                
                <div class="form-row-submit">
                    <button type="submit" class="btn-create">
                        <i class="ti ti-rocket"></i> Create Signal
                    </button>
                </div>
            </form>
            
            <!-- Active Signals Section -->
            <div class="signals-section">
                <div class="signals-header">
                    <h5><i class="ti ti-chart-line text-info"></i> All trades (your EAs)</h5>
                    <p style="margin: 6px 0 0; font-size: 13px; color: rgba(255,255,255,0.45); font-weight: 400;">Every signal recorded for Expert Advisors on this account.</p>
                </div>
                
                <div class="signals-table">
                <table>
                    <thead>
                        <tr>
                            <th>EA NAME</th>
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
            symbolSelect.innerHTML = '<option value="">Select EA first</option>';
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
