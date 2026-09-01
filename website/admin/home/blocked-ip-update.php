<?php
require __DIR__ . '/include/require_super.php';
require dirname(__DIR__) . '/php-includes/security-bridge.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/ip-block.php';
auraai_sec_bootstrap();
auraai_sec_require_method('POST');

require dirname(__DIR__) . '/php-includes/connect.php';

$adminId = (int) get_admin($_SESSION['username'], 'id');
$action = auraai_sec_enum($_POST['action'] ?? '', ['add', 'remove']);

if ($action === 'add') {
    $ip = (string) ($_POST['ip_address'] ?? '');
    $reason = (string) ($_POST['reason'] ?? '');
    $expiresDays = auraai_sec_int($_POST['expires_days'] ?? '', 1, 3650);
    $expiresAt = null;
    if ($expiresDays !== null) {
        $expiresAt = date('Y-m-d H:i:s', time() + ($expiresDays * 86400));
    }

    $result = auraai_ip_block_add($con, $ip, $reason, $adminId, $expiresAt);
    if (!$result['ok']) {
        header('Location: blocked-ips.php?error=' . rawurlencode($result['error']));
        exit;
    }
    header('Location: blocked-ips.php?added=1');
    exit;
}

if ($action === 'remove') {
    $id = auraai_sec_int($_POST['id'] ?? 0, 1, 999999999);
    if ($id === null) {
        header('Location: blocked-ips.php?error=' . rawurlencode('Invalid block id.'));
        exit;
    }
    $result = auraai_ip_block_remove($con, $id);
    if (!$result['ok']) {
        header('Location: blocked-ips.php?error=' . rawurlencode($result['error']));
        exit;
    }
    header('Location: blocked-ips.php?removed=1');
    exit;
}

header('Location: blocked-ips.php?error=' . rawurlencode('Unknown action.'));
exit;
