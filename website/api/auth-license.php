<?php
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    nextrade_api_json(200, ['ok' => true]);
}

try {
    $body = nextrade_api_read_json();
    $licence = nextrade_api_normalize_license($body['licence'] ?? $body['license'] ?? '');
    $phoneSecret = trim((string) ($body['phone_secret'] ?? ''));

    if ($licence === '') {
        nextrade_api_json(200, ['message' => 'error']);
    }

    $db = nextrade_api_db();
    $stmt = $db->prepare(
        'SELECT l.k_ey AS lic_key, l.user AS lic_user, l.status AS lic_status, l.expires AS lic_expires,
                l.phone_secret_code AS lic_phone_secret_code, l.ea AS ea_id,
                e.name AS ea_name, e.notification_key AS ea_notification, e.martingale AS ea_martingale,
                e.owner AS owner_id, a.displayname AS owner_name, a.email AS owner_email,
                a.phone AS owner_phone, a.image AS owner_logo
         FROM licences l
         LEFT JOIN eas e ON e.id = l.ea
         LEFT JOIN admin a ON a.id = e.owner
         WHERE UPPER(REPLACE(l.k_ey, \'-\', \'\')) = ?
         LIMIT 1'
    );
    $stmt->bind_param('s', $licence);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        nextrade_api_json(200, ['message' => 'error']);
    }

    $canonicalKey = (string) ($row['lic_key'] ?? $licence);
    $rawStored = $row['lic_phone_secret_code'] ?? null;
    $stored = is_string($rawStored) ? trim($rawStored) : '';
    $isUnset = $stored === '' || strcasecmp($stored, 'none') === 0;

    if ($isUnset) {
        $generated = bin2hex(random_bytes(16));
        $upd = $db->prepare('UPDATE licences SET phone_secret_code = ? WHERE k_ey = ?');
        $upd->bind_param('ss', $generated, $canonicalKey);
        $upd->execute();
        $upd->close();
        $effectiveSecret = $generated;
    } else {
        if ($phoneSecret === '' || $phoneSecret !== $stored) {
            nextrade_api_json(200, ['message' => 'used']);
        }
        $effectiveSecret = $stored;
    }

    nextrade_api_json(200, [
        'message' => 'accept',
        'data' => [
            'user' => (string) ($row['lic_user'] ?? ''),
            'status' => (string) ($row['lic_status'] ?? 'active'),
            'expires' => (string) ($row['lic_expires'] ?? ''),
            'key' => $canonicalKey,
            'phone_secret_key' => $effectiveSecret,
            'ea_name' => (string) ($row['ea_name'] ?? 'NexTradeAI'),
            'ea_notification' => (string) ($row['ea_notification'] ?? ''),
            'ea_martingale' => (bool) ((int) ($row['ea_martingale'] ?? 0)),
            'owner' => [
                'name' => (string) ($row['owner_name'] ?? 'NexTradeAI'),
                'email' => (string) ($row['owner_email'] ?? ''),
                'phone' => (string) ($row['owner_phone'] ?? ''),
                'logo' => (string) ($row['owner_logo'] ?? ''),
            ],
        ],
    ]);
} catch (Throwable $e) {
    error_log('[NexTradeAI API] auth-license: ' . $e->getMessage());
    nextrade_api_json(200, ['message' => 'error', 'degraded' => 1]);
}
