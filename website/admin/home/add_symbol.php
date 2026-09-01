<?php
session_start();
require __DIR__ . '/../php-includes/connect.php';
require __DIR__ . '/../php-includes/functions.php';

function symbol_redirect(string $path): void
{
    header('Location: ' . $path);
    exit();
}

if (!isset($_SESSION['id'], $_SESSION['username'])) {
    symbol_redirect('../index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    symbol_redirect('EA.php');
}

$ownerId = (int) get_admin($_SESSION['username'], 'id');
$eaId = (int) ($_POST['ea'] ?? 0);
$returnUrl = trim((string) ($_POST['returnUrl'] ?? ''));
$rawName = trim((string) ($_POST['name'] ?? ''));
$name = strtoupper(preg_replace('/[^A-Za-z0-9._#-]/', '', $rawName));

$safeReturn = $eaId > 0 ? 'EA.php?ea=' . $eaId : 'EA.php';
if ($returnUrl !== '' && preg_match('/^EA\.php(\?ea=\d+)?$/', $returnUrl)) {
    $safeReturn = $returnUrl;
}

if ($ownerId <= 0 || $eaId <= 0 || $name === '' || strlen($name) > 32) {
    symbol_redirect($safeReturn . (str_contains($safeReturn, '?') ? '&' : '?') . 'symbol_error=invalid');
}

$eaCheck = getea($eaId, $ownerId, 'id');
if ($eaCheck === 'Invalid Key' || $eaCheck === '' || $eaCheck === null) {
    symbol_redirect('EA.php?symbol_error=forbidden');
}

$dup = $con->prepare('SELECT id FROM symbols WHERE ea = ? AND UPPER(name) = ? LIMIT 1');
$dup->bind_param('is', $eaId, $name);
$dup->execute();
$exists = $dup->get_result()->fetch_assoc();
$dup->close();

if ($exists) {
    symbol_redirect($safeReturn . (str_contains($safeReturn, '?') ? '&' : '?') . 'symbol_error=duplicate');
}

$stmt = $con->prepare('INSERT INTO symbols (name, ea) VALUES (?, ?)');
$stmt->bind_param('si', $name, $eaId);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    error_log('[NexTradeAI add_symbol] insert failed: ' . $con->error);
    symbol_redirect($safeReturn . (str_contains($safeReturn, '?') ? '&' : '?') . 'symbol_error=db');
}

symbol_redirect($safeReturn . (str_contains($safeReturn, '?') ? '&' : '?') . 'symbol_added=1');
