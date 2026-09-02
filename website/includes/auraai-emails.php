<?php
/**
 * NexTradeAI — branded HTML email templates + send helpers.
 */

require_once __DIR__ . '/mailer.php';

function auraai_email_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function auraai_email_wrap(string $title, string $contentHtml, ?string $ctaLabel = null, ?string $ctaUrl = null): string
{
    $logo = auraai_email_escape(defined('EMAIL_LOGO_URL') ? EMAIL_LOGO_URL : LOGO_URL);
    $brand = auraai_email_escape(GMAIL_FROM_NAME);
    $heading = auraai_email_escape($title);
    $year = date('Y');

    $ctaHtml = '';
    if ($ctaLabel && $ctaUrl) {
        $ctaLabelEsc = auraai_email_escape($ctaLabel);
        $ctaHtml = '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:28px auto 0;">'
            . '<tr><td bgcolor="#00A8FF" style="border-radius:10px;background-color:#00A8FF;">'
            . '<a href="' . auraai_email_escape($ctaUrl) . '" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:700;text-decoration:none;">'
            . '<span style="color:#ffffff !important;">' . $ctaLabelEsc . '</span></a></td></tr></table>';
    }

    return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="color-scheme" content="light dark"><meta name="supported-color-schemes" content="light dark">'
        . '<title>' . $heading . '</title>'
        . '<style type="text/css">'
        . '.email-heading,.email-heading span{color:#ffffff !important;-webkit-text-fill-color:#ffffff !important;}'
        . '.email-body,.email-body p,.email-body td{color:#1f2937 !important;-webkit-text-fill-color:#1f2937 !important;}'
        . '.email-muted{color:#6b7280 !important;-webkit-text-fill-color:#6b7280 !important;}'
        . '.email-btn span{color:#ffffff !important;-webkit-text-fill-color:#ffffff !important;}'
        . '@media (prefers-color-scheme: dark){.email-body,.email-body p,.email-body td{color:#e5e7eb !important;-webkit-text-fill-color:#e5e7eb !important;}}'
        . '</style></head>'
        . '<body style="margin:0;padding:0;background:#eef1f5;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#eef1f5" style="background:#eef1f5;padding:32px 16px;">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#ffffff" style="max-width:560px;background:#ffffff;border:1px solid #d1d5db;border-radius:16px;overflow:hidden;">'
        . '<tr><td bgcolor="#111827" style="padding:28px 28px 16px;text-align:center;background-color:#111827;">'
        . '<img src="' . $logo . '" alt="' . $brand . '" width="64" height="64" style="display:block;margin:0 auto 16px;border-radius:12px;border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;">'
        . '<p class="email-heading" style="margin:0;font-size:22px;font-weight:800;letter-spacing:-0.3px;line-height:1.3;color:#ffffff;">'
        . '<span style="color:#ffffff !important;-webkit-text-fill-color:#ffffff !important;">' . $heading . '</span></p>'
        . '</td></tr>'
        . '<tr><td class="email-body" bgcolor="#ffffff" style="padding:8px 28px 28px;background-color:#ffffff;color:#1f2937;font-size:15px;line-height:1.65;">'
        . $contentHtml . $ctaHtml
        . '<p class="email-muted" style="margin:28px 0 0;color:#6b7280;font-size:12px;line-height:1.5;">'
        . 'You received this email because of activity on your NexTradeAI account.<br>'
        . '&copy; ' . $year . ' NexTradeAI. All rights reserved.</p>'
        . '</td></tr></table></td></tr></table></body></html>';
}

function auraai_email_send(string $to, string $subject, string $title, string $contentHtml, ?string $ctaLabel = null, ?string $ctaUrl = null): bool
{
    $html = auraai_email_wrap($title, $contentHtml, $ctaLabel, $ctaUrl);
    $result = auraai_smtp_send($to, $subject, $html);
    if (!$result['ok']) {
        $GLOBALS['_auraai_email_last_error'] = (string) ($result['error'] ?? 'unknown');
        error_log('[NexTradeAI Email] Failed to send to ' . $to . ': ' . $GLOBALS['_auraai_email_last_error']);
    } else {
        $GLOBALS['_auraai_email_last_error'] = '';
    }
    return $result['ok'];
}

function auraai_email_last_error(): string
{
    return (string) ($GLOBALS['_auraai_email_last_error'] ?? '');
}

/** Mentor signed up — pending review */
function auraai_email_mentor_signup_pending(string $email, string $displayName): bool
{
    $name = auraai_email_escape($displayName ?: 'Mentor');
    $content = '<p style="margin:0 0 14px;color:#e4e4e7;">Hi <strong>' . $name . '</strong>,</p>'
        . '<p style="margin:0 0 14px;">Thank you for registering as an NexTradeAI mentor. Your account is <strong style="color:#fbbf24;">pending review</strong>.</p>'
        . '<p style="margin:0;">Our team will verify your details and activate your admin panel. You will receive another email once your account is approved.</p>';

    return auraai_email_send(
        $email,
        'NexTradeAI — Mentor registration received',
        'Registration received',
        $content,
        'Visit NexTradeAI',
        AURAAI_APP_URL
    );
}

/** Notify admin of new mentor signup */
function auraai_email_mentor_signup_admin(string $email, string $displayName, string $phone = ''): bool
{
    $content = '<p style="margin:0 0 14px;">A new mentor has signed up and is awaiting approval.</p>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:16px 0;background:#111;border:1px solid #333;border-radius:10px;">'
        . '<tr><td style="padding:12px 16px;color:#a1a1aa;font-size:13px;">Display name</td>'
        . '<td style="padding:12px 16px;color:#fff;font-size:13px;font-weight:600;">' . auraai_email_escape($displayName) . '</td></tr>'
        . '<tr><td style="padding:12px 16px;color:#a1a1aa;font-size:13px;">Email</td>'
        . '<td style="padding:12px 16px;color:#fff;font-size:13px;">' . auraai_email_escape($email) . '</td></tr>'
        . '<tr><td style="padding:12px 16px;color:#a1a1aa;font-size:13px;">WhatsApp</td>'
        . '<td style="padding:12px 16px;color:#fff;font-size:13px;">' . auraai_email_escape($phone ?: '—') . '</td></tr>'
        . '</table>'
        . '<p style="margin:0;">Review and set status to <strong>Active</strong>, <strong>Pending</strong>, or <strong>Blocked</strong> in the admin users panel.</p>';

    return auraai_email_send(
        ADMIN_NOTIFY_EMAIL,
        'NexTradeAI — New mentor signup — ' . $displayName,
        'New mentor signup',
        $content,
        'Open admin panel',
        AURAAI_ADMIN_LOGIN
    );
}

/** Mentor account status changed (active / pending / blocked) */
function auraai_email_mentor_status_changed(string $email, string $displayName, string $status): bool
{
    $statusNorm = strtolower(trim($status));
    $name = auraai_email_escape($displayName ?: 'Mentor');

    $messages = [
        'active' => [
            'subject' => 'NexTradeAI — Your mentor account is active',
            'title' => 'Account activated',
            'body' => '<p style="margin:0 0 14px;">Hi <strong>' . $name . '</strong>,</p>'
                . '<p style="margin:0 0 14px;">Great news — your NexTradeAI mentor account is now <strong style="color:#22c55e;">active</strong>.</p>'
                . '<p style="margin:0;">You can sign in to your admin panel, upload your automation, generate license keys, and start sending signals to your members.</p>',
            'cta' => 'Sign in to admin',
            'url' => AURAAI_ADMIN_LOGIN,
        ],
        'pending' => [
            'subject' => 'NexTradeAI — Account pending review',
            'title' => 'Account pending',
            'body' => '<p style="margin:0 0 14px;">Hi <strong>' . $name . '</strong>,</p>'
                . '<p style="margin:0 0 14px;">Your mentor account status has been set to <strong style="color:#fbbf24;">pending</strong>.</p>'
                . '<p style="margin:0;">We are still reviewing your application. You will be notified when your account is activated.</p>',
            'cta' => 'Visit NexTradeAI',
            'url' => AURAAI_APP_URL,
        ],
        'blocked' => [
            'subject' => 'NexTradeAI — Account access restricted',
            'title' => 'Account blocked',
            'body' => '<p style="margin:0 0 14px;">Hi <strong>' . $name . '</strong>,</p>'
                . '<p style="margin:0 0 14px;">Your NexTradeAI mentor account has been <strong style="color:#ef4444;">blocked</strong>.</p>'
                . '<p style="margin:0;">If you believe this is a mistake, please contact support at <a href="mailto:' . NEXTRADE_SUPPORT_EMAIL . '" style="color:#60a5fa;">' . NEXTRADE_SUPPORT_EMAIL . '</a>.</p>',
            'cta' => null,
            'url' => null,
        ],
    ];

    $tpl = $messages[$statusNorm] ?? [
        'subject' => 'NexTradeAI — Account status updated',
        'title' => 'Status updated',
        'body' => '<p style="margin:0 0 14px;">Hi <strong>' . $name . '</strong>,</p>'
            . '<p style="margin:0;">Your account status is now: <strong>' . auraai_email_escape($status) . '</strong>.</p>',
        'cta' => 'Sign in to admin',
        'url' => AURAAI_ADMIN_LOGIN,
    ];

    return auraai_email_send(
        $email,
        $tpl['subject'],
        $tpl['title'],
        $tpl['body'],
        $tpl['cta'],
        $tpl['url']
    );
}

/** Member joined via Whop or Paystack (shop membership) */
function auraai_email_member_joined(string $email, string $source = 'payment', bool $isScanner = false): bool
{
    $sourceLabel = auraai_email_escape(ucfirst(strtolower($source)));
    $content = '<p style="margin:0 0 14px;">Hi,</p>'
        . '<p style="margin:0 0 14px;">Your NexTradeAI membership payment via <strong>' . $sourceLabel . '</strong> was successful.</p>'
        . '<p style="margin:0 0 14px;">Your email <strong>' . auraai_email_escape($email) . '</strong> is now linked to your membership. Open the NexTradeAI app and sign in with this email to get started.</p>';

    if ($isScanner) {
        $content .= '<p style="margin:0;">Your <strong>AI Scanner</strong> access has also been activated — open the app and go to the AI Scanner tab.</p>';
    } else {
        $content .= '<p style="margin:0;">Download the app, connect your MT5 account, and enter your license key from your mentor.</p>';
    }

    return auraai_email_send(
        $email,
        'NexTradeAI — Welcome, your membership is active',
        'Welcome to NexTradeAI',
        $content,
        'Get started',
        AURAAI_APP_URL
    );
}

/** AI Scanner unlocked (scanner = 1 on members table) */
function auraai_email_scanner_activated(string $email): bool
{
    $content = '<p style="margin:0 0 14px;">Hi,</p>'
        . '<p style="margin:0 0 14px;">Your <strong style="color:#8b5cf6;">AI Scanner</strong> is now unlocked for <strong>' . auraai_email_escape($email) . '</strong>.</p>'
        . '<p style="margin:0 0 14px;">Upload a chart screenshot and get instant AI analysis with entry, stop loss, and take profit levels.</p>'
        . '<p style="margin:0;">Open the NexTradeAI app, go to <strong>AI Scanner</strong>, and start analyzing charts.</p>';

    return auraai_email_send(
        $email,
        'NexTradeAI — AI Scanner activated',
        'AI Scanner unlocked',
        $content,
        'Open NexTradeAI',
        AURAAI_APP_URL
    );
}

/** License key sent to member/client */
function auraai_email_license_key(
    string $toEmail,
    string $licenseKey,
    string $eaName = '',
    string $mentorName = ''
): bool {
    $key = auraai_email_escape($licenseKey);
    $ea = auraai_email_escape($eaName ?: 'NexTradeAI');
    $mentor = $mentorName !== '' ? auraai_email_escape($mentorName) : '';

    $content = '<p style="margin:0 0 14px;">Hi,</p>';
    if ($mentor !== '') {
        $content .= '<p style="margin:0 0 14px;">' . $mentor . ' has shared an NexTradeAI license key with you.</p>';
    }
    $content .= '<p style="margin:0 0 14px;">Use this license key in the NexTradeAI app to activate <strong>' . $ea . '</strong>:</p>'
        . '<div style="margin:16px 0;padding:16px 20px;background:#111;border:1px solid #00A8FF;border-radius:10px;text-align:center;">'
        . '<span style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:18px;font-weight:700;color:#60a5fa;letter-spacing:1px;">' . $key . '</span></div>'
        . '<p style="margin:0 0 14px;color:#a1a1aa;font-size:13px;">Keep this key private. Each key can only be used on one device at a time.</p>'
        . '<p style="margin:0;">Download the NexTradeAI app, tap <strong>Add License</strong>, and paste your key.</p>';

    return auraai_email_send(
        $toEmail,
        'NexTradeAI — Your license key',
        'Your license key',
        $content,
        'Download NexTradeAI',
        AURAAI_APP_URL
    );
}

/**
 * Convenience: call after member payment webhook activates account.
 * Pass $scanner=true when members.scanner was set to 1.
 */
function auraai_email_member_payment_success(string $email, string $source, bool $scanner = false): bool
{
    if ($email === '') {
        return false;
    }
    $ok = auraai_email_member_joined($email, $source, $scanner);
    if ($scanner) {
        $ok = auraai_email_scanner_activated($email) && $ok;
    }
    return $ok;
}

/** Affiliate registered — welcome + referral link */
function auraai_email_affiliate_welcome(string $email, string $fullName, string $shopLink, bool $fromMentor = false): bool
{
    $name = auraai_email_escape($fullName ?: 'Affiliate');
    $link = auraai_email_escape($shopLink);
    $intro = $fromMentor
        ? 'Your NexTradeAI mentor account is linked to the affiliate program.'
        : 'Welcome to the NexTradeAI affiliate program.';

    $content = '<p style="margin:0 0 14px;color:#1f2937;">Hi <strong>' . $name . '</strong>,</p>'
        . '<p style="margin:0 0 14px;color:#1f2937;">' . auraai_email_escape($intro) . '</p>'
        . '<p style="margin:0 0 14px;color:#1f2937;">Share your personal referral link. When someone pays for membership through your link, you earn commission automatically.</p>'
        . '<div style="margin:16px 0;padding:14px 16px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:10px;word-break:break-all;">'
        . '<a href="' . $link . '" style="color:#00B8D4;font-size:13px;">' . $link . '</a></div>'
        . '<p style="margin:0;color:#1f2937;">Sign in to your affiliate dashboard to track clicks, sales, and request withdrawals.</p>';

    return auraai_email_send(
        $email,
        'NexTradeAI — Welcome to the affiliate program',
        'Affiliate account ready',
        $content,
        'Open affiliate dashboard',
        AURAAI_AFFILIATE_URL
    );
}

/** Affiliate earned commission on a confirmed sale */
function auraai_email_affiliate_commission_earned(
    string $email,
    string $fullName,
    string $commissionFormatted,
    string $productLabel,
    float $ratePct
): bool {
    $name = auraai_email_escape($fullName ?: 'Affiliate');
    $commission = auraai_email_escape($commissionFormatted);
    $product = auraai_email_escape($productLabel);
    $rate = auraai_email_escape(number_format($ratePct, 1) . '%');

    $content = '<p style="margin:0 0 14px;color:#1f2937;">Hi <strong>' . $name . '</strong>,</p>'
        . '<p style="margin:0 0 14px;color:#1f2937;">You earned a new affiliate commission from a confirmed member payment.</p>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:16px 0;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:10px;">'
        . '<tr><td style="padding:12px 16px;color:#6b7280;font-size:13px;">Product</td>'
        . '<td style="padding:12px 16px;color:#111827;font-size:13px;font-weight:600;">' . $product . '</td></tr>'
        . '<tr><td style="padding:12px 16px;color:#6b7280;font-size:13px;">Commission rate</td>'
        . '<td style="padding:12px 16px;color:#111827;font-size:13px;">' . $rate . '</td></tr>'
        . '<tr><td style="padding:12px 16px;color:#6b7280;font-size:13px;">Commission earned</td>'
        . '<td style="padding:12px 16px;color:#16a34a;font-size:15px;font-weight:700;">' . $commission . '</td></tr>'
        . '</table>'
        . '<p style="margin:0;color:#1f2937;">Your balance has been updated. View all commissions and request a payout from your affiliate dashboard.</p>';

    return auraai_email_send(
        $email,
        'NexTradeAI — You earned ' . $commissionFormatted . ' commission',
        'Commission earned',
        $content,
        'View affiliate dashboard',
        AURAAI_AFFILIATE_URL
    );
}

/** Affiliate submitted a withdrawal request */
function auraai_email_affiliate_withdrawal_requested(
    string $email,
    string $fullName,
    string $amountFormatted,
    string $methodLabel
): bool {
    $name = auraai_email_escape($fullName ?: 'Affiliate');
    $amount = auraai_email_escape($amountFormatted);
    $method = auraai_email_escape($methodLabel);

    $content = '<p style="margin:0 0 14px;">Hi <strong>' . $name . '</strong>,</p>'
        . '<p style="margin:0 0 14px;">We received your withdrawal request for <strong style="color:#22c55e;">' . $amount . '</strong> via <strong>' . $method . '</strong>.</p>'
        . '<p style="margin:0;">Our team will review and process it. You will receive another email when the status changes.</p>';

    return auraai_email_send(
        $email,
        'NexTradeAI — Withdrawal request received',
        'Withdrawal requested',
        $content,
        'View affiliate dashboard',
        AURAAI_AFFILIATE_URL
    );
}

/** Notify admin of affiliate withdrawal request */
function auraai_email_affiliate_withdrawal_admin(
    string $fullName,
    string $email,
    string $shopLink,
    string $amountFormatted,
    string $methodLabel,
    string $payoutDetails
): bool {
    $content = '<p style="margin:0 0 14px;">An affiliate has requested a withdrawal.</p>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:16px 0;background:#111;border:1px solid #333;border-radius:10px;">'
        . '<tr><td style="padding:12px 16px;color:#a1a1aa;font-size:13px;">Affiliate</td>'
        . '<td style="padding:12px 16px;color:#fff;font-size:13px;font-weight:600;">' . auraai_email_escape($fullName) . '</td></tr>'
        . '<tr><td style="padding:12px 16px;color:#a1a1aa;font-size:13px;">Email</td>'
        . '<td style="padding:12px 16px;color:#fff;font-size:13px;">' . auraai_email_escape($email) . '</td></tr>'
        . '<tr><td style="padding:12px 16px;color:#a1a1aa;font-size:13px;">Amount</td>'
        . '<td style="padding:12px 16px;color:#22c55e;font-size:13px;font-weight:700;">' . auraai_email_escape($amountFormatted) . '</td></tr>'
        . '<tr><td style="padding:12px 16px;color:#a1a1aa;font-size:13px;">Method</td>'
        . '<td style="padding:12px 16px;color:#fff;font-size:13px;">' . auraai_email_escape($methodLabel) . '</td></tr>'
        . '<tr><td style="padding:12px 16px;color:#a1a1aa;font-size:13px;">Payout details</td>'
        . '<td style="padding:12px 16px;color:#fff;font-size:13px;word-break:break-all;">' . auraai_email_escape($payoutDetails) . '</td></tr>'
        . '</table>'
        . '<p style="margin:0;">Review and update the request in the admin affiliates panel.</p>';

    return auraai_email_send(
        ADMIN_NOTIFY_EMAIL,
        'NexTradeAI — Affiliate withdrawal request — ' . $fullName,
        'Affiliate withdrawal',
        $content,
        'Open affiliates admin',
        AURAAI_ADMIN_LOGIN . 'home/affiliates.php'
    );
}

/** Affiliate withdrawal status updated (paid / processing / rejected) */
function auraai_email_affiliate_withdrawal_status(
    string $email,
    string $fullName,
    string $amountFormatted,
    string $status,
    string $adminNotes = ''
): bool {
    $name = auraai_email_escape($fullName ?: 'Affiliate');
    $amount = auraai_email_escape($amountFormatted);
    $statusNorm = strtolower(trim($status));
    $statusLabel = auraai_email_escape(ucfirst($statusNorm));
    $notes = trim($adminNotes);

    $statusColor = match ($statusNorm) {
        'paid' => '#22c55e',
        'processing' => '#60a5fa',
        'rejected' => '#ef4444',
        default => '#fbbf24',
    };

    $content = '<p style="margin:0 0 14px;">Hi <strong>' . $name . '</strong>,</p>'
        . '<p style="margin:0 0 14px;">Your withdrawal request for <strong>' . $amount . '</strong> is now '
        . '<strong style="color:' . $statusColor . ';">' . $statusLabel . '</strong>.</p>';

    if ($notes !== '') {
        $content .= '<p style="margin:0 0 14px;color:#a1a1aa;font-size:13px;">Note from admin: '
            . auraai_email_escape($notes) . '</p>';
    }

    $content .= '<p style="margin:0;">Sign in to your affiliate dashboard for full payout history.</p>';

    return auraai_email_send(
        $email,
        'NexTradeAI — Withdrawal ' . ucfirst($statusNorm),
        'Withdrawal update',
        $content,
        'View affiliate dashboard',
        AURAAI_AFFILIATE_URL
    );
}

/** Admin forgot-password — send reset link */
function auraai_email_password_reset(string $email, string $resetUrl, string $displayName = ''): bool
{
    $name = auraai_email_escape($displayName ?: 'Admin');
    $content = '<p style="margin:0 0 14px;color:#1f2937;">Hi <strong>' . $name . '</strong>,</p>'
        . '<p style="margin:0 0 14px;color:#1f2937;">We received a request to reset your NexTradeAI admin password.</p>'
        . '<p style="margin:0 0 14px;color:#1f2937;">Click the button below to choose a new password. This link expires in <strong>1 hour</strong>.</p>'
        . '<p style="margin:0;color:#6b7280;font-size:13px;">If you did not request this, you can ignore this email. Your password will stay the same.</p>';

    return auraai_email_send(
        $email,
        'NexTradeAI — Reset your password',
        'Reset your password',
        $content,
        'Reset password',
        $resetUrl
    );
}

/** Admin password successfully changed */
function auraai_email_password_reset_confirmation(string $email): bool
{
    $content = '<p style="margin:0 0 14px;color:#1f2937;">Hi,</p>'
        . '<p style="margin:0 0 14px;color:#1f2937;">Your NexTradeAI admin password for <strong>' . auraai_email_escape($email) . '</strong> was changed successfully.</p>'
        . '<p style="margin:0;color:#1f2937;">You can sign in with your new password. If you did not make this change, contact support immediately at '
        . '<a href="mailto:' . NEXTRADE_SUPPORT_EMAIL . '" style="color:#00B8D4;">' . NEXTRADE_SUPPORT_EMAIL . '</a>.</p>';

    return auraai_email_send(
        $email,
        'NexTradeAI — Password updated',
        'Password updated',
        $content,
        'Sign in to admin',
        AURAAI_ADMIN_LOGIN
    );
}

/**
 * AI Scanner payment webhook result.
 * $memberFound=true when an existing paid member row was updated with scanner=1.
 */
function auraai_email_scanner_payment_result(string $email, bool $memberFound): bool
{
    if ($memberFound) {
        return auraai_email_scanner_activated($email);
    }

    $content = '<p style="margin:0 0 14px;color:#1f2937;">Hi,</p>'
        . '<p style="margin:0 0 14px;color:#1f2937;">We received your <strong>AI Scanner</strong> payment for <strong>'
        . auraai_email_escape($email) . '</strong>.</p>'
        . '<p style="margin:0 0 14px;color:#1f2937;">We could not find an active NexTradeAI membership for this email yet. Complete your VPS membership first using the same email address — your scanner access will unlock automatically once membership is active.</p>'
        . '<p style="margin:0;color:#1f2937;">Need help? Contact <a href="mailto:' . NEXTRADE_SUPPORT_EMAIL . '" style="color:#00B8D4;">' . NEXTRADE_SUPPORT_EMAIL . '</a>.</p>';

    return auraai_email_send(
        $email,
        'NexTradeAI — AI Scanner payment received',
        'Scanner payment received',
        $content,
        'Visit NexTradeAI shop',
        AURAAI_SHOP_URL
    );
}
