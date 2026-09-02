<?php
/**
 * MT5 / Python bridge — publish a copy-trade signal into `signals`.
 * POST /api/post-signal
 * Body: { "ea_secret": "...", "signal": { "asset", "action", "price", "tp?", "sl?", "lot?" } }
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/ea-copy-lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    nextrade_api_json(405, ['message' => 'error']);
}

try {
    $body = nextrade_api_read_json();
    $secret = trim((string) ($body['ea_secret'] ?? $body['ea_code'] ?? ''));
    $signal = $body['signal'] ?? null;

    if ($secret === '' || !is_array($signal)) {
        nextrade_api_json(400, ['message' => 'error', 'error' => 'invalid_payload']);
    }

    $db = nextrade_api_db();
    $ea = nextrade_ea_by_secret($db, $secret);
    if (!$ea) {
        nextrade_api_json(403, ['message' => 'error', 'error' => 'invalid_ea_key']);
    }

    $eaId = (int) $ea['id'];
    $isMartingale = (int) ($ea['martingale'] ?? 0) === 1;
    $result = nextrade_insert_copy_signal($db, $eaId, $isMartingale, $signal);

    if (!$result['ok']) {
        $code = match ($result['message']) {
            'symbol_not_allowed' => 422,
            'lot_required' => 422,
            'invalid_action' => 400,
            default => 500,
        };
        nextrade_api_json($code, ['message' => 'error', 'error' => $result['message']]);
    }

    nextrade_api_json(200, [
        'message' => 'accept',
        'signal_id' => $result['signal_id'] ?? null,
    ]);
} catch (Throwable $e) {
    error_log('[NexTradeAI API] post-signal: ' . $e->getMessage());
    nextrade_api_json(500, ['message' => 'error']);
}
