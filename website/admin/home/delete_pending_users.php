<?php
require dirname(__DIR__) . '/php-includes/security-bridge.php';
auraai_sec_bootstrap();
auraai_sec_session_start();
require dirname(__DIR__) . '/php-includes/connect.php';
require dirname(__DIR__) . '/php-includes/functions.php';

if (!isset($_SESSION['id']) || !get_admin($_SESSION['username'], 'super')) {
    header('Location: users.php?error=unauthorized');
    exit();
}

auraai_sec_require_method('POST');
auraai_sec_require_same_origin();

if (empty($_POST['confirm_delete_pending'])) {
    header('Location: users.php?error=delete_failed');
    exit();
}

$stmt = $con->prepare("DELETE FROM admin WHERE status = 'pending' AND (super IS NULL OR super = 0 OR super = '')");
if (!$stmt) {
    error_log('[delete_pending_users] prepare failed: ' . mysqli_error($con));
    header('Location: users.php?error=delete_failed');
    exit();
}

$ok = $stmt->execute();
$deleted = $ok ? $stmt->affected_rows : 0;
$stmt->close();

if (!$ok) {
    error_log('[delete_pending_users] execute failed: ' . mysqli_error($con));
    header('Location: users.php?error=delete_failed');
    exit();
}

$noun = $deleted === 1 ? 'user' : 'users';
header('Location: users.php?success=' . rawurlencode("Deleted $deleted pending $noun."));
exit();
