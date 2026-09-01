<?php
require __DIR__ . '/bootstrap.php';

try {
    $db = nextrade_api_db();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $email = nextrade_api_normalize_email($_GET['email'] ?? '');
        if ($email === null) {
            nextrade_api_json(200, ['scanner' => false]);
        }

        $stmt = $db->prepare('SELECT scanner FROM members WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        nextrade_api_json(200, ['scanner' => (bool) ((int) ($row['scanner'] ?? 0))]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = nextrade_api_read_json();
        $email = nextrade_api_normalize_email($body['email'] ?? '');
        if ($email === null) {
            nextrade_api_json(400, ['error' => 'Email required']);
        }

        $stmt = $db->prepare('UPDATE members SET scanner = 0 WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $updated = $stmt->affected_rows > 0;
        $stmt->close();

        nextrade_api_json(200, ['ok' => true, 'updated' => $updated]);
    }

    nextrade_api_json(405, ['error' => 'Method not allowed']);
} catch (Throwable $e) {
    error_log('[NexTradeAI API] scanner-status: ' . $e->getMessage());
    nextrade_api_json(200, ['scanner' => false]);
}
