<?php
/**
 * POST /api/warroom
 *
 * Mint a lifetime KILLZONE 3 licence for an email, send the key by email, return the key.
 *
 * Headers:
 *   x-warroom-secret: <WARROOM_API_SECRET from ~/nextradeai-secrets.php>
 *   Content-Type: application/json
 *
 * Body: { "email": "client@example.com" }
 *
 * Response 200:
 *   { "ok": true, "email": "...", "key": "ABC-...", "ea_name": "KILLZONE 3",
 *     "plan": "lifetime", "created": true, "email_sent": true }
 */
require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/warroom-lib.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    nextrade_api_json(200, [
        'ok' => true,
        'service' => 'warroom',
        'ea' => nextrade_warroom_ea_name(),
        'plan' => 'lifetime',
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    nextrade_api_json(405, ['ok' => false, 'error' => 'Method not allowed']);
}

if (!nextrade_warroom_verify_secret()) {
    nextrade_api_json(401, ['ok' => false, 'error' => 'Unauthorized']);
}

require_once dirname(__DIR__) . '/includes/security.php';
auraai_sec_rate_limit_or_exit('warroom_issue', 30, 3600);

try {
    $body = nextrade_api_read_json();
    $email = nextrade_api_normalize_email($body['email'] ?? '');
    if ($email === null) {
        nextrade_api_json(400, ['ok' => false, 'error' => 'Valid email is required']);
    }

    $db = nextrade_api_db();
    $result = nextrade_warroom_issue_key($db, $email);
    if (!$result['ok']) {
        nextrade_api_json(502, ['ok' => false, 'error' => $result['error'] ?? 'Failed to issue key']);
    }

    $payload = [
        'ok' => true,
        'email' => $email,
        'key' => $result['key'],
        'ea_name' => $result['ea_name'] ?? nextrade_warroom_ea_name(),
        'plan' => 'lifetime',
        'created' => (bool) ($result['created'] ?? false),
        'email_sent' => (bool) ($result['email_sent'] ?? false),
    ];

    if (!$payload['email_sent']) {
        nextrade_api_json(502, array_merge($payload, [
            'ok' => false,
            'error' => 'Key created but email could not be sent',
        ]));
    }

    nextrade_api_json(200, $payload);
} catch (Throwable $e) {
    error_log('[NexTradeAI API] warroom: ' . $e->getMessage());
    nextrade_api_json(500, ['ok' => false, 'error' => 'Server error']);
}
