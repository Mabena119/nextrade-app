<?php
require __DIR__ . '/include/require_super.php';
require dirname(__DIR__) . '/php-includes/security-bridge.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/affiliate.php';
auraai_sec_bootstrap();
auraai_sec_require_method('POST');
auraai_sec_require_same_origin();

require dirname(__DIR__) . '/php-includes/connect.php';

$affiliateId = auraai_sec_int($_POST['affiliate_id'] ?? null, 1, 999999);
$action = auraai_sec_enum($_POST['action'] ?? '', ['activate', 'suspend', 'block', 'delete']);

if ($affiliateId === null || $action === null) {
    header('Location: affiliates.php?error=' . urlencode('Invalid request.'));
    exit;
}

$redirect = 'affiliate-detail.php?id=' . $affiliateId;

if ($action === 'delete') {
    $result = auraai_affiliate_admin_delete($con, $affiliateId);
    if (!$result['ok']) {
        header('Location: ' . $redirect . '&error=' . urlencode($result['error'] ?? 'Delete failed.'));
        exit;
    }

    header('Location: affiliates.php?deleted=1');
    exit;
}

$statusMap = [
    'activate' => 'active',
    'suspend' => 'paused',
    'block' => 'blocked',
];
$result = auraai_affiliate_admin_update_status($con, $affiliateId, $statusMap[$action]);

if (!$result['ok']) {
    header('Location: ' . $redirect . '&error=' . urlencode($result['error'] ?? 'Update failed.'));
    exit;
}

header('Location: ' . $redirect . '&updated=1');
exit;
