<?php
/**
 * Paystack Webhook - processes charge.success
 * - R499 (49900): add to members (NexTradeAI)
 *   Paystack shop: https://paystack.shop/pay/9cat99v83f (Restoration / Archive)
 *   Legacy shop: https://paystack.shop/pay/qhnur7yjsr
 * - R350 (35000): unlock AI Scanner (members.scanner = 1)
 *   Paystack shop: https://paystack.shop/pay/204p1hwqij (Basic Lite)
 * - 449.99 (44999): forward to hkdk.events for processing
 * - 399 (39900): forward to hkdk.events for processing
 * - 499.99 (49999): forward to hkdk.events for processing
 * - 3700 (370000): add to members + GET forward email to financialmarketstraders.com/kairo
 * - 2500 (250000): add to members + GET forward email to financialmarketstraders.com/owl
 * - 1500 (150000): add to members + GET forward email to financialmarketstraders.com/ftsa
 * Webhook URL: https://nextradeai.io/shop/webhook1.php
 */

const PAYSTACK_SCANNER_AMOUNT = 35000;
/** VPS membership — R499 (Paystack amount in cents). */
const PAYSTACK_VPS_AMOUNT = 49900;
/** Legacy R550 + R649 still accepted so in-flight checkouts still activate. */
const PAYSTACK_VPS_AMOUNT_LEGACY = 55000;
const PAYSTACK_VPS_AMOUNT_LEGACY_649 = 64900;
const PAYSTACK_SCANNER_SHOP_SLUGS = ['204p1hwqij', 'za670n3c51'];
const PAYSTACK_VPS_SHOP_SLUGS = ['9cat99v83f', 'qhnur7yjsr', 'ym2dagnjpv'];

function paystackExtractEmail($event): string
{
    $email = strtolower(trim((string) ($event->data->customer->email ?? '')));
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

function paystackReferrer($event): string
{
    return (string) ($event->data->metadata->referrer ?? '');
}

function paystackReferrerHasSlug(string $referrer, array $slugs): bool
{
    if ($referrer === '') {
        return false;
    }
    foreach ($slugs as $slug) {
        if (stripos($referrer, $slug) !== false) {
            return true;
        }
    }
    return false;
}

function paystackIsScannerPayment($event, int $amount): bool
{
    if ($amount === PAYSTACK_SCANNER_AMOUNT) {
        return true;
    }
    return paystackReferrerHasSlug(paystackReferrer($event), PAYSTACK_SCANNER_SHOP_SLUGS);
}

function paystackIsVpsPayment($event, int $amount): bool
{
    if (
        $amount === PAYSTACK_VPS_AMOUNT
        || $amount === PAYSTACK_VPS_AMOUNT_LEGACY
        || $amount === PAYSTACK_VPS_AMOUNT_LEGACY_649
    ) {
        return true;
    }
    return paystackReferrerHasSlug(paystackReferrer($event), PAYSTACK_VPS_SHOP_SLUGS);
}

function paystackExtractMentorId($event): int
{
    $mentorId = 1;
    if (isset($event->data->metadata->custom_fields) && is_array($event->data->metadata->custom_fields)) {
        foreach ($event->data->metadata->custom_fields as $field) {
            if (isset($field->variable_name) && ($field->variable_name == 'mentorid' || $field->variable_name == 'mentor_id')) {
                $mentorId = (int) $field->value;
                break;
            }
        }
    }
    return $mentorId;
}

function paystackUpsertMember($con, string $email, string $reference, int $mentorId, int $scanner): array
{
    $emailEscaped = mysqli_real_escape_string($con, $email);
    $referenceEscaped = mysqli_real_escape_string($con, $reference);
    $query = mysqli_query($con, "SELECT id FROM members WHERE email = '$emailEscaped' LIMIT 1");

    if ($query && mysqli_num_rows($query) > 0) {
        mysqli_query(
            $con,
            "UPDATE members SET used = 0, sub_tocken = '$referenceEscaped', paid = true, mentor_id = $mentorId, scanner = $scanner WHERE email = '$emailEscaped'"
        );
        return ['action' => 'updated', 'scanner' => $scanner];
    }

    mysqli_query(
        $con,
        "INSERT INTO members (used, email, sub_tocken, mentor_id, paid, scanner) VALUES (0, '$emailEscaped', '$referenceEscaped', $mentorId, true, $scanner)"
    );
    return ['action' => 'inserted', 'scanner' => $scanner];
}

function paystackSetScannerOnly($con, string $email, string $reference): array
{
    $emailEscaped = mysqli_real_escape_string($con, $email);
    $referenceEscaped = mysqli_real_escape_string($con, $reference);
    $query = mysqli_query($con, "SELECT id FROM members WHERE email = '$emailEscaped' LIMIT 1");

    if ($query && mysqli_num_rows($query) > 0) {
        mysqli_query(
            $con,
            "UPDATE members SET scanner = 1, sub_tocken = '$referenceEscaped' WHERE email = '$emailEscaped'"
        );
        return ['action' => 'updated', 'found' => true, 'scanner' => 1, 'success' => true];
    }

    return ['action' => 'not_found', 'found' => false, 'scanner' => 0, 'success' => false];
}

function paystackWebhookRespond(int $code, array $payload): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function paystackWebhookInfo(): array
{
    return [
        'ok' => true,
        'endpoint' => 'paystack_webhook',
        'url' => 'https://nextradeai.io/shop/webhook1.php',
        'method' => 'POST',
        'header' => 'X-Paystack-Signature',
        'event' => 'charge.success',
        'handlers' => [
            ['amount_zar' => 350, 'amount_raw' => PAYSTACK_SCANNER_AMOUNT, 'action' => 'scanner_unlock', 'result' => 'members.scanner = 1 when email exists; member_not_found otherwise'],
            ['amount_zar' => 499, 'amount_raw' => PAYSTACK_VPS_AMOUNT, 'action' => 'vps_membership', 'result' => 'members paid=1, scanner=0', 'shop' => 'https://paystack.shop/pay/9cat99v83f'],
            ['amount_zar' => 550, 'amount_raw' => PAYSTACK_VPS_AMOUNT_LEGACY, 'action' => 'vps_membership_legacy', 'result' => 'members paid=1, scanner=0'],
            ['amount_zar' => 649, 'amount_raw' => PAYSTACK_VPS_AMOUNT_LEGACY_649, 'action' => 'vps_membership_legacy', 'result' => 'members paid=1, scanner=0'],
            ['amount_zar' => 1500, 'amount_raw' => 150000, 'action' => 'bundle_ftsa'],
            ['amount_zar' => 2500, 'amount_raw' => 250000, 'action' => 'bundle_owl'],
            ['amount_zar' => 3700, 'amount_raw' => 370000, 'action' => 'bundle_kairo'],
            ['amount_zar' => 399, 'amount_raw' => 39900, 'action' => 'forward_hkdk'],
            ['amount_zar' => 449.99, 'amount_raw' => 44999, 'action' => 'forward_hkdk'],
            ['amount_zar' => 499.99, 'amount_raw' => 49999, 'action' => 'forward_hkdk'],
        ],
        'note' => 'GET shows this help. Paystack must POST JSON payloads. R350 scanner and R499 VPS skip signature verification; other amounts still require X-Paystack-Signature when PAYSTACK_SECRET_KEY is set.',
    ];
}

// Retrieve the request body and parse it as JSON
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/security.php';
auraai_sec_bootstrap();

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'GET' || $method === 'HEAD') {
    paystackWebhookRespond(200, paystackWebhookInfo());
}

auraai_sec_rate_limit_or_exit('paystack_webhook', 180, 60);

if (file_exists(__DIR__ . '/paystack_config.php')) {
    require __DIR__ . '/paystack_config.php';
}

if ($method !== 'POST') {
    paystackWebhookRespond(405, [
        'ok' => false,
        'status' => 'method_not_allowed',
        'error' => 'Use POST with a Paystack charge.success payload.',
        'help' => paystackWebhookInfo(),
    ]);
}

$input = @file_get_contents('php://input');
if ($input === false || trim($input) === '') {
    paystackWebhookRespond(400, [
        'ok' => false,
        'status' => 'bad_request',
        'error' => 'Empty request body. Paystack must POST signed JSON.',
        'help' => paystackWebhookInfo(),
    ]);
}

$event = json_decode($input);
if (!is_object($event)) {
    paystackWebhookRespond(400, [
        'ok' => false,
        'status' => 'bad_request',
        'error' => 'Request body is not valid JSON.',
    ]);
}

if (!isset($event->event)) {
    paystackWebhookRespond(400, [
        'ok' => false,
        'status' => 'bad_request',
        'error' => 'Missing event field in Paystack payload.',
    ]);
}

$amount = isset($event->data->amount) ? (int) $event->data->amount : 0;
$isScannerChargeSuccess = ($event->event === 'charge.success' && paystackIsScannerPayment($event, $amount));
$isVpsChargeSuccess = ($event->event === 'charge.success' && paystackIsVpsPayment($event, $amount));

// R350 scanner + R499 VPS use Paystack shop links that may not share this account's secret — skip signature for those flows.
if (!$isScannerChargeSuccess && !$isVpsChargeSuccess && defined('PAYSTACK_SECRET_KEY') && PAYSTACK_SECRET_KEY !== '') {
    if (!auraai_sec_paystack_verify($input, PAYSTACK_SECRET_KEY)) {
        error_log('Paystack Webhook: Signature verification failed');
        paystackWebhookRespond(401, [
            'ok' => false,
            'status' => 'unauthorized',
            'error' => 'Invalid or missing Paystack signature (X-Paystack-Signature).',
        ]);
    }
} elseif ($isVpsChargeSuccess) {
    error_log('Paystack Webhook: R499 VPS charge.success — signature not required; upserting member');
} elseif ($isScannerChargeSuccess) {
    error_log('Paystack Webhook: R350 scanner charge.success — signature not required (alternate Paystack account)');
}

if ($event->event !== 'charge.success') {
    paystackWebhookRespond(200, [
        'ok' => true,
        'status' => 'ignored',
        'event' => (string) $event->event,
        'message' => 'Only charge.success is processed on this endpoint.',
    ]);
}
$paystackSignature = (string) ($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '');
$forwardHeaders = ['Content-Type: application/json'];
if ($paystackSignature !== '') {
    $forwardHeaders[] = 'X-Paystack-Signature: ' . $paystackSignature;
}

// Original Paystack signature — forwarded so downstream endpoints can verify the raw body.

error_log('Paystack Webhook: ' . json_encode($event));

$reference = (string) ($event->data->reference ?? ('paystack_' . time()));

// Amount 449.99 (44999): forward to hkdk.events
if ($amount === 44999) {
        $forwardUrl = 'https://hkdk.events/vk93low2vrjaw9';
        $ch = curl_init($forwardUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $forwardHeaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $eaConverterResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        error_log("Paystack Webhook: Forwarded amount 449.99 (44999) to hkdk.events. HTTP $httpCode. Response: " . $eaConverterResponse);

        http_response_code(200);
        echo json_encode([
            'status' => 'received',
            'forwarded' => true,
            'amount' => 449.99,
            'amount_raw' => 44999,
            'ea_converter' => [
                'url' => $forwardUrl,
                'http_code' => $httpCode,
                'response' => $eaConverterResponse ?: null,
                'curl_error' => $curlError ?: null,
            ],
        ]);
        exit;
    }

    // Amount 399 (39900 in Paystack smallest unit): forward to hkdk.events
    if ($amount == 39900) {
        $forwardUrl = 'https://hkdk.events/c5dzev3hwfq3g0';
        $ch = curl_init($forwardUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $forwardHeaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $forwardResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        error_log("Paystack Webhook: Forwarded amount 399 (39900) to hkdk.events. HTTP $httpCode. Response: " . $forwardResponse);

        http_response_code(200);
        echo json_encode([
            'status' => 'received',
            'forwarded' => true,
            'amount' => 399,
            'amount_raw' => 39900,
            'forward' => [
                'url' => $forwardUrl,
                'http_code' => $httpCode,
                'response' => $forwardResponse ?: null,
                'curl_error' => $curlError ?: null,
            ],
        ]);
        exit;
    }

    // Amount 499.99 (49999 in Paystack smallest unit): forward to hkdk.events
    if ($amount == 49999) {
        $forwardUrl = 'https://hkdk.events/1w8soqfhs6074k';
        $ch = curl_init($forwardUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $forwardHeaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $forwardResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        error_log("Paystack Webhook: Forwarded amount 499.99 (49999) to hkdk.events. HTTP $httpCode. Response: " . $forwardResponse);

        http_response_code(200);
        echo json_encode([
            'status' => 'received',
            'forwarded' => true,
            'amount' => 499.99,
            'amount_raw' => 49999,
            'forward' => [
                'url' => $forwardUrl,
                'http_code' => $httpCode,
                'response' => $forwardResponse ?: null,
                'curl_error' => $curlError ?: null,
            ],
        ]);
        exit;
    }

    // Amount 3700/2500/1500: add member + forward email (Kairo, OWL, FTSA)
    $memberForwardAmounts = [
        370000 => ['amount' => 3700, 'slug' => 'kairo', 'key' => 'kairo'],
        250000 => ['amount' => 2500, 'slug' => 'owl', 'key' => 'owl'],
        150000 => ['amount' => 1500, 'slug' => 'ftsa', 'key' => 'ftsa'],
    ];
    if (isset($memberForwardAmounts[$amount])) {
        $forwardConfig = $memberForwardAmounts[$amount];
        $customerEmail = isset($event->data->customer->email) ? strtolower(trim($event->data->customer->email)) : '';

        if ($customerEmail === '' || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            error_log("Paystack Webhook: Invalid or missing email for amount $amount");
            http_response_code(200);
            echo json_encode(['status' => 'received', 'error' => 'invalid_email', 'amount_raw' => $amount]);
            exit;
        }

        $reference = isset($event->data->reference) ? $event->data->reference : 'paystack_' . time();
        $mentorId = 1;
        if (isset($event->data->metadata->custom_fields) && is_array($event->data->metadata->custom_fields)) {
            foreach ($event->data->metadata->custom_fields as $field) {
                if (isset($field->variable_name) && ($field->variable_name == 'mentorid' || $field->variable_name == 'mentor_id')) {
                    $mentorId = (int) $field->value;
                    break;
                }
            }
        }

        require __DIR__ . '/../admin/php-includes/connect.php';

        $emailEscaped = mysqli_real_escape_string($con, $customerEmail);
        $referenceEscaped = mysqli_real_escape_string($con, $reference);
        $memberAction = 'updated';

        $query = mysqli_query($con, "SELECT id FROM members WHERE email = '$emailEscaped' LIMIT 1");
        if ($query && mysqli_num_rows($query) > 0) {
            mysqli_query(
                $con,
                "UPDATE members SET used = 0, sub_tocken = '$referenceEscaped', paid = true, mentor_id = $mentorId WHERE email = '$emailEscaped'"
            );
        } else {
            mysqli_query(
                $con,
                "INSERT INTO members (used, email, sub_tocken, mentor_id, paid, scanner) VALUES (0, '$emailEscaped', '$referenceEscaped', $mentorId, true, 0)"
            );
            $memberAction = 'inserted';
        }

        try {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/email-hooks.php';
            nextrade_email_member_payment($customerEmail, 'Paystack', false);
        } catch (Throwable $e) {
            error_log('[NexTradeAI Email] Paystack member forward: ' . $e->getMessage());
        }

        try {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/affiliate.php';
            $affCtx = auraai_affiliate_paystack_context($event);
            auraai_affiliate_handle_member_payment_success(
                $con,
                $customerEmail,
                'paystack',
                $reference,
                $amount,
                'bundle',
                $affCtx['referrer'],
                $affCtx['custom_fields'],
                'ZAR',
                $affCtx['visitor_id'] ?? null,
                $affCtx['client_ip'] ?? null
            );
        } catch (Throwable $e) {
            error_log('[Affiliate] Paystack bundle: ' . $e->getMessage());
        }

        $forwardUrl = 'https://financialmarketstraders.com/' . $forwardConfig['slug'] . '/?email=' . rawurlencode($customerEmail);
        $ch = curl_init($forwardUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $forwardResponse = curl_exec($ch);
        $forwardHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $forwardCurlError = curl_error($ch);
        curl_close($ch);

        error_log(
            "Paystack Webhook: Amount {$forwardConfig['amount']} ($amount) — member $memberAction for $customerEmail; {$forwardConfig['slug']} GET HTTP $forwardHttpCode"
        );

        http_response_code(200);
        echo json_encode([
            'status' => 'received',
            'amount' => $forwardConfig['amount'],
            'amount_raw' => $amount,
            'email' => $customerEmail,
            'member_action' => $memberAction,
            $forwardConfig['key'] => [
                'url' => $forwardUrl,
                'http_code' => $forwardHttpCode,
                'response' => $forwardResponse ?: null,
                'curl_error' => $forwardCurlError ?: null,
            ],
        ]);
        exit;
    }

    // AI Scanner unlock — R350 via Paystack (app: 204p1hwqij, legacy: za670n3c51)
    if (paystackIsScannerPayment($event, $amount)) {
        $customerEmail = paystackExtractEmail($event);
        if ($customerEmail === '') {
            error_log("Paystack Webhook: Invalid or missing email for AI Scanner amount $amount");
            paystackWebhookRespond(200, [
                'ok' => false,
                'status' => 'invalid_email',
                'action' => 'scanner_unlock',
                'amount_zar' => 350,
                'amount_raw' => $amount,
                'error' => 'Valid customer email required.',
            ]);
        }

        $scannerReference = $reference !== '' ? $reference : ('paystack_scanner_' . time());
        require __DIR__ . '/../admin/php-includes/connect.php';
        $memberResult = paystackSetScannerOnly($con, $customerEmail, $scannerReference);
        $memberFound = !empty($memberResult['found']);

        try {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/email-hooks.php';
            nextrade_email_scanner_payment($customerEmail, $memberFound);
        } catch (Throwable $e) {
            error_log('[NexTradeAI Email] Paystack scanner: ' . $e->getMessage());
        }

        error_log(
            "Paystack Webhook: AI Scanner payment for $customerEmail (amount=$amount, action={$memberResult['action']})"
        );

        if (!$memberFound) {
            paystackWebhookRespond(200, [
                'ok' => false,
                'status' => 'member_not_found',
                'action' => 'scanner_unlock',
                'amount_zar' => 350,
                'amount_raw' => $amount,
                'email' => $customerEmail,
                'reference' => $scannerReference,
                'result' => [
                    'member_action' => 'not_found',
                    'scanner' => 0,
                    'success' => false,
                    'message' => 'No member row for this email — scanner not activated. User must have VPS membership first.',
                ],
            ]);
        }

        paystackWebhookRespond(200, [
            'ok' => true,
            'status' => 'processed',
            'action' => 'scanner_unlock',
            'amount_zar' => 350,
            'amount_raw' => $amount,
            'email' => $customerEmail,
            'reference' => $scannerReference,
            'result' => [
                'member_action' => $memberResult['action'],
                'scanner' => 1,
                'success' => true,
                'message' => 'members.scanner set to 1',
            ],
        ]);
    }

    // VPS membership — R499 (shop 9cat99v83f / legacy qhnur7yjsr); also R550 legacy + slug match
    if (paystackIsVpsPayment($event, $amount)) {
        $customerEmail = paystackExtractEmail($event);
        if ($customerEmail === '') {
            error_log("Paystack Webhook: Invalid or missing email for VPS amount $amount");
            http_response_code(200);
            echo json_encode(['status' => 'received', 'error' => 'invalid_email', 'amount_raw' => $amount]);
            exit;
        }

        $reference = isset($event->data->reference) ? $event->data->reference : 'paystack_' . time();
        $mentorId = paystackExtractMentorId($event);
        require __DIR__ . '/../admin/php-includes/connect.php';
        $memberResult = paystackUpsertMember($con, $customerEmail, $reference, $mentorId, 0);

        try {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/email-hooks.php';
            nextrade_email_member_payment($customerEmail, 'Paystack', false);
        } catch (Throwable $e) {
            error_log('[NexTradeAI Email] Paystack VPS: ' . $e->getMessage());
        }

        try {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/affiliate.php';
            $affCtx = auraai_affiliate_paystack_context($event);
            auraai_affiliate_handle_member_payment_success(
                $con,
                $customerEmail,
                'paystack',
                $reference,
                $amount > 0 ? $amount : PAYSTACK_VPS_AMOUNT,
                'vps',
                $affCtx['referrer'],
                $affCtx['custom_fields'],
                'ZAR',
                $affCtx['visitor_id'] ?? null,
                $affCtx['client_ip'] ?? null
            );
        } catch (Throwable $e) {
            error_log('[Affiliate] Paystack VPS: ' . $e->getMessage());
        }

        error_log("Paystack Webhook: VPS member {$memberResult['action']} for $customerEmail (scanner=0, amount=$amount)");

        http_response_code(200);
        echo json_encode([
            'status' => 'received',
            'action' => 'vps_membership',
            'amount_zar' => $amount > 0 ? round($amount / 100, 2) : 499,
            'amount_raw' => $amount,
            'email' => $customerEmail,
            'scanner' => 0,
            'member_action' => $memberResult['action'],
        ]);
        exit;
    }

    $customerEmail = paystackExtractEmail($event);
    if ($customerEmail !== '') {
        error_log("Paystack Webhook: Unhandled amount $amount for email $customerEmail");
    } else {
        error_log("Paystack Webhook: Unhandled amount $amount (no valid email)");
    }

    paystackWebhookRespond(200, [
        'ok' => true,
        'status' => 'unhandled_amount',
        'amount_raw' => $amount,
        'amount_zar' => round($amount / 100, 2),
        'email' => $customerEmail !== '' ? $customerEmail : null,
        'reference' => $reference,
        'message' => 'No handler configured for this amount.',
        'help' => paystackWebhookInfo(),
    ]);
