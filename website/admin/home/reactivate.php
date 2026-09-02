<?php
require_once __DIR__ . '/../php-includes/connect.php';
require_once __DIR__ . '/../php-includes/functions.php';
require_once __DIR__ . '/include/licence-actions.php';

$session = nextrade_admin_session();
if (!$session['ok']) {
    header('Location: ' . $session['redirect']);
    exit;
}

$key = isset($_GET['key']) ? trim((string) $_GET['key']) : '';
if ($key === '') {
    header('Location: stats.php');
    exit;
}

$result = nextrade_reactivate_licence(
    $con,
    $key,
    (int) $session['admin_id'],
    (bool) $session['is_super']
);

if (!$result['ok']) {
    header('Location: stats.php');
    exit;
}

header('Location: key-info.php?key=' . rawurlencode((string) $result['key']));
exit;
