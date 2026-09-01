<?php
require __DIR__ . '/include/require_super.php';
require dirname(__DIR__) . '/php-includes/security-bridge.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/affiliate.php';
auraai_sec_bootstrap();
auraai_sec_require_method('POST');
auraai_sec_require_same_origin();

require dirname(__DIR__) . '/php-includes/connect.php';

$id = auraai_sec_int($_POST['id'] ?? null, 1);
$status = auraai_sec_enum($_POST['status'] ?? '', ['processing', 'paid', 'rejected']);
$notes = auraai_sec_string($_POST['admin_notes'] ?? '', 500, 0) ?? '';

if ($id === null || $status === null) {
    header('Location: affiliates.php?error=' . urlencode('Invalid request.'));
    exit;
}

$result = auraai_affiliate_process_withdrawal($con, $id, $status, $notes);

if (!$result['ok']) {
    header('Location: affiliates.php?error=' . urlencode($result['error'] ?? 'Update failed.'));
    exit;
}

header('Location: affiliates.php?updated=1');
exit;
