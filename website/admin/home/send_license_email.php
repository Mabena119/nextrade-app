<?php
/**
 * POST /admin/home/send_license_email.php
 * Sends a license key email to a client (admin session required).
 */

require dirname(__DIR__) . '/php-includes/security-bridge.php';
auraai_sec_bootstrap();
auraai_sec_require_method('POST');
auraai_sec_require_admin_action();
auraai_sec_rate_limit_or_exit('send_license_email', 20, 3600);

header('Content-Type: application/json; charset=utf-8');

$input = $_POST;
if (empty($input) && stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $input = auraai_sec_json_input();
}

$licenseKey = auraai_sec_license_key($input['license_key'] ?? $input['key'] ?? '');
$recipient = auraai_sec_email($input['recipient_email'] ?? $input['email'] ?? '');
$eaName = auraai_sec_string($input['ea_name'] ?? '', 120) ?? '';

if ($licenseKey === null || $recipient === null) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Valid license_key and recipient_email are required']);
    exit;
}

try {
    require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
    auraai_email_bootstrap();

    $mentorName = auraai_sec_string($_SESSION['displayname'] ?? $_SESSION['name'] ?? '', 120) ?? '';
    $sent = auraai_email_license_key($recipient, $licenseKey, $eaName, $mentorName);

    if (!$sent) {
        $detail = auraai_email_last_error();
        $message = 'Failed to send email';
        if ($detail !== '') {
            if (stripos($detail, 'Unauthorized') !== false) {
                $message = 'Email relay not configured. Contact support.';
            } elseif (stripos($detail, 'Daily user sending limit exceeded') !== false) {
                $message = 'Gmail daily sending limit reached. Try again tomorrow.';
            } else {
                $message = $detail;
            }
        }
        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => $message]);
        exit;
    }

    echo json_encode(['ok' => true, 'message' => 'License key sent']);
} catch (Throwable $e) {
    error_log('[send_license_email] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}
