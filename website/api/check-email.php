<?php
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    nextrade_api_json(200, ['ok' => true]);
}

try {
    $body = nextrade_api_read_json();
    $email = nextrade_api_normalize_email($body['email'] ?? '');
    if ($email === null) {
        nextrade_api_json(400, ['error' => 'Email is required']);
    }

    $db = nextrade_api_db();
    $stmt = $db->prepare('SELECT id, email, paid, used FROM members WHERE LOWER(email) = LOWER(?) LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$result) {
        nextrade_api_json(200, ['found' => 0, 'used' => 0, 'paid' => 0, 'invalidMentor' => 0]);
    }

    $used = (int) ($result['used'] ?? 0);
    $paid = (int) ($result['paid'] ?? 0);

    if ($used === 0) {
        $upd = $db->prepare('UPDATE members SET used = 1 WHERE LOWER(email) = LOWER(?)');
        $upd->bind_param('s', $email);
        $upd->execute();
        $upd->close();
        $used = 0;
    }

    nextrade_api_json(200, [
        'found' => 1,
        'used' => $used,
        'paid' => $paid,
        'invalidMentor' => 0,
    ]);
} catch (Throwable $e) {
    error_log('[NexTradeAI API] check-email: ' . $e->getMessage());
    nextrade_api_json(200, ['found' => 0, 'used' => 0, 'paid' => 0, 'invalidMentor' => 0, 'degraded' => 1]);
}
