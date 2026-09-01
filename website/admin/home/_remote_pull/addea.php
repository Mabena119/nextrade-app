<?php
session_start();
require __DIR__ . '/../php-includes/connect.php';
require __DIR__ . '/../php-includes/functions.php';

if (!isset($_SESSION['id']) || !isset($_SESSION['username'])) {
    header('Location: ../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['ea'])) {
    header('Location: EA.php');
    exit();
}

$eaName = trim((string) $_POST['ea']);
$owner = get_admin($_SESSION['username'], 'id');

if ($eaName === '' || strlen($eaName) > 50) {
    header('Location: EA.php?error=invalid_name');
    exit();
}

if (empty($owner) || !is_numeric($owner)) {
    header('Location: EA.php?error=invalid_owner');
    exit();
}

$ownerId = (int) $owner;
$martingale = (isset($_POST['martingale']) && $_POST['martingale'] === '1') ? 1 : 0;

$check = $con->prepare('SELECT id, owner FROM eas WHERE name = ? LIMIT 1');
$check->bind_param('s', $eaName);
$check->execute();
$existing = $check->get_result()->fetch_assoc();
$check->close();

if ($existing) {
    if ((int) ($existing['owner'] ?? 0) === $ownerId) {
        header('Location: EA.php?ea=' . (int) $existing['id'] . '&notice=existing_ea');
        exit();
    }

    header('Location: EA.php?error=duplicate_name');
    exit();
}

$code = md5(uniqid((string) random_int(0, PHP_INT_MAX), true));
$notification = md5(uniqid((string) random_int(0, PHP_INT_MAX), true));

$stmt = $con->prepare(
    'INSERT INTO eas (owner, name, secret_code, notification_key, martingale) VALUES (?, ?, ?, ?, ?)'
);
$stmt->bind_param('isssi', $ownerId, $eaName, $code, $notification, $martingale);

try {
    $ok = $stmt->execute();
} catch (mysqli_sql_exception $e) {
    error_log('[NexTradeAI addea] Insert failed: ' . $e->getMessage());
    if ((int) $e->getCode() === 1062) {
        header('Location: EA.php?error=duplicate_name');
        exit();
    }
    header('Location: EA.php?error=db_error');
    exit();
}

$stmt->close();

if ($ok) {
    header('Location: EA.php');
    exit();
}

header('Location: EA.php?error=db_error');
exit();
