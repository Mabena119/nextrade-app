<?php
/**
 * NexTradeAI — Gmail / PHPMailer configuration (no secrets in this file).
 *
 * Secrets live outside public_html in ~/nextradeai-secrets.php (see private/nextradeai-secrets.php.example).
 */

require_once __DIR__ . '/site-config.php';

function auraai_email_load_secrets(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $candidates = [
        getenv('NEXTRADEAI_SECRETS_FILE') ?: '',
        getenv('AURAAI_SECRETS_FILE') ?: '',
        (getenv('HOME') ?: '') . '/nextradeai-secrets.php',
        (getenv('HOME') ?: '') . '/auraai-secrets.php',
        dirname(__DIR__, 2) . '/nextradeai-secrets.php',
        dirname(__DIR__, 2) . '/auraai-secrets.php',
    ];

    foreach ($candidates as $path) {
        if ($path !== '' && is_readable($path)) {
            require $path;
            return;
        }
    }
}

auraai_email_load_secrets();

if (!defined('GMAIL_USER') || GMAIL_USER === '') {
    throw new RuntimeException('GMAIL_USER not configured. Create ~/nextradeai-secrets.php on the server.');
}
if (!defined('GMAIL_PASS') || GMAIL_PASS === '') {
    throw new RuntimeException('GMAIL_PASS not configured. Create ~/nextradeai-secrets.php on the server.');
}
if (!defined('GMAIL_FROM_NAME')) {
    define('GMAIL_FROM_NAME', 'NexTradeAI');
}
if (!defined('LOGO_URL')) {
    define('LOGO_URL', NEXTRADE_SITE_URL . '/assets/img/sitelogo.png');
}
if (!defined('EMAIL_LOGO_URL')) {
    define('EMAIL_LOGO_URL', LOGO_URL);
}

define('MAIL_FROM_EMAIL', GMAIL_USER);
define('MAIL_REPLY_TO', GMAIL_USER);
define('ADMIN_NOTIFY_EMAIL', GMAIL_USER);

define('AURAAI_EMAIL_RELAY_URL', NEXTRADE_APP_URL . '/api/send-email');
if (!defined('AURAAI_EMAIL_RELAY_SECRET') || AURAAI_EMAIL_RELAY_SECRET === '') {
    throw new RuntimeException('AURAAI_EMAIL_RELAY_SECRET not configured. Create ~/nextradeai-secrets.php on the server.');
}
