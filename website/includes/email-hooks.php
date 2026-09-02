<?php
/**
 * NexTradeAI — connect site events to branded email templates.
 * All handlers log failures; they never block the parent HTTP request.
 */

function nextrade_email_bootstrap(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $bootstrap = null;
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/') : '';
    if ($docRoot !== '' && is_readable($docRoot . '/includes/bootstrap.php')) {
        $bootstrap = $docRoot . '/includes/bootstrap.php';
    } elseif (is_readable(__DIR__ . '/bootstrap.php')) {
        $bootstrap = __DIR__ . '/bootstrap.php';
    }

    if ($bootstrap === null) {
        throw new RuntimeException('NexTradeAI email bootstrap not found');
    }

    require_once $bootstrap;
    auraai_email_bootstrap();
    $ready = true;
}

/** @return bool true when the template was accepted by the mailer */
function nextrade_email_dispatch(string $event, callable $sender): bool
{
    try {
        nextrade_email_bootstrap();
        $ok = (bool) $sender();
        if (!$ok) {
            $detail = function_exists('auraai_email_last_error') ? auraai_email_last_error() : '';
            error_log('[NexTradeAI Email] ' . $event . ' failed' . ($detail !== '' ? ': ' . $detail : ''));
        }
        return $ok;
    } catch (Throwable $e) {
        $GLOBALS['_auraai_email_last_error'] = $e->getMessage();
        error_log('[NexTradeAI Email] ' . $event . ': ' . $e->getMessage());
        return false;
    }
}

function nextrade_email_mentor_signup(string $email, string $displayName, string $phone = ''): void
{
    $email = trim($email);
    $displayName = trim($displayName);
    $phone = trim($phone);
    if ($email === '') {
        return;
    }

    nextrade_email_dispatch(
        'mentor_signup_pending',
        static fn (): bool => auraai_email_mentor_signup_pending($email, $displayName)
    );
    nextrade_email_dispatch(
        'mentor_signup_admin',
        static fn (): bool => auraai_email_mentor_signup_admin($email, $displayName, $phone)
    );
}

function nextrade_email_mentor_status(string $email, string $displayName, string $status): void
{
    $email = trim($email);
    if ($email === '') {
        return;
    }

    nextrade_email_dispatch(
        'mentor_status_' . strtolower(trim($status)),
        static fn (): bool => auraai_email_mentor_status_changed($email, $displayName, $status)
    );
}

function nextrade_email_member_payment(string $email, string $source, bool $scanner = false): void
{
    $email = trim($email);
    if ($email === '') {
        return;
    }

    nextrade_email_dispatch(
        'member_payment_' . strtolower($source),
        static fn (): bool => auraai_email_member_payment_success($email, $source, $scanner)
    );
}

function nextrade_email_scanner_payment(string $email, bool $memberFound): void
{
    $email = trim($email);
    if ($email === '') {
        return;
    }

    nextrade_email_dispatch(
        'scanner_payment',
        static fn (): bool => auraai_email_scanner_payment_result($email, $memberFound)
    );
}

function nextrade_email_license_key(
    string $toEmail,
    string $licenseKey,
    string $eaName = '',
    string $mentorName = ''
): bool {
    $toEmail = trim($toEmail);
    $licenseKey = trim($licenseKey);
    if ($toEmail === '' || $licenseKey === '') {
        return false;
    }

    return nextrade_email_dispatch(
        'license_key',
        static fn (): bool => auraai_email_license_key($toEmail, $licenseKey, $eaName, $mentorName)
    );
}

function nextrade_email_password_reset(string $email, string $resetUrl, string $displayName = ''): bool
{
    $email = trim($email);
    if ($email === '' || trim($resetUrl) === '') {
        return false;
    }

    return nextrade_email_dispatch(
        'password_reset',
        static fn (): bool => auraai_email_password_reset($email, $resetUrl, $displayName)
    );
}

function nextrade_email_password_reset_confirmation(string $email): bool
{
    $email = trim($email);
    if ($email === '') {
        return false;
    }

    return nextrade_email_dispatch(
        'password_reset_confirmation',
        static fn (): bool => auraai_email_password_reset_confirmation($email)
    );
}
