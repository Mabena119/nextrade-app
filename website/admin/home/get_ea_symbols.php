<?php
session_start();
require __DIR__ . '/../php-includes/connect.php';
require __DIR__ . '/../php-includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id'], $_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$ownerId = (int) get_admin($_SESSION['username'], 'id');
$eaId = (int) ($_GET['ea_id'] ?? 0);

if ($ownerId <= 0 || $eaId <= 0) {
    echo json_encode(['success' => false, 'symbols' => []]);
    exit();
}

$eaCheck = getea($eaId, $ownerId, 'id');
if ($eaCheck === 'Invalid Key' || $eaCheck === '' || $eaCheck === null) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit();
}

$stmt = $con->prepare('SELECT id, name FROM symbols WHERE ea = ? ORDER BY name ASC');
$stmt->bind_param('i', $eaId);
$stmt->execute();
$result = $stmt->get_result();
$symbols = [];
while ($row = $result->fetch_assoc()) {
    $symbols[] = [
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
    ];
}
$stmt->close();

echo json_encode(['success' => true, 'symbols' => $symbols]);
