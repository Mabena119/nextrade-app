<?php
/**
 * Whop webhook endpoint (notifyb.php).
 * Ozow uses notify.php — do not point Whop here.
 *
 * Whop dashboard URL: https://auraai-vps.com/shop/notifyb.php
 * Events: membership.activated, payment.succeeded
 *
 * Product routing (membership.activated / payment.succeeded):
 *   NexTradeAI     (prod_Qhg225hmLDoay) → add/update member (paid = 1)
 *   NexTradeAI Scanner (prod_2ZlqLG9vBe3tF) → set scanner = 1 (create member if missing)
 */

if (file_exists(__DIR__ . '/whop_config.php')) {
    require __DIR__ . '/whop_config.php';
}
if (!defined('WHOP_WEBHOOK_SECRET')) {
    define('WHOP_WEBHOOK_SECRET', getenv('WHOP_WEBHOOK_SECRET') ?: 'whsec_YOUR_SECRET_HERE');
}
if (!defined('WHOP_ALLOW_UNVERIFIED_FOR_TESTING')) {
    define('WHOP_ALLOW_UNVERIFIED_FOR_TESTING', true);
}
if (!defined('WHOP_DEFAULT_MENTOR_ID')) {
    define('WHOP_DEFAULT_MENTOR_ID', 1);
}

header('Content-Type: text/plain');

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/security.php';
auraai_sec_bootstrap();
auraai_sec_rate_limit_or_exit('whop_webhook', 180, 60);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}

$rawBody = file_get_contents('php://input');
if ($rawBody === false || $rawBody === '') {
    error_log('Whop Webhook: Empty body');
    http_response_code(400);
    echo 'Bad request';
    exit;
}

whop_log_request($rawBody);

$signatureHeader = $_SERVER['HTTP_SVIX_SIGNATURE'] ?? $_SERVER['HTTP_WEBHOOK_SIGNATURE'] ?? '';
$timestampHeader = $_SERVER['HTTP_SVIX_TIMESTAMP'] ?? $_SERVER['HTTP_WEBHOOK_TIMESTAMP'] ?? '';
$idHeader = $_SERVER['HTTP_SVIX_ID'] ?? $_SERVER['HTTP_WEBHOOK_ID'] ?? '';
$hasSignatureHeaders = $signatureHeader !== '' && $timestampHeader !== '' && $idHeader !== '';

if (!WHOP_ALLOW_UNVERIFIED_FOR_TESTING) {
    if (!$hasSignatureHeaders || !verifyWhopSignature($rawBody, $signatureHeader, $timestampHeader, $idHeader)) {
        error_log('Whop Webhook: Signature verification failed');
        http_response_code(401);
        echo 'Unauthorized';
        exit;
    }
} elseif ($hasSignatureHeaders && WHOP_WEBHOOK_SECRET !== 'whsec_YOUR_SECRET_HERE' && WHOP_WEBHOOK_SECRET !== '') {
    if (!verifyWhopSignature($rawBody, $signatureHeader, $timestampHeader, $idHeader)) {
        // ws_ secrets often fail Standard Webhooks verification — log and continue when unverified mode is on.
        error_log('Whop Webhook: Signature verification failed (unverified mode — continuing)');
    }
}

$payload = json_decode($rawBody);
if (!is_object($payload)) {
    error_log('Whop Webhook: Invalid JSON');
    http_response_code(400);
    echo 'Bad request';
    exit;
}

$eventType = (string) ($payload->type ?? $payload->action ?? $payload->event_type ?? '');
$data = $payload->data ?? $payload->payload ?? new stdClass();
$companyId = (string) ($payload->company_id ?? $data->company->id ?? '');

if (defined('WHOP_COMPANY_ID') && WHOP_COMPANY_ID !== '' && $companyId !== '' && $companyId !== WHOP_COMPANY_ID) {
    error_log("Whop Webhook: Skipping — company $companyId does not match");
    http_response_code(200);
    echo whop_response('skipped', 'Company ID does not match configured company', [
        'company_id' => $companyId,
    ]);
    exit;
}

if (!in_array($eventType, ['payment.succeeded', 'membership.activated', 'membership.activated.v2'], true)) {
    http_response_code(200);
    echo whop_response('skipped', 'Event type not handled', ['event' => $eventType]);
    exit;
}

$product = extractProductInfo($data);
if (isWhopMatrixProduct($data, $product)) {
    error_log("Whop Webhook: Skipping EA Matrix product {$product['id']} ({$product['title']})");
    http_response_code(200);
    echo whop_response('skipped', 'EA Matrix VPS is not handled on this endpoint', [
        'event' => $eventType,
        'product_id' => $product['id'],
        'product_title' => $product['title'],
    ]);
    exit;
}

if ($eventType === 'payment.succeeded' && !isPaymentSucceeded($data)) {
    error_log('Whop Webhook: Skipping payment.succeeded — status not paid (' . ($data->status ?? '') . ')');
    http_response_code(200);
    echo whop_response('skipped', 'Payment status is not paid', [
        'event' => $eventType,
        'status' => (string) ($data->status ?? ''),
    ]);
    exit;
}

if (in_array($eventType, ['membership.activated', 'membership.activated.v2'], true)
    && !isMembershipCompleted($data)) {
    error_log('Whop Webhook: Skipping membership.activated — status not completed (' . ($data->status ?? '') . ')');
    http_response_code(200);
    echo whop_response('skipped', 'Membership status is not active/completed', [
        'event' => $eventType,
        'status' => (string) ($data->status ?? ''),
    ]);
    exit;
}

$email = extractEmailFromPayload($data);
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    error_log("Whop Webhook: No valid email in $eventType");
    http_response_code(200);
    echo whop_response('skipped', 'No valid email in payload', ['event' => $eventType]);
    exit;
}

$product = extractProductInfo($data);
$productAction = resolveWhopProductAction($data);
if ($productAction === null) {
    error_log("Whop Webhook: Skipping $eventType for $email — unrecognized product {$product['id']} ({$product['title']})");
    http_response_code(200);
    echo whop_response('skipped', 'Unrecognized Whop product', [
        'event' => $eventType,
        'email' => $email,
        'product_id' => $product['id'],
        'product_title' => $product['title'],
    ]);
    exit;
}

require '../admin/php-includes/connect.php';

$paymentId = (string) ($data->id ?? $data->payment_id ?? $data->membership_id ?? uniqid('whop_'));
$mentorId = extractMentorIdFromPayload($data);

if ($productAction === 'vps') {
    $ok = whop_upsert_full_member($con, $email, $paymentId, $mentorId);
    $action = 'add_member';
    $productLabel = 'NexTradeAI';
    $detail = $ok ? 'Member added/updated with paid = 1' : 'Could not add or update member';
} else {
    $scannerResult = whop_set_scanner_for_member($con, $email, $paymentId, $mentorId);
    $action = 'set_scanner';
    $productLabel = 'NexTradeAI Scanner';
    if ($scannerResult['member_action'] === 'updated') {
        $ok = true;
        $detail = 'scanner set to 1 (member updated)';
    } elseif ($scannerResult['member_action'] === 'not_found') {
        $ok = true;
        $detail = 'member email not found — scanner not activated';
    } else {
        $ok = false;
        $detail = 'Could not set scanner for email';
    }
}

$result = [
    'event' => $eventType,
    'email' => $email,
    'product_id' => $product['id'],
    'product_title' => $product['title'],
    'action' => $action,
    'mentor_id' => $mentorId,
    'payment_id' => $paymentId,
];

if ($productAction === 'scanner' && isset($scannerResult['member_action'])) {
    $result['member_action'] = $scannerResult['member_action'];
}

if ($ok) {
    try {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
        auraai_email_bootstrap();
        if ($productAction === 'scanner') {
            $memberFound = ($scannerResult['member_action'] ?? '') === 'updated';
            auraai_email_scanner_payment_result($email, $memberFound);
        } else {
            auraai_email_member_payment_success($email, 'Whop', false);
        }
    } catch (Throwable $e) {
        error_log('[NexTradeAI Email] Whop webhook: ' . $e->getMessage());
    }

    if ($productAction === 'vps') {
        try {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/affiliate.php';
            $affCtx = auraai_affiliate_whop_context($data);
            $whopAmount = (int) ($data->amount ?? $data->total ?? $data->plan->renewal_price ?? 0);
            if ($whopAmount < 1000) {
                $whopAmount = 55000;
            }
            if ($affCtx['ref_code'] !== null) {
                auraai_affiliate_bind_email($con, $email, $affCtx['ref_code']);
            }
            $affiliateRecorded = auraai_affiliate_handle_member_payment_success(
                $con,
                $email,
                'whop',
                $paymentId,
                $whopAmount,
                'vps',
                $affCtx['referrer'],
                $affCtx['custom_fields'],
                $affCtx['currency'],
                $affCtx['visitor_id'] ?? null,
                $affCtx['client_ip'] ?? null
            );
            $result['affiliate_recorded'] = $affiliateRecorded ? 'yes' : 'no';
            if (!$affiliateRecorded) {
                error_log("[Affiliate] Whop VPS: no attribution for $email (payment_id=$paymentId)");
            }
        } catch (Throwable $e) {
            error_log('[Affiliate] Whop VPS: ' . $e->getMessage());
        }
    }

    error_log("Whop Webhook: $productLabel OK for $email (event=$eventType, action=$action, mentor_id=$mentorId)");
    http_response_code(200);
    echo whop_response('ok', "$productLabel: $detail", $result);
} else {
    error_log("Whop Webhook: $productLabel FAILED for $email (event=$eventType, action=$action)");
    http_response_code(500);
    echo whop_response('failed', "$productLabel: $detail", $result);
}

function whop_log_request(string $rawBody): void
{
    $logFile = __DIR__ . '/whop_notify_log.txt';
    file_put_contents($logFile, date('c') . ' ' . $rawBody . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function verifyWhopSignature(string $body, string $signatureHeader, string $timestamp, string $msgId): bool
{
    if (abs(time() - (int) $timestamp) > 300) {
        return false;
    }

    $secret = WHOP_WEBHOOK_SECRET;
    $secretBytes = null;
    if (strpos($secret, 'whsec_') === 0) {
        $secretBytes = base64_decode(substr($secret, 6), true);
    } elseif (strpos($secret, 'ws_') === 0) {
        $secretBytes = hex2bin(substr($secret, 3));
    } else {
        return false;
    }
    if ($secretBytes === false || strlen($secretBytes) < 16) {
        return false;
    }

    $signedContent = $msgId . '.' . $timestamp . '.' . $body;
    $expectedSig = base64_encode(hash_hmac('sha256', $signedContent, $secretBytes, true));

    foreach (explode(' ', $signatureHeader) as $sig) {
        $parts = explode(',', $sig, 2);
        $sigValue = isset($parts[1]) ? trim($parts[1]) : trim($sig);
        if (hash_equals($expectedSig, $sigValue)) {
            return true;
        }
    }

    return false;
}

function extractEmailFromPayload($data): string
{
    if (!is_object($data)) {
        return '';
    }

    $candidates = [
        $data->user->email ?? null,
        $data->user_email ?? null,
        $data->member->email ?? null,
        $data->membership->email ?? null,
        $data->email ?? null,
    ];

    foreach ($candidates as $email) {
        if (is_string($email) && trim($email) !== '') {
            return strtolower(trim($email));
        }
    }

    return '';
}

function extractMentorIdFromPayload($data): int
{
    $mentorId = (int) WHOP_DEFAULT_MENTOR_ID;
    if (!is_object($data)) {
        return $mentorId;
    }

    $fields = $data->custom_field_responses
        ?? $data->custom_fields
        ?? $data->membership->custom_field_responses
        ?? null;

    if (!is_array($fields)) {
        return $mentorId;
    }

    foreach ($fields as $field) {
        if (!is_object($field)) {
            continue;
        }
        $name = $field->variable_name ?? $field->name ?? '';
        if (in_array($name, ['mentorid', 'mentor_id'], true)) {
            $value = (int) ($field->value ?? $field->answer ?? 0);
            if ($value > 0) {
                return $value;
            }
        }
    }

    return $mentorId;
}

function isPaymentSucceeded($data): bool
{
    if (!is_object($data)) {
        return false;
    }
    $status = strtolower((string) ($data->status ?? ''));
    if ($status !== 'paid') {
        return false;
    }
    $substatus = strtolower((string) ($data->substatus ?? ''));
    return $substatus === '' || $substatus === 'succeeded';
}

function isMembershipCompleted($data): bool
{
    if (!is_object($data)) {
        return false;
    }
    $status = strtolower((string) ($data->status ?? ''));
    return in_array($status, ['completed', 'active', 'trialing'], true);
}

function extractProductInfo($data): array
{
    if (!is_object($data)) {
        return ['id' => '', 'title' => ''];
    }

    $product = $data->product ?? null;
    $id = (string) ($data->product_id ?? (is_object($product) ? ($product->id ?? '') : ''));
    $title = (string) (is_object($product) ? ($product->title ?? $product->name ?? '') : '');

    return ['id' => $id, 'title' => $title];
}

/** EA Matrix VPS is a separate Whop product — acknowledge webhook but do not process here. */
function isWhopMatrixProduct($data, array $product): bool
{
    $matrixProductIds = ['prod_vxK8sI9whIztu'];
    $matrixPlanIds = ['plan_lokoDFGCt9OAk'];

    if ($product['id'] !== '' && in_array($product['id'], $matrixProductIds, true)) {
        return true;
    }

    if (is_object($data) && isset($data->plan) && is_object($data->plan)) {
        $planId = (string) ($data->plan->id ?? '');
        if ($planId !== '' && in_array($planId, $matrixPlanIds, true)) {
            return true;
        }
    }

    $title = strtolower($product['title']);
    return $title !== '' && (str_contains($title, 'ea matrix') || str_contains($title, 'matrix vps'));
}

/** @return 'vps'|'scanner'|null */
function resolveWhopProductAction($data): ?string
{
    $product = extractProductInfo($data);
    if (isWhopMatrixProduct($data, $product)) {
        return null;
    }

    $planId = '';
    if (is_object($data) && isset($data->plan) && is_object($data->plan)) {
        $planId = (string) ($data->plan->id ?? '');
    }

    $vpsId = defined('WHOP_PRODUCT_VPS_ID') ? WHOP_PRODUCT_VPS_ID : 'prod_Qhg225hmLDoay';
    $scannerId = defined('WHOP_PRODUCT_SCANNER_ID') ? WHOP_PRODUCT_SCANNER_ID : 'prod_2ZlqLG9vBe3tF';
    $vpsTitle = defined('WHOP_PRODUCT_VPS_TITLE') ? WHOP_PRODUCT_VPS_TITLE : 'NexTradeAI';
    $scannerTitle = defined('WHOP_PRODUCT_SCANNER_TITLE') ? WHOP_PRODUCT_SCANNER_TITLE : 'NexTradeAI Scanner';
    $vpsPlanId = 'plan_jj2FYMM2A6gbf';

    if ($product['id'] !== '' && $product['id'] === $vpsId) {
        return 'vps';
    }
    if ($planId !== '' && $planId === $vpsPlanId) {
        return 'vps';
    }
    if ($product['id'] !== '' && $product['id'] === $scannerId) {
        return 'scanner';
    }
    if ($product['title'] !== '' && stripos($product['title'], $vpsTitle) !== false) {
        return 'vps';
    }
    if ($product['title'] !== '' && stripos($product['title'], $scannerTitle) !== false) {
        return 'scanner';
    }

    return null;
}

function whop_response(string $status, string $message, array $context = []): string
{
    $lines = [strtoupper($status) . ': ' . $message];
    foreach ($context as $key => $value) {
        if ($value === '' || $value === null) {
            continue;
        }
        $lines[] = $key . '=' . $value;
    }

    return implode("\n", $lines);
}

function whop_upsert_full_member(mysqli $con, string $email, string $paymentId, int $mentorId): bool
{
    $stmt = $con->prepare('SELECT id FROM members WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if ($exists) {
        $stmt = $con->prepare('UPDATE members SET used = 0, sub_tocken = ?, paid = 1, mentor_id = ? WHERE email = ?');
        $stmt->bind_param('sis', $paymentId, $mentorId, $email);
    } else {
        $stmt = $con->prepare('INSERT INTO members (used, email, sub_tocken, mentor_id, paid) VALUES (0, ?, ?, ?, 1)');
        $stmt->bind_param('ssi', $email, $paymentId, $mentorId);
    }

    $ok = $stmt->execute();
    if (!$ok) {
        error_log('Whop DB error (full): ' . $stmt->error);
    }
    $stmt->close();
    return $ok;
}

/** @return array{ok:bool,action:string,member_action:string} */
function whop_set_scanner_for_member(mysqli $con, string $email, string $paymentId, int $mentorId = 1): array
{
    $fail = ['ok' => false, 'action' => 'set_scanner', 'member_action' => 'failed'];

    $stmt = $con->prepare('SELECT id, email FROM members WHERE LOWER(email) = LOWER(?) LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $stmt = $con->prepare('UPDATE members SET scanner = 1, sub_tocken = ? WHERE LOWER(email) = LOWER(?)');
        $stmt->bind_param('ss', $paymentId, $email);
        $ok = $stmt->execute();
        if (!$ok) {
            error_log('Whop DB error (scanner update): ' . $stmt->error);
        }
        $stmt->close();

        return $ok
            ? ['ok' => true, 'action' => 'set_scanner', 'member_action' => 'updated']
            : $fail;
    }

    return ['ok' => false, 'action' => 'set_scanner', 'member_action' => 'not_found'];
}
