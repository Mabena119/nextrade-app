<?php
/**
 * NexTradeAI — input security helpers (CSRF, rate limits, validation, headers).
 */

function auraai_sec_storage_dir(): string
{
    $dir = sys_get_temp_dir() . '/auraai-security';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    return $dir;
}

/** Send baseline security headers (safe for HTML + JSON endpoints). */
function auraai_sec_send_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

/** Harden session cookie flags before session_start(). */
function auraai_sec_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function auraai_sec_require_method(string $method): void
{
    if (strcasecmp($_SERVER['REQUEST_METHOD'] ?? '', $method) !== 0) {
        http_response_code(405);
        header('Allow: ' . strtoupper($method));
        exit('Method not allowed');
    }
}

function auraai_sec_client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return preg_replace('/[^0-9a-fA-F:.]/', '', $ip) ?: '0.0.0.0';
}

/** Simple file-based rate limiter. Returns true if allowed. */
function auraai_sec_rate_limit(string $bucket, int $maxAttempts, int $windowSeconds): bool
{
    $key = hash('sha256', $bucket . '|' . auraai_sec_client_ip());
    $file = auraai_sec_storage_dir() . '/rl_' . $key . '.json';
    $now = time();
    $data = ['count' => 0, 'reset' => $now + $windowSeconds];

    if (is_readable($file)) {
        $raw = @file_get_contents($file);
        $decoded = $raw ? json_decode($raw, true) : null;
        if (is_array($decoded) && isset($decoded['reset'], $decoded['count'])) {
            $data = $decoded;
        }
    }

    if ($now > (int) $data['reset']) {
        $data = ['count' => 0, 'reset' => $now + $windowSeconds];
    }

    $data['count'] = (int) $data['count'] + 1;
    @file_put_contents($file, json_encode($data), LOCK_EX);

    return $data['count'] <= $maxAttempts;
}

function auraai_sec_rate_limit_or_exit(string $bucket, int $maxAttempts, int $windowSeconds, string $message = 'Too many requests. Please try again later.'): void
{
    if (!auraai_sec_rate_limit($bucket, $maxAttempts, $windowSeconds)) {
        http_response_code(429);
        if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
            || stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => $message]);
        } else {
            echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        }
        exit;
    }
}

function auraai_sec_csrf_token(): string
{
    auraai_sec_session_start();
    if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function auraai_sec_csrf_field(): string
{
    $token = auraai_sec_csrf_token();
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

function auraai_sec_csrf_validate(?string $token = null): bool
{
    auraai_sec_session_start();
    $token = $token ?? ($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $expected = $_SESSION['_csrf_token'] ?? '';
    return is_string($token) && is_string($expected) && $expected !== '' && hash_equals($expected, $token);
}

function auraai_sec_csrf_require(?string $token = null): void
{
    if (!auraai_sec_csrf_validate($token)) {
        http_response_code(403);
        if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Invalid security token. Refresh the page and try again.']);
        } else {
            echo 'Invalid security token. Please refresh and try again.';
        }
        exit;
    }
}

/** Honeypot — bots often fill hidden fields. */
function auraai_sec_honeypot_check(string $field = 'website'): bool
{
    $value = trim((string) ($_POST[$field] ?? ''));
    return $value === '';
}

function auraai_sec_honeypot_require(string $field = 'website'): void
{
    if (!auraai_sec_honeypot_check($field)) {
        http_response_code(400);
        exit('Bad request');
    }
}

function auraai_sec_escape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function auraai_sec_string($value, int $maxLen = 255, int $minLen = 0): ?string
{
    if (!is_string($value) && !is_numeric($value)) {
        return null;
    }
    $value = trim((string) $value);
    $len = strlen($value);
    if ($len < $minLen || $len > $maxLen) {
        return null;
    }
    // Strip null bytes and control chars except common whitespace
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    return $value;
}

function auraai_sec_email($value): ?string
{
    $value = auraai_sec_string($value, 254, 3);
    if ($value === null) {
        return null;
    }
    $value = strtolower($value);
    return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
}

function auraai_sec_int($value, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): ?int
{
    if (is_string($value) && !preg_match('/^-?\d+$/', trim($value))) {
        return null;
    }
    if (!is_numeric($value)) {
        return null;
    }
    $int = (int) $value;
    return ($int >= $min && $int <= $max) ? $int : null;
}

function auraai_sec_enum($value, array $allowed): ?string
{
    if (!is_string($value) && !is_numeric($value)) {
        return null;
    }
    $value = (string) $value;
    return in_array($value, $allowed, true) ? $value : null;
}

function auraai_sec_password($value, int $minLen = 6, int $maxLen = 50): ?string
{
    $value = auraai_sec_string($value, $maxLen, $minLen);
    if ($value === null) {
        return null;
    }
    return $value;
}

function auraai_sec_license_key($value): ?string
{
    $value = auraai_sec_string($value, 128, 4);
    if ($value === null) {
        return null;
    }
    if (!preg_match('/^[A-Za-z0-9\-_]+$/', $value)) {
        return null;
    }
    return $value;
}

/** Parse JSON body with size cap. */
function auraai_sec_json_input(int $maxBytes = 65536): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || strlen($raw) > $maxBytes) {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/** Detect file MIME without requiring the fileinfo extension (missing on some cPanel PHP builds). */
function auraai_sec_detect_mime(string $path): string
{
    if (!is_file($path)) {
        return '';
    }
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);
        if (is_string($mime) && $mime !== '') {
            return strtolower($mime);
        }
    }
    if (function_exists('mime_content_type')) {
        $mime = @mime_content_type($path);
        if (is_string($mime) && $mime !== '') {
            return strtolower($mime);
        }
    }
    if (function_exists('getimagesize')) {
        $info = @getimagesize($path);
        if (is_array($info) && !empty($info['mime'])) {
            return strtolower((string) $info['mime']);
        }
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $map = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'ex5' => 'application/octet-stream',
        'ex4' => 'application/octet-stream',
        'mq5' => 'text/plain',
        'zip' => 'application/zip',
    ];
    return $map[$ext] ?? 'application/octet-stream';
}

/**
 * Validate uploaded file (extension + MIME + size).
 * @return array{ok:bool,path?:string,error?:string}
 */
function auraai_sec_validate_upload(array $file, string $destDir, array $allowedExt, int $maxBytes = 5242880): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload failed'];
    }
    if (($file['size'] ?? 0) > $maxBytes) {
        return ['ok' => false, 'error' => 'File too large'];
    }
    $original = basename((string) ($file['name'] ?? 'file'));
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return ['ok' => false, 'error' => 'Invalid file type'];
    }
    $mime = auraai_sec_detect_mime((string) ($file['tmp_name'] ?? ''));
    $allowedMimes = [
        'jpg' => ['image/jpeg', 'image/jpg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/jpg', 'image/pjpeg'],
        'png' => ['image/png', 'image/x-png'],
        'ex5' => ['application/octet-stream', 'application/x-msdownload', 'application/x-executable'],
        'ex4' => ['application/octet-stream', 'application/x-msdownload'],
        'mq5' => ['text/plain', 'application/octet-stream'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
    ];
    if (isset($allowedMimes[$ext]) && !in_array($mime, $allowedMimes[$ext], true)) {
        return ['ok' => false, 'error' => 'Invalid file content'];
    }
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }
    $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
    $destPath = rtrim($destDir, '/') . '/' . $safeName;
    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['ok' => false, 'error' => 'Could not save upload'];
    }
    return ['ok' => true, 'path' => $destPath];
}

/** Paystack webhook HMAC verification. */
function auraai_sec_paystack_verify(string $rawBody, string $secret): bool
{
    $signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
    if ($signature === '' || $secret === '') {
        return false;
    }
    $expected = hash_hmac('sha512', $rawBody, $secret);
    return hash_equals($expected, $signature);
}

/** True when an admin dashboard session is present. */
function auraai_sec_admin_logged_in(): bool
{
    auraai_sec_session_start();
    return !empty($_SESSION['id'])
        || !empty($_SESSION['username'])
        || !empty($_SESSION['admin_id']);
}

/** Require active admin session. */
function auraai_sec_require_admin(): void
{
    if (!auraai_sec_admin_logged_in()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Session expired. Please sign in again.']);
        exit;
    }
}

/** Normalize host for origin checks (auraai-vps.com == auraai-vps.com). */
function auraai_sec_normalize_host(string $host): string
{
    $host = strtolower(trim($host));
    if (strpos($host, 'www.') === 0) {
        $host = substr($host, 4);
    }
    return $host;
}

/** Block cross-site POSTs to admin JSON endpoints (CSRF-lite for fetch/XHR). */
function auraai_sec_require_same_origin(): void
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return;
    }
    $expectedHost = auraai_sec_normalize_host($host);
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin !== '') {
        $originHost = parse_url($origin, PHP_URL_HOST);
        if (is_string($originHost) && auraai_sec_normalize_host($originHost) !== $expectedHost) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Forbidden';
            exit;
        }
        return;
    }
    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    if ($referer !== '') {
        $refererHost = parse_url($referer, PHP_URL_HOST);
        if (is_string($refererHost) && auraai_sec_normalize_host($refererHost) !== $expectedHost) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Forbidden';
            exit;
        }
    }
}

function auraai_sec_require_admin_action(): void
{
    auraai_sec_require_admin();
    auraai_sec_require_same_origin();
}

function auraai_sec_bootstrap(): void
{
    auraai_sec_send_headers();
    if (!function_exists('auraai_sec_ip_block_check_or_exit')) {
        require_once __DIR__ . '/ip-block.php';
    }
    auraai_sec_ip_block_check_or_exit();
}
