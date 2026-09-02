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
    header('Location: alicense.php');
    exit;
}

$result = nextrade_reactivate_licence(
    $con,
    $key,
    (int) $session['admin_id'],
    (bool) $session['is_super']
);

if (!$result['ok']) {
    $messages = [
        'not_found' => 'That access code was not found. Check the code and try again.',
        'forbidden' => 'You do not have permission to restore this access code.',
        'db_error' => 'Could not update the licence. Please try again.',
        'missing_key' => 'No access code was provided.',
    ];
    $error = $result['error'] ?? 'db_error';
    nextrade_action_message(
        'Restore failed',
        $messages[$error] ?? $messages['db_error'],
        false,
        'alicense.php',
        'Back to restore form'
    );
    exit;
}

$expiresText = isset($result['expires'])
    ? ' New expiry: ' . date('d M Y', strtotime((string) $result['expires'])) . '.'
    : '';

nextrade_action_message(
    'Licence restored',
    'The access code is active again and can be linked on a new device.' . $expiresText,
    true,
    'key-info.php?key=' . rawurlencode((string) $result['key']),
    'View code details'
);
exit;
