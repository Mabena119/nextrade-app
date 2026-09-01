<?php
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    nextrade_api_json(405, ['error' => 'Method not allowed']);
}

try {
    $eaId = trim((string) ($_GET['eaId'] ?? ''));
    $since = trim((string) ($_GET['since'] ?? ''));

    if ($eaId === '') {
        nextrade_api_json(400, ['error' => 'EA ID required']);
    }

    $db = nextrade_api_db();
    $signals = [];

    if ($since !== '') {
        $mysqlSince = $since;
        $ts = strtotime($since);
        if ($ts !== false) {
            $mysqlSince = gmdate('Y-m-d H:i:s', $ts);
        }

        $stmt = $db->prepare(
            'SELECT id, ea, asset, latestupdate, action, price, tp, sl, time, lot
             FROM `signals`
             WHERE ea = ? AND latestupdate > ?
             ORDER BY latestupdate DESC
             LIMIT 50'
        );
        $stmt->bind_param('ss', $eaId, $mysqlSince);
    } else {
        $stmt = $db->prepare(
            'SELECT id, ea, asset, latestupdate, action, price, tp, sl, time, lot
             FROM `signals`
             WHERE ea = ?
             ORDER BY latestupdate DESC
             LIMIT 50'
        );
        $stmt->bind_param('s', $eaId);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $signals[] = $row;
    }
    $stmt->close();

    nextrade_api_json(200, ['signals' => $signals]);
} catch (Throwable $e) {
    error_log('[NexTradeAI API] get-new-signals: ' . $e->getMessage());
    nextrade_api_json(500, ['error' => 'Database error', 'message' => $e->getMessage()]);
}
