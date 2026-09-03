<?php
require dirname(__DIR__) . '/php-includes/security-bridge.php';
require __DIR__ . '/include/copy-trades-action.php';
auraai_sec_bootstrap();
auraai_sec_session_start();
require dirname(__DIR__) . '/php-includes/connect.php';
require dirname(__DIR__) . '/php-includes/functions.php';
require dirname(__DIR__) . '/../api/ea-copy-lib.php';

if (!isset($_SESSION['id'], $_SESSION['username'])) {
    header('Location: ../index.php');
    exit();
}

copy_trades_require_post();

auraai_sec_require_same_origin();

$ownerId = (int) get_admin($_SESSION['username'], 'id');
$eaId = auraai_sec_int($_POST['ea_id'] ?? null, 1);
$symbol = auraai_sec_string($_POST['symbol'] ?? '', 32, 1);
$tradeType = auraai_sec_enum($_POST['trade_type'] ?? '', ['buy', 'sell']);
$takeProfit = nextrade_normalize_optional_level($_POST['take_profit'] ?? null);
$stopLoss = nextrade_normalize_optional_level($_POST['stop_loss'] ?? null);
$lotRaw = trim((string) ($_POST['lot'] ?? ''));

if ($ownerId <= 0 || $eaId === null || $symbol === null || $tradeType === null) {
    copy_trades_finish(false, '?error=missing_fields');
}

$stmt = $con->prepare('SELECT id, martingale FROM eas WHERE id = ? AND owner = ? LIMIT 1');
$stmt->bind_param('ii', $eaId, $ownerId);
$stmt->execute();
$eaRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$eaRow) {
    copy_trades_finish(false, '?error=invalid_signal&ea_id=' . $eaId);
}

$isMartingale = (int) ($eaRow['martingale'] ?? 0) === 1;
$lot = '';
if ($isMartingale) {
    if ($lotRaw === '' || !is_numeric($lotRaw) || (float) $lotRaw <= 0) {
        copy_trades_finish(false, '?error=invalid_lot&ea_id=' . $eaId);
    }
    $lot = (string) $lotRaw;
} elseif ($lotRaw !== '' && is_numeric($lotRaw) && (float) $lotRaw > 0) {
    $lot = (string) $lotRaw;
}

$price = '0';
$signalType = 'all';
$results = 'active';
$now = gmdate('Y-m-d H:i:s');

$insert = $con->prepare(
    'INSERT INTO signals (ea, asset, type, action, price, tp, sl, lot, results, time, latestupdate)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$insert->bind_param(
    'issssssssss',
    $eaId,
    $symbol,
    $signalType,
    $tradeType,
    $price,
    $takeProfit,
    $stopLoss,
    $lot,
    $results,
    $now,
    $now
);

if (!$insert->execute()) {
    error_log('[create_signal] insert failed: ' . mysqli_error($con));
    copy_trades_finish(false, '?error=database_error&ea_id=' . $eaId);
}

$insert->close();
copy_trades_finish(true, '?success=signal_created');
