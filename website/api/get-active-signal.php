<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/ea-copy-lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    nextrade_api_json(405, ['error' => 'Method not allowed']);
}

try {
    $eaId = trim((string) ($_GET['eaId'] ?? ''));

    if ($eaId === '') {
        nextrade_api_json(400, ['error' => 'EA ID required']);
    }

    $db = nextrade_api_db();
    nextrade_purge_expired_copy_signals($db);
    $openWhere = nextrade_signal_open_where_sql('results', 'time');

    $stmt = $db->prepare(
        "SELECT id, ea, asset, latestupdate, action, price, tp, sl, time, lot, results, type
         FROM `signals`
         WHERE ea = ? AND {$openWhere}
         ORDER BY latestupdate DESC
         LIMIT 1"
    );
    $stmt->bind_param('s', $eaId);
    $stmt->execute();
    $result = $stmt->get_result();
    $signal = $result->fetch_assoc() ?: null;
    $stmt->close();

    if (is_array($signal) && !nextrade_signal_is_executable($signal)) {
        $signal = null;
    }

    if (is_array($signal)) {
        $signal = nextrade_normalize_signal_row_timestamps($signal);
    }

    nextrade_api_json(200, ['signal' => $signal]);
} catch (Throwable $e) {
    error_log('[NexTradeAI API] get-active-signal: ' . $e->getMessage());
    nextrade_api_json(500, ['error' => 'Database error', 'message' => $e->getMessage()]);
}
