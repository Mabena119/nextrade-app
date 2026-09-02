<?php
/**
 * MT5 / Python bridge — remove active signal when a copied position closes.
 * POST /api/close-signal
 * Body: { "ea_secret": "...", "asset": "EURUSD" }
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/ea-copy-lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    nextrade_api_json(405, ['message' => 'error']);
}

try {
    $body = nextrade_api_read_json();
    $secret = trim((string) ($body['ea_secret'] ?? $body['ea_code'] ?? ''));
    $asset = trim((string) ($body['asset'] ?? ''));

    if ($secret === '' || $asset === '') {
        nextrade_api_json(400, ['message' => 'error', 'error' => 'invalid_payload']);
    }

    $db = nextrade_api_db();
    $ea = nextrade_ea_by_secret($db, $secret);
    if (!$ea) {
        nextrade_api_json(403, ['message' => 'error', 'error' => 'invalid_ea_key']);
    }

    $result = nextrade_close_copy_signal($db, (int) $ea['id'], $asset);
    if (!$result['ok']) {
        nextrade_api_json(404, ['message' => 'error', 'error' => $result['message']]);
    }

    nextrade_api_json(200, ['message' => 'accept']);
} catch (Throwable $e) {
    error_log('[NexTradeAI API] close-signal: ' . $e->getMessage());
    nextrade_api_json(500, ['message' => 'error']);
}
