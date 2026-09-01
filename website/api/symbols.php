<?php
require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    nextrade_api_json(405, ['message' => 'error']);
}

try {
    $phoneSecret = trim((string) ($_GET['phone_secret'] ?? ''));
    if ($phoneSecret === '') {
        nextrade_api_json(200, ['message' => 'error']);
    }

    $db = nextrade_api_db();
    $stmt = $db->prepare('SELECT ea, expires FROM licences WHERE phone_secret_code = ? LIMIT 1');
    $stmt->bind_param('s', $phoneSecret);
    $stmt->execute();
    $lic = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$lic) {
        nextrade_api_json(200, ['message' => 'error']);
    }

    if (!empty($lic['expires'])) {
        $expiresAt = strtotime((string) $lic['expires']);
        if ($expiresAt !== false && time() > $expiresAt) {
            $upd = $db->prepare("UPDATE licences SET status = 'Expired' WHERE phone_secret_code = ?");
            $upd->bind_param('s', $phoneSecret);
            $upd->execute();
            $upd->close();
        }
    }

    $eaId = (int) ($lic['ea'] ?? 0);
    $sym = $db->prepare('SELECT id, name FROM symbols WHERE ea = ? ORDER BY name ASC');
    $sym->bind_param('i', $eaId);
    $sym->execute();
    $rows = $sym->get_result()->fetch_all(MYSQLI_ASSOC);
    $sym->close();

    $data = [];
    foreach ($rows as $row) {
        $data[] = ['id' => (string) $row['id'], 'name' => (string) $row['name']];
    }

    nextrade_api_json(200, ['message' => 'accept', 'data' => $data]);
} catch (Throwable $e) {
    error_log('[NexTradeAI API] symbols: ' . $e->getMessage());
    nextrade_api_json(200, ['message' => 'error']);
}
