<?php
/**
 * NexTradeAI — PHPMailer + Gmail SMTP (smtp.gmail.com:587 STARTTLS).
 * cPanel hijacks local SMTP ports; falls back to Render HTTPS relay when needed.
 */

require_once __DIR__ . '/email-config.php';
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/** @return array{ok:bool,error?:string} */
function auraai_smtp_validate_message(string $subject, string $htmlBody): array
{
    if (strpos($subject, 'NexTradeAI —') !== 0) {
        return ['ok' => false, 'error' => 'Subject must start with "NexTradeAI —"'];
    }
    if (stripos($htmlBody, 'NexTradeAI') === false && stripos($htmlBody, 'nextradeai.io') === false) {
        return ['ok' => false, 'error' => 'Email body must be an NexTradeAI template'];
    }
    if (preg_match('/casino|BigWins|bonus powitalny|ZAREJESTRUJ/i', $subject . $htmlBody)) {
        return ['ok' => false, 'error' => 'Blocked content'];
    }
    return ['ok' => true];
}

/**
 * @return array{ok:bool,error?:string}
 */
function auraai_smtp_send(string $to, string $subject, string $htmlBody, ?string $textBody = null): array
{
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid recipient email'];
    }

    $valid = auraai_smtp_validate_message($subject, $htmlBody);
    if (!$valid['ok']) {
        error_log('[NexTradeAI Email] Rejected: ' . ($valid['error'] ?? 'invalid message'));
        return $valid;
    }

    $textBody = $textBody ?? trim(preg_replace('/\s+/', ' ', strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>'], "\n", $htmlBody))));

    $phpMailerResult = auraai_send_phpmailer($to, $subject, $htmlBody, $textBody);
    if ($phpMailerResult['ok']) {
        return $phpMailerResult;
    }

    return auraai_send_via_relay($to, $subject, $htmlBody, $textBody, $phpMailerResult['error'] ?? 'PHPMailer failed');
}

/** @return array{ok:bool,error?:string} */
function auraai_send_phpmailer(string $to, string $subject, string $htmlBody, string $textBody): array
{
    $mail = new PHPMailer(true);

    try {
        $mail->SMTPDebug = SMTP::DEBUG_OFF;
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = GMAIL_USER;
        $mail->Password = GMAIL_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->Timeout = 20;

        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ];

        $mail->setFrom(MAIL_FROM_EMAIL, GMAIL_FROM_NAME);
        $mail->addAddress($to);
        $mail->addReplyTo(MAIL_REPLY_TO, GMAIL_FROM_NAME);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody;

        $mail->send();
        return ['ok' => true];
    } catch (PHPMailerException $e) {
        $detail = $mail->ErrorInfo ?: $e->getMessage();
        error_log('[NexTradeAI PHPMailer] ' . $detail);
        return ['ok' => false, 'error' => $detail];
    }
}

/** HTTPS relay when cPanel blocks outbound Gmail SMTP. */
function auraai_send_via_relay(string $to, string $subject, string $htmlBody, string $textBody, ?string $reason = null): array
{
    if (!defined('AURAAI_EMAIL_RELAY_URL') || !defined('AURAAI_EMAIL_RELAY_SECRET')) {
        return ['ok' => false, 'error' => 'Relay not configured'];
    }

    $payload = json_encode([
        'to' => $to,
        'subject' => $subject,
        'html' => $htmlBody,
        'text' => $textBody,
        'gmailUser' => GMAIL_USER,
        'gmailPass' => GMAIL_PASS,
        'fromName' => GMAIL_FROM_NAME,
    ], JSON_UNESCAPED_UNICODE);

    if ($payload === false) {
        return ['ok' => false, 'error' => 'JSON encode failed'];
    }

    $ch = curl_init(AURAAI_EMAIL_RELAY_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-auraai-email-secret: ' . AURAAI_EMAIL_RELAY_SECRET,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'error' => 'Relay curl failed: ' . $curlError . ($reason ? " ($reason)" : '')];
    }

    $data = json_decode($response, true);
    if ($httpCode >= 200 && $httpCode < 300 && !empty($data['ok'])) {
        return ['ok' => true];
    }

    $relayErr = is_array($data) ? ($data['error'] ?? $response) : $response;
    error_log('[NexTradeAI Email Relay] HTTP ' . $httpCode . ': ' . $relayErr);
    return ['ok' => false, 'error' => 'Relay failed: ' . $relayErr];
}
