<?php
/**
 * MT5 / Python bridge — validate EA secret code (eas.secret_code).
 * GET /api/ea-auth?key=YOUR_EA_SECRET
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/ea-copy-lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    nextrade_api_json(405, ['message' => 'error']);
}

try {
    $key = trim((string) ($_GET['key'] ?? ''));
    if ($key === '') {
        nextrade_api_json(203, ['message' => 'error', 'error' => 'missing_key']);
    }

    $db = nextrade_api_db();
    $ea = nextrade_ea_by_secret($db, $key);
    if (!$ea) {
        nextrade_api_json(203, ['message' => 'error', 'error' => 'invalid_ea_key']);
    }

    $martingale = (int) ($ea['martingale'] ?? 0) === 1;

    nextrade_api_json(200, [
        'message' => 'accept',
        'ea_id' => (int) $ea['id'],
        'ea_name' => (string) ($ea['name'] ?? ''),
        'martingale' => $martingale ? 1 : 0,
        'ea_martingale' => $martingale,
    ]);
} catch (Throwable $e) {
    error_log('[NexTradeAI API] ea-auth: ' . $e->getMessage());
    nextrade_api_json(500, ['message' => 'error']);
}
