<?php
/**
 * NexTradeAI — cPanel MySQL configuration (no secrets in this file).
 *
 * Credentials live in ~/nextradeai-secrets.php (see private/nextradeai-secrets.php.example).
 * All PHP entry points should use admin/php-includes/connect.php or auraai_db_connect().
 */

function auraai_db_load_secrets(): void
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

function auraai_db_constant(string $nextradeai, string $legacy): mixed
{
    if (defined($nextradeai)) {
        return constant($nextradeai);
    }
    if (defined($legacy)) {
        return constant($legacy);
    }

    return null;
}

function auraai_db_config(): array
{
    auraai_db_load_secrets();

    return [
        'host' => auraai_db_constant('NEXTRADEAI_DB_HOST', 'AURAAI_DB_HOST') ?? 'localhost',
        'user' => auraai_db_constant('NEXTRADEAI_DB_USER', 'AURAAI_DB_USER') ?? 'nextradeai',
        'pass' => auraai_db_constant('NEXTRADEAI_DB_PASS', 'AURAAI_DB_PASS') ?? '',
        'name' => auraai_db_constant('NEXTRADEAI_DB_NAME', 'AURAAI_DB_NAME') ?? 'nextradeai',
    ];
}

function auraai_db_connect()
{
    $cfg = auraai_db_config();
    $con = mysqli_connect($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['name']);
    if (!$con) {
        throw new RuntimeException('Database connection failed');
    }

    return $con;
}
