<?php
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    nextrade_api_json(405, ['error' => 'Method not allowed']);
}

try {
    $licenseKey = trim((string) ($_GET['licenseKey'] ?? ''));
    if ($licenseKey === '') {
        nextrade_api_json(400, ['error' => 'License key required']);
    }

    $db = nextrade_api_db();
    $stmt = $db->prepare('SELECT ea FROM licences WHERE k_ey = ? LIMIT 1');
    $stmt->bind_param('s', $licenseKey);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $eaId = $row ? $row['ea'] : null;
    nextrade_api_json(200, ['id' => $eaId, 'eaId' => $eaId]);
} catch (Throwable $e) {
    error_log('[NexTradeAI API] get-ea-from-license: ' . $e->getMessage());
    nextrade_api_json(500, ['error' => 'Database error']);
}
