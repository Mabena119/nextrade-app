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

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['close'])) {
    symbol_redirect('EA.php');
}

$ownerId = (int) get_admin($_SESSION['username'], 'id');
$eaId = (int) ($_POST['ea'] ?? 0);
$symbolId = (int) ($_POST['id'] ?? 0);
$returnUrl = trim((string) ($_POST['returnUrl'] ?? ''));

$safeReturn = $eaId > 0 ? 'EA.php?ea=' . $eaId : 'EA.php';
if ($returnUrl !== '' && preg_match('/^EA\.php(\?ea=\d+)?$/', $returnUrl)) {
    $safeReturn = $returnUrl;
}

if ($ownerId <= 0 || $eaId <= 0 || $symbolId <= 0) {
    symbol_redirect($safeReturn . (str_contains($safeReturn, '?') ? '&' : '?') . 'symbol_error=invalid');
}

$eaCheck = getea($eaId, $ownerId, 'id');
if ($eaCheck === 'Invalid Key' || $eaCheck === '' || $eaCheck === null) {
    symbol_redirect('EA.php?symbol_error=forbidden');
}

$stmt = $con->prepare('DELETE FROM symbols WHERE id = ? AND ea = ? LIMIT 1');
$stmt->bind_param('ii', $symbolId, $eaId);
$stmt->execute();
$stmt->close();

symbol_redirect($safeReturn);
