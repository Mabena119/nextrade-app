<?php
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    nextrade_api_json(405, ['error' => 'Method not allowed']);
}

try {
    $eaId = trim((string) ($_GET['eaId'] ?? ''));

    if ($eaId === '') {
        nextrade_api_json(400, ['error' => 'EA ID required']);
    }

    $db = nextrade_api_db();
    $stmt = $db->prepare(
        'SELECT id, ea, asset, latestupdate, action, price, tp, sl, time, lot, results, type
         FROM `signals`
         WHERE ea = ? AND LOWER(COALESCE(results, "")) IN ("active", "pending")
         ORDER BY latestupdate DESC
         LIMIT 1'
    );
    $stmt->bind_param('s', $eaId);
    $stmt->execute();
    $result = $stmt->get_result();
    $signal = $result->fetch_assoc() ?: null;
    $stmt->close();

    nextrade_api_json(200, ['signal' => $signal]);
} catch (Throwable $e) {
    error_log('[NexTradeAI API] get-active-signal: ' . $e->getMessage());
    nextrade_api_json(500, ['error' => 'Database error', 'message' => $e->getMessage()]);
}
