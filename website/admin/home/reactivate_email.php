<?php
require_once __DIR__ . '/../php-includes/connect.php';
require_once __DIR__ . '/../php-includes/functions.php';
require_once __DIR__ . '/include/licence-actions.php';

$session = nextrade_admin_session();
if (!$session['ok']) {
    header('Location: ' . $session['redirect']);
    exit;
}

if (!(bool) $session['is_super']) {
    header('Location: aemail.php');
    exit;
}

$email = isset($_GET['email']) ? trim((string) $_GET['email']) : '';
if ($email === '') {
    header('Location: aemail.php');
    exit;
}

$result = nextrade_reactivate_member_email($con, $email);

if (!$result['ok']) {
    $messages = [
        'not_found' => 'That email address was not found. Check the address and try again.',
        'invalid_email' => 'Enter a valid email address and try again.',
        'db_error' => 'Could not update the member record. Please try again.',
    ];
    $error = $result['error'] ?? 'db_error';
    nextrade_action_message(
        'Restore failed',
        $messages[$error] ?? $messages['db_error'],
        false,
        'aemail.php',
        'Back to restore form'
    );
    exit;
}

nextrade_action_message(
    'Email restored',
    'The member can sign into the app again with ' . $result['email'] . '.',
    true,
    'aemail.php',
    'Back to restore form'
);
exit;
