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
    $stmt = $db->prepare(
        'SELECT l.ea AS ea_id, e.name AS ea_name,
                a.displayname AS owner_name, a.image AS owner_logo
         FROM licences l
         LEFT JOIN eas e ON e.id = l.ea
         LEFT JOIN admin a ON a.id = e.owner
         WHERE UPPER(REPLACE(l.k_ey, \'-\', \'\')) = UPPER(REPLACE(?, \'-\', \'\'))
         LIMIT 1'
    );
    $stmt->bind_param('s', $licenseKey);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $eaId = $row ? $row['ea_id'] : null;
    nextrade_api_json(200, [
        'id' => $eaId,
        'eaId' => $eaId,
        'ea_name' => $row['ea_name'] ?? null,
        'owner' => $row
            ? [
                'name' => (string) ($row['owner_name'] ?? ''),
                'logo' => (string) ($row['owner_logo'] ?? ''),
            ]
            : null,
    ]);
} catch (Throwable $e) {
    error_log('[NexTradeAI API] get-ea-from-license: ' . $e->getMessage());
    nextrade_api_json(500, ['error' => 'Database error']);
}
