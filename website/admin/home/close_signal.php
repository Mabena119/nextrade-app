<?php
require dirname(__DIR__) . '/php-includes/security-bridge.php';
require __DIR__ . '/include/copy-trades-action.php';
auraai_sec_bootstrap();
auraai_sec_session_start();
require dirname(__DIR__) . '/php-includes/connect.php';
require dirname(__DIR__) . '/php-includes/functions.php';

if (!isset($_SESSION['id'], $_SESSION['username'])) {
    header('Location: ../index.php');
    exit();
}

copy_trades_require_post();

auraai_sec_require_same_origin();

$ownerId = (int) get_admin($_SESSION['username'], 'id');
$signalId = auraai_sec_int($_POST['signal_id'] ?? null, 1);

if ($ownerId <= 0 || $signalId === null) {
    copy_trades_finish(false, '?error=invalid_signal');
}

$verify = $con->prepare(
    'SELECT s.id
     FROM signals s
     INNER JOIN eas e ON s.ea = e.id
     WHERE s.id = ? AND e.owner = ?
     LIMIT 1'
);
$verify->bind_param('ii', $signalId, $ownerId);
$verify->execute();
$owned = $verify->get_result()->fetch_assoc();
$verify->close();

if (!$owned) {
    copy_trades_finish(false, '?error=invalid_signal');
}

$delete = $con->prepare('DELETE FROM signals WHERE id = ? LIMIT 1');
$delete->bind_param('i', $signalId);
$delete->execute();
$affected = $delete->affected_rows;
$delete->close();

if ($affected < 1) {
    copy_trades_finish(false, '?error=missing_signal');
}

copy_trades_finish(true, '?success=trade_removed');
