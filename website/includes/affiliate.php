<?php
/**
 * NexTradeAI — affiliate accounts, attribution, and conversion tracking.
 */

require_once __DIR__ . '/security.php';

const AURAAI_AFFILIATE_COOKIE = 'auraai_ref';
const AURAAI_VISITOR_COOKIE = 'auraai_vid';
const AURAAI_AFFILIATE_COOKIE_DAYS = 30;
const AURAAI_AFFILIATE_MIN_COMMISSION = 0.05;
const AURAAI_AFFILIATE_MAX_COMMISSION = 0.10;
const AURAAI_AFFILIATE_SALES_FOR_MAX = 350;
const AURAAI_AFFILIATE_MIN_WITHDRAWAL_CENTS = 10000;
/** @deprecated Use auraai_affiliate_commission_rate_from_sales() */
const AURAAI_AFFILIATE_DEFAULT_COMMISSION = 0.05;

function auraai_affiliate_ensure_tables(mysqli $con): void
{
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS affiliates (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(32) NOT NULL,
        full_name VARCHAR(120) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(30) NULL,
        password VARCHAR(255) NOT NULL,
        commission_rate DECIMAL(5,4) NOT NULL DEFAULT 0.0500,
        status ENUM('active','paused','blocked') NOT NULL DEFAULT 'active',
        payout_notes TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        UNIQUE KEY uq_code (code),
        UNIQUE KEY uq_email (email),
        KEY idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS affiliate_attributions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        affiliate_id INT UNSIGNED NOT NULL,
        email VARCHAR(255) NOT NULL,
        ref_code VARCHAR(32) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        UNIQUE KEY uq_email (email),
        KEY idx_affiliate (affiliate_id),
        KEY idx_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS affiliate_clicks (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        affiliate_id INT UNSIGNED NOT NULL,
        ref_code VARCHAR(32) NOT NULL,
        landing_path VARCHAR(255) NOT NULL DEFAULT '/shop/',
        ip_hash CHAR(64) NULL,
        clicked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_affiliate (affiliate_id),
        KEY idx_ip_clicked (ip_hash, clicked_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS affiliate_ip_attributions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        affiliate_id INT UNSIGNED NOT NULL,
        ref_code VARCHAR(32) NOT NULL,
        ip_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_ip_hash (ip_hash),
        KEY idx_expires (expires_at),
        KEY idx_affiliate (affiliate_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS affiliate_visitor_attributions (
        visitor_hash CHAR(64) NOT NULL PRIMARY KEY,
        affiliate_id INT UNSIGNED NOT NULL,
        ref_code VARCHAR(32) NOT NULL,
        expires_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_expires (expires_at),
        KEY idx_affiliate (affiliate_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS affiliate_conversions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        affiliate_id INT UNSIGNED NOT NULL,
        member_id INT UNSIGNED NULL,
        email VARCHAR(255) NOT NULL,
        product_type ENUM('vps','scanner','bundle') NOT NULL DEFAULT 'vps',
        gateway ENUM('paystack','whop','ozow') NOT NULL,
        gateway_ref VARCHAR(128) NOT NULL,
        amount_cents INT UNSIGNED NOT NULL DEFAULT 0,
        currency CHAR(3) NOT NULL DEFAULT 'ZAR',
        commission_cents INT UNSIGNED NOT NULL DEFAULT 0,
        status ENUM('confirmed','refunded') NOT NULL DEFAULT 'confirmed',
        converted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_gateway_ref (gateway, gateway_ref),
        KEY idx_affiliate (affiliate_id),
        KEY idx_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS affiliate_payout_methods (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        affiliate_id INT UNSIGNED NOT NULL,
        method_type ENUM('btc','usdt_trc20','bank') NOT NULL,
        label VARCHAR(80) NULL,
        wallet_address VARCHAR(128) NULL,
        account_name VARCHAR(120) NULL,
        bank_name VARCHAR(120) NULL,
        account_number VARCHAR(64) NULL,
        branch_code VARCHAR(32) NULL,
        is_default TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        KEY idx_affiliate (affiliate_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS affiliate_withdrawals (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        affiliate_id INT UNSIGNED NOT NULL,
        payout_method_id INT UNSIGNED NOT NULL,
        amount_cents INT UNSIGNED NOT NULL,
        currency CHAR(3) NOT NULL DEFAULT 'ZAR',
        status ENUM('pending','processing','paid','rejected') NOT NULL DEFAULT 'pending',
        admin_notes TEXT NULL,
        requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        processed_at DATETIME NULL,
        KEY idx_affiliate (affiliate_id),
        KEY idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $col = mysqli_query($con, "SHOW COLUMNS FROM affiliates LIKE 'admin_id'");
    if ($col && mysqli_num_rows($col) === 0) {
        mysqli_query($con, 'ALTER TABLE affiliates ADD COLUMN admin_id INT UNSIGNED NULL AFTER id, ADD KEY idx_admin_id (admin_id)');
    }

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS affiliate_payment_ips (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        member_id INT UNSIGNED NULL,
        email VARCHAR(255) NOT NULL,
        affiliate_id INT UNSIGNED NOT NULL DEFAULT 0,
        ref_code VARCHAR(32) NOT NULL DEFAULT '',
        ip_hash CHAR(64) NOT NULL,
        gateway ENUM('paystack','whop','ozow','app') NOT NULL,
        gateway_ref VARCHAR(128) NOT NULL,
        recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_gateway_ref (gateway, gateway_ref),
        KEY idx_email (email),
        KEY idx_ip_hash (ip_hash),
        KEY idx_affiliate (affiliate_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $gwCol = mysqli_query($con, "SHOW COLUMNS FROM affiliate_conversions LIKE 'gateway'");
    if ($gwCol && ($gwRow = mysqli_fetch_assoc($gwCol)) && strpos((string) $gwRow['Type'], 'app') === false) {
        mysqli_query($con, "ALTER TABLE affiliate_conversions MODIFY gateway ENUM('paystack','whop','ozow','app') NOT NULL");
    }
}

function auraai_affiliate_generate_code(mysqli $con): string
{
    for ($i = 0; $i < 20; $i++) {
        $code = 'AFF' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $stmt = $con->prepare('SELECT id FROM affiliates WHERE code = ? LIMIT 1');
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$exists) {
            return $code;
        }
    }
    return 'AFF' . strtoupper(substr(sha1((string) microtime(true)), 0, 6));
}

function auraai_affiliate_normalize_code(?string $code): ?string
{
    $code = auraai_sec_string($code ?? '', 32, 3);
    if ($code === null) {
        return null;
    }
    $code = strtoupper($code);
    if (!preg_match('/^[A-Z0-9\-_]+$/', $code)) {
        return null;
    }
    return $code;
}

/** Sales needed to reach a given commission rate on the 0–350 scale. */
function auraai_affiliate_sales_for_rate(float $rate): int
{
    $rate = max(AURAAI_AFFILIATE_MIN_COMMISSION, min(AURAAI_AFFILIATE_MAX_COMMISSION, $rate));
    $span = AURAAI_AFFILIATE_MAX_COMMISSION - AURAAI_AFFILIATE_MIN_COMMISSION;
    if ($span <= 0) {
        return 0;
    }

    return (int) round((($rate - AURAAI_AFFILIATE_MIN_COMMISSION) / $span) * AURAAI_AFFILIATE_SALES_FOR_MAX);
}

/** Commission rate (0.05–0.10) scales linearly from 0 to 350 confirmed sales. */
function auraai_affiliate_commission_rate_from_sales(int $confirmedSales): float
{
    $confirmedSales = max(0, min(AURAAI_AFFILIATE_SALES_FOR_MAX, $confirmedSales));
    $span = AURAAI_AFFILIATE_MAX_COMMISSION - AURAAI_AFFILIATE_MIN_COMMISSION;
    $rate = AURAAI_AFFILIATE_MIN_COMMISSION + ($confirmedSales / AURAAI_AFFILIATE_SALES_FOR_MAX) * $span;

    return min(AURAAI_AFFILIATE_MAX_COMMISSION, $rate);
}

function auraai_affiliate_confirmed_sales_count(mysqli $con, int $affiliateId): int
{
    auraai_affiliate_ensure_tables($con);
    $stmt = $con->prepare("SELECT COUNT(*) AS c FROM affiliate_conversions WHERE affiliate_id = ? AND status = 'confirmed'");
    $stmt->bind_param('i', $affiliateId);
    $stmt->execute();
    $count = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();

    return $count;
}

/** Current rate + next tier progress for the affiliate dashboard. */
function auraai_affiliate_commission_info(mysqli $con, int $affiliateId): array
{
    $sales = auraai_affiliate_confirmed_sales_count($con, $affiliateId);
    $rate = auraai_affiliate_commission_rate_from_sales($sales);
    $minPct = (int) round(AURAAI_AFFILIATE_MIN_COMMISSION * 100);
    $maxPct = (int) round(AURAAI_AFFILIATE_MAX_COMMISSION * 100);
    $currentPct = round($rate * 100, 1);

    $nextPct = null;
    $salesToNext = null;
    if ($rate < AURAAI_AFFILIATE_MAX_COMMISSION) {
        $nextPctVal = min($maxPct, (int) ceil($currentPct + 0.001));
        if ($nextPctVal <= $currentPct) {
            $nextPctVal = min($maxPct, (int) floor($currentPct) + 1);
        }
        $nextRate = $nextPctVal / 100;
        $nextPct = (float) $nextPctVal;
        $salesForNext = auraai_affiliate_sales_for_rate($nextRate);
        $salesToNext = max(1, $salesForNext - $sales);
    }

    return [
        'confirmed_sales' => $sales,
        'rate' => $rate,
        'current_pct' => $currentPct,
        'min_pct' => $minPct,
        'max_pct' => $maxPct,
        'next_pct' => $nextPct,
        'sales_to_next' => $salesToNext,
        'sales_for_max' => AURAAI_AFFILIATE_SALES_FOR_MAX,
        'sales_remaining' => max(0, AURAAI_AFFILIATE_SALES_FOR_MAX - $sales),
        'at_max' => $rate >= AURAAI_AFFILIATE_MAX_COMMISSION,
    ];
}

function auraai_affiliate_sync_commission_rate(mysqli $con, int $affiliateId): void
{
    $info = auraai_affiliate_commission_info($con, $affiliateId);
    $stmt = $con->prepare('UPDATE affiliates SET commission_rate = ?, updated_at = NOW() WHERE id = ?');
    $rate = $info['rate'];
    $stmt->bind_param('di', $rate, $affiliateId);
    $stmt->execute();
    $stmt->close();
}

function auraai_affiliate_by_code(mysqli $con, string $code): ?array
{
    auraai_affiliate_ensure_tables($con);
    $code = auraai_affiliate_normalize_code($code);
    if ($code === null) {
        return null;
    }
    $stmt = $con->prepare("SELECT * FROM affiliates WHERE code = ? AND status = 'active' LIMIT 1");
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function auraai_affiliate_by_id(mysqli $con, int $id): ?array
{
    auraai_affiliate_ensure_tables($con);
    $stmt = $con->prepare('SELECT * FROM affiliates WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function auraai_affiliate_register(mysqli $con, string $fullName, string $email, string $password, string $phone = ''): array
{
    auraai_affiliate_ensure_tables($con);

    $fullName = auraai_sec_string($fullName, 120, 2);
    $email = auraai_sec_email($email);
    $password = auraai_sec_password($password, 6, 50);
    $phone = auraai_sec_string($phone, 30, 0) ?? '';

    if ($fullName === null || $email === null || $password === null) {
        return ['ok' => false, 'error' => 'Please fill in all required fields correctly.'];
    }

    $check = $con->prepare('SELECT id FROM affiliates WHERE email = ? LIMIT 1');
    $check->bind_param('s', $email);
    $check->execute();
    if ($check->get_result()->fetch_assoc()) {
        $check->close();
        return ['ok' => false, 'error' => 'An affiliate account with this email already exists.'];
    }
    $check->close();

    $code = auraai_affiliate_generate_code($con);
    $stmt = $con->prepare('INSERT INTO affiliates (code, full_name, email, phone, password, commission_rate, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $rate = AURAAI_AFFILIATE_MIN_COMMISSION;
    $status = 'active';
    $stmt->bind_param('sssssds', $code, $fullName, $email, $phone, $password, $rate, $status);
    $ok = $stmt->execute();
    $affiliateId = $ok ? (int) $stmt->insert_id : 0;
    $stmt->close();

    if (!$ok) {
        return ['ok' => false, 'error' => 'Could not create affiliate account.'];
    }

    auraai_affiliate_notify_welcome($email, $fullName, $code, false);

    return ['ok' => true, 'id' => $affiliateId, 'code' => $code];
}

function auraai_affiliate_login(mysqli $con, string $email, string $password): array
{
    auraai_affiliate_ensure_tables($con);
    $email = auraai_sec_email($email);
    $password = auraai_sec_password($password, 1, 50);
    if ($email === null || $password === null) {
        return ['ok' => false, 'error' => 'Invalid email or password.'];
    }

    $stmt = $con->prepare('SELECT id, password, status FROM affiliates WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || !hash_equals((string) $row['password'], $password)) {
        return ['ok' => false, 'error' => 'Invalid email or password.'];
    }

    $status = strtolower((string) ($row['status'] ?? 'active'));
    if ($status === 'blocked') {
        return ['ok' => false, 'error' => 'blocked', 'code' => 'blocked'];
    }
    if (!in_array($status, ['active', 'paused'], true)) {
        return ['ok' => false, 'error' => 'Your affiliate account is not active. Contact support.', 'code' => 'inactive'];
    }

    return ['ok' => true, 'id' => (int) $row['id'], 'status' => $status];
}

function auraai_affiliate_set_ref_cookie(string $code): void
{
    $code = auraai_affiliate_normalize_code($code);
    if ($code === null) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie(AURAAI_AFFILIATE_COOKIE, $code, [
        'expires' => time() + (AURAAI_AFFILIATE_COOKIE_DAYS * 86400),
        'path' => '/',
        'secure' => $secure,
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[AURAAI_AFFILIATE_COOKIE] = $code;
}

function auraai_affiliate_get_ref_cookie(): ?string
{
    return auraai_affiliate_normalize_code($_COOKIE[AURAAI_AFFILIATE_COOKIE] ?? null);
}

function auraai_affiliate_ip_hash(?string $ip = null): string
{
    $ip = $ip ?? auraai_sec_client_ip();
    return hash('sha256', $ip);
}

/** Validate a client IP passed from payment metadata or app ping. */
function auraai_affiliate_normalize_client_ip(?string $ip): ?string
{
    $ip = trim((string) $ip);
    if ($ip === '') {
        return null;
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        // Allow private IPs in dev / local testing.
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }
    }
    return $ip;
}

/** Extract visitor id from a URL query string (?vid=). */
function auraai_affiliate_extract_visitor_from_referrer(string $referrer): ?string
{
    if ($referrer === '') {
        return null;
    }
    $query = parse_url($referrer, PHP_URL_QUERY);
    if (!is_string($query) || $query === '') {
        return null;
    }
    parse_str($query, $params);
    foreach (['vid', 'visitor_id', 'visitor'] as $key) {
        if (!empty($params[$key])) {
            return auraai_affiliate_normalize_visitor((string) $params[$key]);
        }
    }
    return null;
}

function auraai_affiliate_normalize_visitor(?string $visitorId): ?string
{
    $visitorId = strtolower(trim((string) $visitorId));
    if ($visitorId === '') {
        return null;
    }
    if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $visitorId)) {
        return null;
    }
    return $visitorId;
}

function auraai_affiliate_visitor_hash(?string $visitorId): ?string
{
    $visitorId = auraai_affiliate_normalize_visitor($visitorId);
    if ($visitorId === null) {
        return null;
    }
    return hash('sha256', $visitorId);
}

function auraai_affiliate_generate_visitor_id(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12)
    );
}

function auraai_affiliate_set_visitor_cookie(string $visitorId): void
{
    $visitorId = auraai_affiliate_normalize_visitor($visitorId);
    if ($visitorId === null) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie(AURAAI_VISITOR_COOKIE, $visitorId, [
        'expires' => time() + (AURAAI_AFFILIATE_COOKIE_DAYS * 86400),
        'path' => '/',
        'secure' => $secure,
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[AURAAI_VISITOR_COOKIE] = $visitorId;
}

/** Persistent anonymous device/browser id for cross-session affiliate matching. */
function auraai_affiliate_ensure_visitor_cookie(?string $preferred = null): string
{
    $preferred = auraai_affiliate_normalize_visitor($preferred);
    $existing = auraai_affiliate_normalize_visitor($_COOKIE[AURAAI_VISITOR_COOKIE] ?? '');
    $visitorId = $preferred ?? $existing ?? auraai_affiliate_generate_visitor_id();
    auraai_affiliate_set_visitor_cookie($visitorId);

    return $visitorId;
}

function auraai_affiliate_attribution_expires_at(): string
{
    return date('Y-m-d H:i:s', time() + (AURAAI_AFFILIATE_COOKIE_DAYS * 86400));
}

function auraai_affiliate_record_ip_attribution(mysqli $con, string $code, ?string $ip = null): void
{
    $affiliate = auraai_affiliate_by_code($con, $code);
    if (!$affiliate) {
        return;
    }
    $ipHash = auraai_affiliate_ip_hash($ip);
    $affiliateId = (int) $affiliate['id'];
    $expiresAt = auraai_affiliate_attribution_expires_at();
    $stmt = $con->prepare(
        'INSERT INTO affiliate_ip_attributions (affiliate_id, ref_code, ip_hash, expires_at)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE affiliate_id = VALUES(affiliate_id), ref_code = VALUES(ref_code), expires_at = VALUES(expires_at), updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->bind_param('isss', $affiliateId, $code, $ipHash, $expiresAt);
    $stmt->execute();
    $stmt->close();
}

function auraai_affiliate_record_visitor_attribution(mysqli $con, string $code, ?string $visitorId): void
{
    $visitorHash = auraai_affiliate_visitor_hash($visitorId);
    if ($visitorHash === null) {
        return;
    }
    $affiliate = auraai_affiliate_by_code($con, $code);
    if (!$affiliate) {
        return;
    }
    $affiliateId = (int) $affiliate['id'];
    $expiresAt = auraai_affiliate_attribution_expires_at();
    $stmt = $con->prepare(
        'INSERT INTO affiliate_visitor_attributions (visitor_hash, affiliate_id, ref_code, expires_at)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE affiliate_id = VALUES(affiliate_id), ref_code = VALUES(ref_code), expires_at = VALUES(expires_at), updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->bind_param('siss', $visitorHash, $affiliateId, $code, $expiresAt);
    $stmt->execute();
    $stmt->close();
}

/** Record IP + visitor signals when a referral link is opened (browser or app ping). */
function auraai_affiliate_record_attribution_signals(mysqli $con, string $code, ?string $visitorId = null, ?string $ip = null): void
{
    auraai_affiliate_record_ip_attribution($con, $code, $ip);
    if ($visitorId !== null && $visitorId !== '') {
        auraai_affiliate_record_visitor_attribution($con, $code, $visitorId);
    }
}

function auraai_affiliate_resolve_by_ip(mysqli $con, ?string $ip = null): ?array
{
    auraai_affiliate_ensure_tables($con);
    $ipHash = auraai_affiliate_ip_hash($ip);
    $stmt = $con->prepare(
        "SELECT a.* FROM affiliate_ip_attributions ip
         INNER JOIN affiliates a ON a.id = ip.affiliate_id
         WHERE ip.ip_hash = ? AND ip.expires_at > NOW() AND a.status = 'active'
         ORDER BY ip.updated_at DESC
         LIMIT 1"
    );
    $stmt->bind_param('s', $ipHash);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function auraai_affiliate_resolve_by_visitor(mysqli $con, ?string $visitorId): ?array
{
    auraai_affiliate_ensure_tables($con);
    $visitorHash = auraai_affiliate_visitor_hash($visitorId);
    if ($visitorHash === null) {
        return null;
    }
    $stmt = $con->prepare(
        "SELECT a.* FROM affiliate_visitor_attributions v
         INNER JOIN affiliates a ON a.id = v.affiliate_id
         WHERE v.visitor_hash = ? AND v.expires_at > NOW() AND a.status = 'active'
         LIMIT 1"
    );
    $stmt->bind_param('s', $visitorHash);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

/**
 * Resolve affiliate code from URL/cookie, visitor id, or recent IP click — in priority order.
 */
function auraai_affiliate_resolve_ref_for_session(
    mysqli $con,
    ?string $refFromRequest = null,
    ?string $visitorId = null,
    ?string $clientIp = null
): ?string {
    $code = auraai_affiliate_normalize_code($refFromRequest);
    if ($code !== null) {
        return $code;
    }
    $code = auraai_affiliate_get_ref_cookie();
    if ($code !== null) {
        return $code;
    }
    if ($visitorId !== null && $visitorId !== '') {
        $affiliate = auraai_affiliate_resolve_by_visitor($con, $visitorId);
        if ($affiliate) {
            return (string) $affiliate['code'];
        }
    }
    $cookieVisitor = auraai_affiliate_normalize_visitor($_COOKIE[AURAAI_VISITOR_COOKIE] ?? '');
    if ($cookieVisitor !== null) {
        $affiliate = auraai_affiliate_resolve_by_visitor($con, $cookieVisitor);
        if ($affiliate) {
            return (string) $affiliate['code'];
        }
    }
    $affiliate = auraai_affiliate_resolve_by_ip($con, $clientIp);
    if ($affiliate) {
        return (string) $affiliate['code'];
    }

    return null;
}

/** Bind email to affiliate using explicit ref, visitor id, or IP fallback. */
function auraai_affiliate_ensure_email_attribution(
    mysqli $con,
    string $email,
    ?string $refFromRequest = null,
    ?string $visitorId = null,
    ?string $clientIp = null
): ?array {
    auraai_affiliate_ensure_tables($con);
    $email = auraai_sec_email($email);
    if ($email === null) {
        return null;
    }

    $existing = auraai_affiliate_resolve_by_email($con, $email);
    if ($existing) {
        return $existing;
    }

    $code = auraai_affiliate_resolve_ref_for_session($con, $refFromRequest, $visitorId, $clientIp);
    if ($code === null) {
        return null;
    }

    if (!auraai_affiliate_bind_email($con, $email, $code)) {
        return null;
    }

    return auraai_affiliate_by_code($con, $code);
}

function auraai_affiliate_track_click(mysqli $con, string $code, string $path = '/shop/', ?string $visitorId = null): void
{
    $affiliate = auraai_affiliate_by_code($con, $code);
    if (!$affiliate) {
        return;
    }
    $ipHash = auraai_affiliate_ip_hash();
    $stmt = $con->prepare('INSERT INTO affiliate_clicks (affiliate_id, ref_code, landing_path, ip_hash) VALUES (?, ?, ?, ?)');
    $affiliateId = (int) $affiliate['id'];
    $stmt->bind_param('isss', $affiliateId, $code, $path, $ipHash);
    $stmt->execute();
    $stmt->close();
    auraai_affiliate_record_attribution_signals($con, $code, $visitorId);
}

function auraai_affiliate_bind_email(mysqli $con, string $email, string $code): bool
{
    $email = auraai_sec_email($email);
    $affiliate = auraai_affiliate_by_code($con, $code);
    if ($email === null || !$affiliate) {
        return false;
    }

    $affiliateId = (int) $affiliate['id'];
    $expiresAt = date('Y-m-d H:i:s', time() + (AURAAI_AFFILIATE_COOKIE_DAYS * 86400));

    $stmt = $con->prepare(
        'INSERT INTO affiliate_attributions (affiliate_id, email, ref_code, expires_at)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE affiliate_id = VALUES(affiliate_id), ref_code = VALUES(ref_code), expires_at = VALUES(expires_at), created_at = CURRENT_TIMESTAMP'
    );
    $stmt->bind_param('isss', $affiliateId, $email, $code, $expiresAt);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function auraai_affiliate_resolve_by_email(mysqli $con, string $email): ?array
{
    auraai_affiliate_ensure_tables($con);
    $email = auraai_sec_email($email);
    if ($email === null) {
        return null;
    }
    $stmt = $con->prepare(
        "SELECT a.* FROM affiliate_attributions att
         INNER JOIN affiliates a ON a.id = att.affiliate_id
         WHERE att.email = ? AND att.expires_at > NOW() AND a.status = 'active'
         LIMIT 1"
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function auraai_affiliate_extract_code_from_referrer(string $referrer): ?string
{
    if ($referrer === '') {
        return null;
    }
    $query = parse_url($referrer, PHP_URL_QUERY);
    if (!is_string($query) || $query === '') {
        return null;
    }
    parse_str($query, $params);
    foreach (['ref', 'affiliate', 'affiliate_code'] as $key) {
        if (!empty($params[$key])) {
            return auraai_affiliate_normalize_code((string) $params[$key]);
        }
    }
    return null;
}

function auraai_affiliate_extract_code_from_custom_fields($fields): ?string
{
    if (!is_array($fields)) {
        return null;
    }
    foreach ($fields as $field) {
        if (!is_object($field) && !is_array($field)) {
            continue;
        }
        $name = strtolower((string) (is_object($field)
            ? ($field->variable_name ?? $field->name ?? $field->question ?? '')
            : ($field['variable_name'] ?? $field['name'] ?? $field['question'] ?? '')));
        $value = (string) (is_object($field)
            ? ($field->value ?? $field->answer ?? '')
            : ($field['value'] ?? $field['answer'] ?? ''));
        if (in_array($name, ['ref', 'affiliate', 'affiliate_code', 'affiliatecode', 'affiliate ref', 'referral', 'referral code'], true)) {
            $code = auraai_affiliate_normalize_code($value);
            if ($code !== null) {
                return $code;
            }
        }
    }
    return null;
}

/** Normalize Whop/Paystack custom field payloads to a single list. */
function auraai_affiliate_collect_custom_fields($data): array
{
    $lists = [];
    if (!is_object($data) && !is_array($data)) {
        return [];
    }

    $sources = [];
    if (is_object($data)) {
        $sources[] = $data->custom_field_responses ?? null;
        $sources[] = $data->custom_fields ?? null;
        $sources[] = $data->membership->custom_field_responses ?? null;
        $sources[] = $data->metadata->custom_fields ?? null;
        if (isset($data->metadata) && is_object($data->metadata)) {
            $sources[] = $data->metadata;
        }
    }

    foreach ($sources as $source) {
        if (is_array($source)) {
            $lists = array_merge($lists, $source);
        } elseif (is_object($source)) {
            foreach (get_object_vars($source) as $key => $value) {
                if (is_scalar($value)) {
                    $lists[] = (object) ['variable_name' => (string) $key, 'value' => (string) $value];
                }
            }
        }
    }

    return $lists;
}

/** Extract affiliate ref from Whop membership/payment payload. */
function auraai_affiliate_whop_context($data): array
{
    $referrer = '';
    $customFields = auraai_affiliate_collect_custom_fields($data);
    $currency = 'ZAR';

    if (is_object($data)) {
        $referrer = (string) ($data->referrer ?? $data->checkout_url ?? $data->source_url ?? '');
        $rawCurrency = strtoupper((string) ($data->currency ?? $data->plan->currency ?? 'ZAR'));
        if ($rawCurrency !== '') {
            $currency = $rawCurrency;
        }

        foreach (['metadata', 'plan'] as $nestedKey) {
            $nested = $data->{$nestedKey} ?? null;
            if (!is_object($nested)) {
                continue;
            }
            $directRef = auraai_affiliate_normalize_code((string) ($nested->ref ?? $nested->affiliate ?? $nested->affiliate_code ?? ''));
            if ($directRef !== null) {
                $customFields[] = (object) ['variable_name' => 'ref', 'value' => $directRef];
            }
        }

        $directRef = auraai_affiliate_normalize_code((string) ($data->ref ?? $data->affiliate ?? $data->affiliate_code ?? ''));
        if ($directRef !== null) {
            $customFields[] = (object) ['variable_name' => 'ref', 'value' => $directRef];
        }
    }

    if ($referrer !== '') {
        $fromReferrer = auraai_affiliate_extract_code_from_referrer($referrer);
        if ($fromReferrer !== null && auraai_affiliate_extract_code_from_custom_fields($customFields) === null) {
            $customFields[] = (object) ['variable_name' => 'ref', 'value' => $fromReferrer];
        }
    }

    $refCode = auraai_affiliate_extract_code_from_custom_fields($customFields);

    $visitorId = null;
    if (is_object($data)) {
        $visitorId = auraai_affiliate_normalize_visitor((string) ($data->vid ?? $data->visitor_id ?? ''));
        if ($visitorId === null && isset($data->metadata) && is_object($data->metadata)) {
            $visitorId = auraai_affiliate_normalize_visitor((string) ($data->metadata->vid ?? $data->metadata->visitor_id ?? ''));
        }
    }
    if ($visitorId === null) {
        foreach ($customFields as $field) {
            $name = strtolower((string) (is_object($field)
                ? ($field->variable_name ?? $field->name ?? $field->question ?? '')
                : ($field['variable_name'] ?? $field['name'] ?? $field['question'] ?? '')));
            if (in_array($name, ['vid', 'visitor_id', 'visitor'], true)) {
                $visitorId = auraai_affiliate_normalize_visitor((string) (is_object($field)
                    ? ($field->value ?? $field->answer ?? '')
                    : ($field['value'] ?? $field['answer'] ?? '')));
                if ($visitorId !== null) {
                    break;
                }
            }
        }
    }
    if ($visitorId === null && $referrer !== '') {
        $visitorId = auraai_affiliate_extract_visitor_from_referrer($referrer);
    }

    $clientIp = null;
    if (is_object($data)) {
        $clientIp = auraai_affiliate_normalize_client_ip((string) ($data->ip_address ?? $data->customer_ip ?? ''));
        if ($clientIp === null && isset($data->metadata) && is_object($data->metadata)) {
            $clientIp = auraai_affiliate_normalize_client_ip((string) ($data->metadata->customer_ip ?? $data->metadata->ip_address ?? ''));
        }
    }

    return [
        'referrer' => $referrer,
        'custom_fields' => $customFields ?: null,
        'ref_code' => $refCode,
        'visitor_id' => $visitorId,
        'client_ip' => $clientIp,
        'currency' => $currency,
    ];
}

/** Extract affiliate ref from Paystack charge.success metadata (referrer URL, custom fields, direct keys). */
function auraai_affiliate_paystack_context($event): array
{
    $empty = [
        'referrer' => '',
        'custom_fields' => null,
        'ref_code' => null,
        'visitor_id' => null,
        'client_ip' => null,
    ];

    if (!is_object($event) || !isset($event->data)) {
        return $empty;
    }

    $data = $event->data;
    $metadata = $data->metadata ?? null;
    $referrer = '';
    $customFields = null;

    if (is_object($metadata)) {
        $referrer = (string) ($metadata->referrer ?? '');
        $customFields = $metadata->custom_fields ?? null;

        $directRef = auraai_affiliate_normalize_code((string) ($metadata->ref ?? $metadata->affiliate ?? $metadata->affiliate_code ?? ''));
        if ($directRef !== null) {
            $customFields = is_array($customFields) ? $customFields : [];
            $customFields[] = (object) ['variable_name' => 'ref', 'value' => $directRef];
        }

        if ($referrer !== '') {
            $fromReferrer = auraai_affiliate_extract_code_from_referrer($referrer);
            if ($fromReferrer !== null) {
                $hasRef = auraai_affiliate_extract_code_from_custom_fields($customFields) !== null;
                if (!$hasRef) {
                    $customFields = is_array($customFields) ? $customFields : [];
                    $customFields[] = (object) ['variable_name' => 'ref', 'value' => $fromReferrer];
                }
            }
        }
    }

    $refCode = auraai_affiliate_extract_code_from_custom_fields(is_array($customFields) ? $customFields : null);
    if ($refCode === null && $referrer !== '') {
        $refCode = auraai_affiliate_extract_code_from_referrer($referrer);
    }

    $visitorId = null;
    if (is_object($metadata)) {
        $visitorId = auraai_affiliate_normalize_visitor((string) ($metadata->vid ?? $metadata->visitor_id ?? ''));
    }
    if ($visitorId === null && $referrer !== '') {
        $visitorId = auraai_affiliate_extract_visitor_from_referrer($referrer);
    }

    $clientIp = auraai_affiliate_normalize_client_ip((string) (
        $data->ip_address
        ?? $data->customer_ip
        ?? (is_object($data->authorization ?? null) ? ($data->authorization->ip_address ?? '') : '')
        ?? (is_object($metadata) ? ($metadata->customer_ip ?? $metadata->ip_address ?? '') : '')
    ));

    return [
        'referrer' => $referrer,
        'custom_fields' => $customFields,
        'ref_code' => $refCode,
        'visitor_id' => $visitorId,
        'client_ip' => $clientIp,
    ];
}

function auraai_affiliate_resolve_for_payment(
    mysqli $con,
    string $email,
    ?string $referrer = null,
    $customFields = null,
    ?string $visitorId = null,
    ?string $clientIp = null
): ?array {
    $code = auraai_affiliate_extract_code_from_custom_fields($customFields);
    if ($code !== null) {
        $affiliate = auraai_affiliate_by_code($con, $code);
        if ($affiliate) {
            return $affiliate;
        }
    }

    if ($referrer !== null && $referrer !== '') {
        $code = auraai_affiliate_extract_code_from_referrer($referrer);
        if ($code !== null) {
            $affiliate = auraai_affiliate_by_code($con, $code);
            if ($affiliate) {
                return $affiliate;
            }
        }
    }

    $affiliate = auraai_affiliate_resolve_by_email($con, $email);
    if ($affiliate) {
        return $affiliate;
    }

    if ($visitorId !== null && $visitorId !== '') {
        $affiliate = auraai_affiliate_resolve_by_visitor($con, $visitorId);
        if ($affiliate) {
            auraai_affiliate_bind_email($con, $email, (string) $affiliate['code']);
            return $affiliate;
        }
    }

    if ($clientIp !== null && $clientIp !== '') {
        $affiliate = auraai_affiliate_resolve_by_ip($con, $clientIp);
        if ($affiliate) {
            auraai_affiliate_bind_email($con, $email, (string) $affiliate['code']);
            return $affiliate;
        }
    }

    return null;
}

function auraai_affiliate_record_conversion(
    mysqli $con,
    array $affiliate,
    string $email,
    string $productType,
    string $gateway,
    string $gatewayRef,
    int $amountCents,
    string $currency = 'ZAR',
    ?int $memberId = null
): bool
{
    auraai_affiliate_ensure_tables($con);

    if (strtolower((string) ($affiliate['status'] ?? 'active')) !== 'active') {
        return false;
    }

    $email = auraai_sec_email($email);
    if ($email === null || $gatewayRef === '') {
        return false;
    }

    $productType = auraai_sec_enum($productType, ['vps', 'scanner', 'bundle']) ?? 'vps';
    $gateway = auraai_sec_enum($gateway, ['paystack', 'whop', 'ozow', 'app']) ?? 'paystack';
    $amountCents = max(0, $amountCents);
    $affiliateId = (int) $affiliate['id'];
    $priorSales = auraai_affiliate_confirmed_sales_count($con, $affiliateId);
    $rate = auraai_affiliate_commission_rate_from_sales($priorSales);
    $commissionCents = (int) round($amountCents * $rate);

    $status = 'confirmed';
    $memberIdVal = $memberId ?? 0;

    $stmt = $con->prepare(
        'INSERT IGNORE INTO affiliate_conversions
        (affiliate_id, member_id, email, product_type, gateway, gateway_ref, amount_cents, currency, commission_cents, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param(
        'iissssisis',
        $affiliateId,
        $memberIdVal,
        $email,
        $productType,
        $gateway,
        $gatewayRef,
        $amountCents,
        $currency,
        $commissionCents,
        $status
    );
    $ok = $stmt->execute();
    $inserted = $ok && $stmt->affected_rows > 0;
    $stmt->close();
    if ($inserted) {
        auraai_affiliate_sync_commission_rate($con, $affiliateId);
        auraai_affiliate_notify_commission($affiliate, $commissionCents, $productType, $rate);
    }

    return $inserted;
}

function auraai_affiliate_member_id_by_email(mysqli $con, string $email): ?int
{
    $email = auraai_sec_email($email);
    if ($email === null) {
        return null;
    }
    $stmt = $con->prepare('SELECT id FROM members WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int) $row['id'] : null;
}

function auraai_affiliate_dashboard_stats(mysqli $con, int $affiliateId): array
{
    auraai_affiliate_ensure_tables($con);

    $stats = [
        'clicks' => 0,
        'conversions' => 0,
        'commission_cents' => 0,
        'recent' => [],
    ];

    $stmt = $con->prepare('SELECT COUNT(*) AS c FROM affiliate_clicks WHERE affiliate_id = ?');
    $stmt->bind_param('i', $affiliateId);
    $stmt->execute();
    $stats['clicks'] = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();

    $stmt = $con->prepare("SELECT COUNT(*) AS c, COALESCE(SUM(commission_cents),0) AS s FROM affiliate_conversions WHERE affiliate_id = ? AND status = 'confirmed'");
    $stmt->bind_param('i', $affiliateId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stats['conversions'] = (int) ($row['c'] ?? 0);
    $stats['commission_cents'] = (int) ($row['s'] ?? 0);
    $stmt->close();

    $stmt = $con->prepare(
        "SELECT email, product_type, gateway, amount_cents, commission_cents, status, converted_at
         FROM affiliate_conversions WHERE affiliate_id = ? ORDER BY converted_at DESC LIMIT 25"
    );
    $stmt->bind_param('i', $affiliateId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($r = $result->fetch_assoc()) {
        $stats['recent'][] = $r;
    }
    $stmt->close();

    return $stats;
}

function auraai_affiliate_balance_info(mysqli $con, int $affiliateId): array
{
    auraai_affiliate_ensure_tables($con);

    $stmt = $con->prepare("SELECT COALESCE(SUM(commission_cents), 0) AS earned FROM affiliate_conversions WHERE affiliate_id = ? AND status = 'confirmed'");
    $stmt->bind_param('i', $affiliateId);
    $stmt->execute();
    $earned = (int) ($stmt->get_result()->fetch_assoc()['earned'] ?? 0);
    $stmt->close();

    $stmt = $con->prepare("SELECT COALESCE(SUM(amount_cents), 0) AS held FROM affiliate_withdrawals WHERE affiliate_id = ? AND status IN ('pending','processing')");
    $stmt->bind_param('i', $affiliateId);
    $stmt->execute();
    $pending = (int) ($stmt->get_result()->fetch_assoc()['held'] ?? 0);
    $stmt->close();

    $stmt = $con->prepare("SELECT COALESCE(SUM(amount_cents), 0) AS paid FROM affiliate_withdrawals WHERE affiliate_id = ? AND status = 'paid'");
    $stmt->bind_param('i', $affiliateId);
    $stmt->execute();
    $paidOut = (int) ($stmt->get_result()->fetch_assoc()['paid'] ?? 0);
    $stmt->close();

    $available = max(0, $earned - $pending - $paidOut);

    return [
        'earned_cents' => $earned,
        'pending_cents' => $pending,
        'paid_out_cents' => $paidOut,
        'available_cents' => $available,
        'min_withdrawal_cents' => AURAAI_AFFILIATE_MIN_WITHDRAWAL_CENTS,
    ];
}

function auraai_affiliate_payout_methods(mysqli $con, int $affiliateId): array
{
    auraai_affiliate_ensure_tables($con);
    $stmt = $con->prepare('SELECT * FROM affiliate_payout_methods WHERE affiliate_id = ? ORDER BY is_default DESC, created_at ASC');
    $stmt->bind_param('i', $affiliateId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows ?: [];
}

function auraai_affiliate_payout_method_label(array $method): string
{
    $type = $method['method_type'] ?? '';
    $labels = ['btc' => 'Bitcoin (BTC)', 'usdt_trc20' => 'USDT (TRC20)', 'bank' => 'Bank Account'];
    $base = $labels[$type] ?? ucfirst($type);
    if (!empty($method['label'])) {
        return $base . ' · ' . $method['label'];
    }

    return $base;
}

function auraai_affiliate_payout_method_summary(array $method): string
{
    $type = $method['method_type'] ?? '';
    if ($type === 'bank') {
        $bank = $method['bank_name'] ?? '';
        $acct = $method['account_number'] ?? '';
        if ($acct !== '' && strlen($acct) > 4) {
            $acct = str_repeat('•', max(0, strlen($acct) - 4)) . substr($acct, -4);
        }

        return trim($bank . ' ' . $acct);
    }

    $wallet = $method['wallet_address'] ?? '';
    if (strlen($wallet) > 12) {
        return substr($wallet, 0, 6) . '…' . substr($wallet, -6);
    }

    return $wallet;
}

function auraai_affiliate_validate_btc_address(string $address): bool
{
    return (bool) preg_match('/^(bc1|[13])[a-zA-HJ-NP-Z0-9]{25,62}$/', $address);
}

function auraai_affiliate_validate_trc20_address(string $address): bool
{
    return (bool) preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $address);
}

function auraai_affiliate_save_payout_method(mysqli $con, int $affiliateId, array $input): array
{
    auraai_affiliate_ensure_tables($con);

    $methodId = auraai_sec_int($input['method_id'] ?? 0, 0, 999999999) ?? 0;
    $type = auraai_sec_enum($input['method_type'] ?? '', ['btc', 'usdt_trc20', 'bank']);
    $label = auraai_sec_string($input['label'] ?? '', 80, 0) ?? '';
    $setDefault = !empty($input['is_default']);

    if ($type === null) {
        return ['ok' => false, 'error' => 'Please select a valid payout method type.'];
    }

    $wallet = '';
    $accountName = '';
    $bankName = '';
    $accountNumber = '';
    $branchCode = '';

    if ($type === 'btc') {
        $wallet = trim((string) ($input['wallet_address'] ?? ''));
        if (!auraai_affiliate_validate_btc_address($wallet)) {
            return ['ok' => false, 'error' => 'Enter a valid Bitcoin wallet address.'];
        }
    } elseif ($type === 'usdt_trc20') {
        $wallet = trim((string) ($input['wallet_address'] ?? ''));
        if (!auraai_affiliate_validate_trc20_address($wallet)) {
            return ['ok' => false, 'error' => 'Enter a valid USDT TRC20 wallet address (starts with T).'];
        }
    } else {
        $accountName = auraai_sec_string($input['account_name'] ?? '', 120, 2);
        $bankName = auraai_sec_string($input['bank_name'] ?? '', 120, 2);
        $accountNumber = auraai_sec_string($input['account_number'] ?? '', 64, 4);
        $branchCode = auraai_sec_string($input['branch_code'] ?? '', 32, 0) ?? '';
        if ($accountName === null || $bankName === null || $accountNumber === null) {
            return ['ok' => false, 'error' => 'Bank name, account holder, and account number are required.'];
        }
        if (!preg_match('/^[0-9]{4,64}$/', $accountNumber)) {
            return ['ok' => false, 'error' => 'Enter a valid bank account number.'];
        }
    }

    if ($methodId > 0) {
        $check = $con->prepare('SELECT id FROM affiliate_payout_methods WHERE id = ? AND affiliate_id = ? LIMIT 1');
        $check->bind_param('ii', $methodId, $affiliateId);
        $check->execute();
        if (!$check->get_result()->fetch_assoc()) {
            $check->close();

            return ['ok' => false, 'error' => 'Payout method not found.'];
        }
        $check->close();

        $isDefault = 0;
        if ($setDefault) {
            $isDefault = 1;
        } else {
            $cur = $con->prepare('SELECT is_default FROM affiliate_payout_methods WHERE id = ? AND affiliate_id = ? LIMIT 1');
            $cur->bind_param('ii', $methodId, $affiliateId);
            $cur->execute();
            $row = $cur->get_result()->fetch_assoc();
            $cur->close();
            $isDefault = (int) ($row['is_default'] ?? 0);
        }

        $stmt = $con->prepare(
            'UPDATE affiliate_payout_methods SET method_type = ?, label = ?, wallet_address = ?, account_name = ?, bank_name = ?, account_number = ?, branch_code = ?, is_default = ?, updated_at = NOW() WHERE id = ? AND affiliate_id = ?'
        );
        $stmt->bind_param('sssssssiii', $type, $label, $wallet, $accountName, $bankName, $accountNumber, $branchCode, $isDefault, $methodId, $affiliateId);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return ['ok' => false, 'error' => 'Could not update payout method.'];
        }
        if ($setDefault) {
            auraai_affiliate_set_default_payout_method($con, $affiliateId, $methodId);
        }
    } else {
        $existing = auraai_affiliate_payout_methods($con, $affiliateId);
        $isDefault = ($setDefault || count($existing) === 0) ? 1 : 0;
        $stmt = $con->prepare(
            'INSERT INTO affiliate_payout_methods (affiliate_id, method_type, label, wallet_address, account_name, bank_name, account_number, branch_code, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('isssssssi', $affiliateId, $type, $label, $wallet, $accountName, $bankName, $accountNumber, $branchCode, $isDefault);
        $ok = $stmt->execute();
        $methodId = $ok ? (int) $stmt->insert_id : 0;
        $stmt->close();
        if (!$ok) {
            return ['ok' => false, 'error' => 'Could not save payout method.'];
        }
        if ($isDefault) {
            auraai_affiliate_set_default_payout_method($con, $affiliateId, $methodId);
        }
    }

    return ['ok' => true, 'id' => $methodId];
}

function auraai_affiliate_set_default_payout_method(mysqli $con, int $affiliateId, int $methodId): void
{
    $stmt = $con->prepare('UPDATE affiliate_payout_methods SET is_default = 0 WHERE affiliate_id = ?');
    $stmt->bind_param('i', $affiliateId);
    $stmt->execute();
    $stmt->close();

    $stmt = $con->prepare('UPDATE affiliate_payout_methods SET is_default = 1, updated_at = NOW() WHERE id = ? AND affiliate_id = ?');
    $stmt->bind_param('ii', $methodId, $affiliateId);
    $stmt->execute();
    $stmt->close();
}

function auraai_affiliate_delete_payout_method(mysqli $con, int $affiliateId, int $methodId): array
{
    auraai_affiliate_ensure_tables($con);
    if ($methodId <= 0) {
        return ['ok' => false, 'error' => 'Invalid payout method.'];
    }

    $pending = $con->prepare("SELECT id FROM affiliate_withdrawals WHERE payout_method_id = ? AND affiliate_id = ? AND status IN ('pending','processing') LIMIT 1");
    $pending->bind_param('ii', $methodId, $affiliateId);
    $pending->execute();
    if ($pending->get_result()->fetch_assoc()) {
        $pending->close();

        return ['ok' => false, 'error' => 'This method has a pending withdrawal and cannot be removed.'];
    }
    $pending->close();

    $stmt = $con->prepare('DELETE FROM affiliate_payout_methods WHERE id = ? AND affiliate_id = ?');
    $stmt->bind_param('ii', $methodId, $affiliateId);
    $stmt->execute();
    $deleted = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$deleted) {
        return ['ok' => false, 'error' => 'Payout method not found.'];
    }

    $remaining = auraai_affiliate_payout_methods($con, $affiliateId);
    if (!empty($remaining)) {
        $hasDefault = false;
        foreach ($remaining as $m) {
            if (!empty($m['is_default'])) {
                $hasDefault = true;
                break;
            }
        }
        if (!$hasDefault) {
            auraai_affiliate_set_default_payout_method($con, $affiliateId, (int) $remaining[0]['id']);
        }
    }

    return ['ok' => true];
}

function auraai_affiliate_request_withdrawal(mysqli $con, int $affiliateId, int $methodId, int $amountCents): array
{
    auraai_affiliate_ensure_tables($con);

    if ($methodId <= 0) {
        return ['ok' => false, 'error' => 'Select a payout method.'];
    }

    $balance = auraai_affiliate_balance_info($con, $affiliateId);
    if ($amountCents < AURAAI_AFFILIATE_MIN_WITHDRAWAL_CENTS) {
        return ['ok' => false, 'error' => 'Minimum withdrawal is ' . auraai_affiliate_format_money(AURAAI_AFFILIATE_MIN_WITHDRAWAL_CENTS) . '.'];
    }
    if ($amountCents > $balance['available_cents']) {
        return ['ok' => false, 'error' => 'Insufficient available balance for this withdrawal.'];
    }

    $stmt = $con->prepare('SELECT id FROM affiliate_payout_methods WHERE id = ? AND affiliate_id = ? LIMIT 1');
    $stmt->bind_param('ii', $methodId, $affiliateId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        $stmt->close();

        return ['ok' => false, 'error' => 'Payout method not found.'];
    }
    $stmt->close();

    $open = $con->prepare("SELECT id FROM affiliate_withdrawals WHERE affiliate_id = ? AND status IN ('pending','processing') LIMIT 1");
    $open->bind_param('i', $affiliateId);
    $open->execute();
    if ($open->get_result()->fetch_assoc()) {
        $open->close();

        return ['ok' => false, 'error' => 'You already have a withdrawal in progress. Wait for it to be processed before requesting another.'];
    }
    $open->close();

    $stmt = $con->prepare('INSERT INTO affiliate_withdrawals (affiliate_id, payout_method_id, amount_cents, currency, status) VALUES (?, ?, ?, ?, ?)');
    $currency = 'ZAR';
    $status = 'pending';
    $stmt->bind_param('iiiss', $affiliateId, $methodId, $amountCents, $currency, $status);
    $ok = $stmt->execute();
    $withdrawalId = $ok ? (int) $stmt->insert_id : 0;
    $stmt->close();

    if (!$ok) {
        return ['ok' => false, 'error' => 'Could not submit withdrawal request.'];
    }

    auraai_affiliate_notify_withdrawal_requested($con, $affiliateId, $methodId, $amountCents);

    return ['ok' => true, 'id' => $withdrawalId];
}

function auraai_affiliate_withdrawals(mysqli $con, int $affiliateId, int $limit = 20): array
{
    auraai_affiliate_ensure_tables($con);
    $stmt = $con->prepare(
        'SELECT w.*, m.method_type, m.label, m.wallet_address, m.bank_name, m.account_number
         FROM affiliate_withdrawals w
         INNER JOIN affiliate_payout_methods m ON m.id = w.payout_method_id
         WHERE w.affiliate_id = ?
         ORDER BY w.requested_at DESC
         LIMIT ?'
    );
    $stmt->bind_param('ii', $affiliateId, $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows ?: [];
}

function auraai_affiliate_by_email(mysqli $con, string $email): ?array
{
    auraai_affiliate_ensure_tables($con);
    $email = auraai_sec_email($email);
    if ($email === null) {
        return null;
    }
    $stmt = $con->prepare('SELECT * FROM affiliates WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function auraai_affiliate_by_admin_id(mysqli $con, int $adminId): ?array
{
    auraai_affiliate_ensure_tables($con);
    if ($adminId <= 0) {
        return null;
    }
    $stmt = $con->prepare('SELECT * FROM affiliates WHERE admin_id = ? LIMIT 1');
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function auraai_affiliate_link_admin_id(mysqli $con, int $affiliateId, int $adminId): void
{
    if ($affiliateId <= 0 || $adminId <= 0) {
        return;
    }
    $stmt = $con->prepare('UPDATE affiliates SET admin_id = ?, updated_at = NOW() WHERE id = ? AND (admin_id IS NULL OR admin_id = 0 OR admin_id = ?)');
    $stmt->bind_param('iii', $adminId, $affiliateId, $adminId);
    $stmt->execute();
    $stmt->close();
}

function auraai_affiliate_sync_admin_password(mysqli $con, int $affiliateId, string $adminPassword): void
{
    $adminPassword = auraai_sec_password($adminPassword, 1, 255);
    if ($affiliateId <= 0 || $adminPassword === null) {
        return;
    }
    $stmt = $con->prepare('UPDATE affiliates SET password = ?, updated_at = NOW() WHERE id = ?');
    $stmt->bind_param('si', $adminPassword, $affiliateId);
    $stmt->execute();
    $stmt->close();
}

/** Link mentor admin account to affiliate profile (create if needed). Uses mentor admin password. */
function auraai_affiliate_ensure_for_admin(
    mysqli $con,
    int $adminId,
    string $email,
    string $fullName,
    string $phone = '',
    string $adminPassword = ''
): array {
    auraai_affiliate_ensure_tables($con);

    $affiliate = auraai_affiliate_by_admin_id($con, $adminId);
    if (!$affiliate) {
        $affiliate = auraai_affiliate_by_email($con, $email);
    }

    if ($affiliate) {
        auraai_affiliate_link_admin_id($con, (int) $affiliate['id'], $adminId);
        if ($adminPassword !== '') {
            auraai_affiliate_sync_admin_password($con, (int) $affiliate['id'], $adminPassword);
        }

        return ['ok' => true, 'id' => (int) $affiliate['id']];
    }

    $fullName = auraai_sec_string($fullName, 120, 2) ?? 'Mentor';
    $email = auraai_sec_email($email);
    if ($email === null) {
        return ['ok' => false, 'error' => 'Invalid mentor email.'];
    }

    if ($adminPassword === '') {
        return ['ok' => false, 'error' => 'Could not read mentor credentials.'];
    }

    $code = auraai_affiliate_generate_code($con);
    $rate = AURAAI_AFFILIATE_MIN_COMMISSION;
    $status = 'active';
    $phone = auraai_sec_string($phone, 30, 0) ?? '';

    $stmt = $con->prepare(
        'INSERT INTO affiliates (admin_id, code, full_name, email, phone, password, commission_rate, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('isssssds', $adminId, $code, $fullName, $email, $phone, $adminPassword, $rate, $status);
    $ok = $stmt->execute();
    $affiliateId = $ok ? (int) $stmt->insert_id : 0;
    $stmt->close();

    if (!$ok) {
        return ['ok' => false, 'error' => 'Could not create affiliate profile.'];
    }

    auraai_affiliate_notify_welcome($email, $fullName, $code, true);

    return ['ok' => true, 'id' => $affiliateId, 'created' => true];
}

function auraai_affiliate_admin_overview(mysqli $con): array
{
    auraai_affiliate_ensure_tables($con);
    $sql = "SELECT a.*,
            (SELECT COUNT(*) FROM affiliate_clicks c WHERE c.affiliate_id = a.id) AS clicks,
            (SELECT COUNT(*) FROM affiliate_conversions cv WHERE cv.affiliate_id = a.id AND cv.status = 'confirmed') AS conversions,
            (SELECT COALESCE(SUM(cv.commission_cents), 0) FROM affiliate_conversions cv WHERE cv.affiliate_id = a.id AND cv.status = 'confirmed') AS earned_cents,
            (SELECT COALESCE(SUM(w.amount_cents), 0) FROM affiliate_withdrawals w WHERE w.affiliate_id = a.id AND w.status IN ('pending','processing')) AS pending_cents,
            (SELECT COALESCE(SUM(w.amount_cents), 0) FROM affiliate_withdrawals w WHERE w.affiliate_id = a.id AND w.status = 'paid') AS paid_cents
            FROM affiliates a
            ORDER BY earned_cents DESC, a.created_at DESC";
    $result = mysqli_query($con, $sql);
    $rows = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
    }

    return $rows;
}

/** All commission rows for one affiliate (admin detail view). */
function auraai_affiliate_admin_conversions(mysqli $con, int $affiliateId, int $limit = 500): array
{
    auraai_affiliate_ensure_tables($con);
    if ($affiliateId <= 0) {
        return [];
    }

    $limit = max(1, min(1000, $limit));
    $stmt = $con->prepare(
        "SELECT id, member_id, email, product_type, gateway, gateway_ref, amount_cents, currency,
                commission_cents, status, converted_at
         FROM affiliate_conversions
         WHERE affiliate_id = ?
         ORDER BY converted_at DESC
         LIMIT ?"
    );
    $stmt->bind_param('ii', $affiliateId, $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows ?: [];
}

function auraai_affiliate_admin_withdrawals(mysqli $con, ?string $statusFilter = null, int $limit = 100): array
{
    auraai_affiliate_ensure_tables($con);
    $allowed = ['pending', 'processing', 'paid', 'rejected'];
    $sql = "SELECT w.*, a.full_name, a.email AS affiliate_email, a.code AS affiliate_code,
                   m.method_type, m.label, m.wallet_address, m.account_name, m.bank_name, m.account_number, m.branch_code
            FROM affiliate_withdrawals w
            INNER JOIN affiliates a ON a.id = w.affiliate_id
            INNER JOIN affiliate_payout_methods m ON m.id = w.payout_method_id";
    if ($statusFilter !== null && in_array($statusFilter, $allowed, true)) {
        $esc = mysqli_real_escape_string($con, $statusFilter);
        $sql .= " WHERE w.status = '$esc'";
    }
    $sql .= ' ORDER BY FIELD(w.status, \'pending\', \'processing\', \'paid\', \'rejected\'), w.requested_at DESC LIMIT ' . (int) $limit;
    $result = mysqli_query($con, $sql);
    $rows = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
    }

    return $rows;
}

function auraai_affiliate_admin_payout_details(array $row): string
{
    $type = $row['method_type'] ?? '';
    if ($type === 'bank') {
        $parts = array_filter([
            $row['bank_name'] ?? '',
            $row['account_name'] ?? '',
            $row['account_number'] ?? '',
            !empty($row['branch_code']) ? 'Branch ' . $row['branch_code'] : '',
        ]);

        return implode(' · ', $parts);
    }

    return (string) ($row['wallet_address'] ?? '');
}

function auraai_affiliate_process_withdrawal(mysqli $con, int $withdrawalId, string $status, string $adminNotes = ''): array
{
    auraai_affiliate_ensure_tables($con);
    $status = auraai_sec_enum($status, ['processing', 'paid', 'rejected']);
    if ($status === null) {
        return ['ok' => false, 'error' => 'Invalid withdrawal status.'];
    }

    $adminNotes = auraai_sec_string($adminNotes, 500, 0) ?? '';

    $stmt = $con->prepare(
        'SELECT w.id, w.status, w.amount_cents, w.affiliate_id, a.email, a.full_name
         FROM affiliate_withdrawals w
         INNER JOIN affiliates a ON a.id = w.affiliate_id
         WHERE w.id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $withdrawalId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['ok' => false, 'error' => 'Withdrawal request not found.'];
    }

    $current = $row['status'] ?? '';
    if ($current === 'paid' || $current === 'rejected') {
        return ['ok' => false, 'error' => 'This withdrawal has already been finalized.'];
    }

    $processedAt = in_array($status, ['paid', 'rejected'], true) ? date('Y-m-d H:i:s') : null;
    $stmt = $con->prepare('UPDATE affiliate_withdrawals SET status = ?, admin_notes = ?, processed_at = ? WHERE id = ?');
    $stmt->bind_param('sssi', $status, $adminNotes, $processedAt, $withdrawalId);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        return ['ok' => false, 'error' => 'Could not update withdrawal.'];
    }

    auraai_affiliate_notify_withdrawal_status($row, $status, $adminNotes);

    return ['ok' => true];
}

function auraai_affiliate_format_money(int $cents, string $currency = 'ZAR'): string
{
    $symbol = $currency === 'ZAR' ? 'R' : $currency . ' ';
    return $symbol . number_format($cents / 100, 2);
}

function auraai_affiliate_load_emails(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    require_once __DIR__ . '/bootstrap.php';
    auraai_email_bootstrap();
    $loaded = true;
}

function auraai_affiliate_send_email(string $label, callable $sender): void
{
    try {
        auraai_affiliate_load_emails();
        $ok = (bool) $sender();
        if (!$ok) {
            error_log('[NexTradeAI Affiliate] ' . $label . ' email failed: ' . auraai_email_last_error());
        }
    } catch (Throwable $e) {
        error_log('[NexTradeAI Affiliate] ' . $label . ' email error: ' . $e->getMessage());
    }
}

function auraai_affiliate_product_label(string $productType): string
{
    $map = ['vps' => 'VPS Membership', 'scanner' => 'AI Scanner', 'bundle' => 'Bundle'];

    return $map[$productType] ?? strtoupper($productType);
}

function auraai_affiliate_notify_welcome(string $email, string $fullName, string $refCode, bool $fromMentor): void
{
    auraai_affiliate_send_email('Welcome', function () use ($email, $fullName, $refCode, $fromMentor) {
        return auraai_email_affiliate_welcome($email, $fullName, auraai_affiliate_shop_link($refCode), $fromMentor);
    });
}

function auraai_affiliate_notify_commission(array $affiliate, int $commissionCents, string $productType, float $rate): void
{
    auraai_affiliate_send_email('Commission', function () use ($affiliate, $commissionCents, $productType, $rate) {
        return auraai_email_affiliate_commission_earned(
            (string) $affiliate['email'],
            (string) $affiliate['full_name'],
            auraai_affiliate_format_money($commissionCents),
            auraai_affiliate_product_label($productType),
            round($rate * 100, 1)
        );
    });
}

function auraai_affiliate_notify_withdrawal_requested(mysqli $con, int $affiliateId, int $methodId, int $amountCents): void
{
    try {
        $affiliate = auraai_affiliate_by_id($con, $affiliateId);
        if (!$affiliate) {
            return;
        }
        $methods = auraai_affiliate_payout_methods($con, $affiliateId);
        $method = null;
        foreach ($methods as $m) {
            if ((int) $m['id'] === $methodId) {
                $method = $m;
                break;
            }
        }
        if (!$method) {
            return;
        }

        auraai_affiliate_load_emails();
        $amountFmt = auraai_affiliate_format_money($amountCents);
        $methodLabel = auraai_affiliate_payout_method_label($method);
        $affEmail = (string) $affiliate['email'];
        $affName = (string) $affiliate['full_name'];
        $affCode = (string) $affiliate['code'];
        $payoutDetails = auraai_affiliate_admin_payout_details($method);

        auraai_affiliate_send_email('Withdrawal requested', function () use ($affEmail, $affName, $amountFmt, $methodLabel) {
            return auraai_email_affiliate_withdrawal_requested($affEmail, $affName, $amountFmt, $methodLabel);
        });
        auraai_affiliate_send_email('Withdrawal admin alert', function () use ($affName, $affEmail, $affCode, $amountFmt, $methodLabel, $payoutDetails) {
            return auraai_email_affiliate_withdrawal_admin(
                $affName,
                $affEmail,
                auraai_affiliate_shop_link($affCode),
                $amountFmt,
                $methodLabel,
                $payoutDetails
            );
        });
    } catch (Throwable $e) {
        error_log('[NexTradeAI Affiliate] Withdrawal request email failed: ' . $e->getMessage());
    }
}

function auraai_affiliate_notify_withdrawal_status(array $withdrawalRow, string $status, string $adminNotes = ''): void
{
    auraai_affiliate_send_email('Withdrawal status', function () use ($withdrawalRow, $status, $adminNotes) {
        return auraai_email_affiliate_withdrawal_status(
            (string) $withdrawalRow['email'],
            (string) $withdrawalRow['full_name'],
            auraai_affiliate_format_money((int) $withdrawalRow['amount_cents']),
            $status,
            $adminNotes
        );
    });
}

function auraai_affiliate_shop_link(string $code): string
{
    return 'https://auraai-vps.com/?ref=' . rawurlencode($code);
}

function auraai_affiliate_require_login(): array
{
    auraai_sec_session_start();
    $id = (int) ($_SESSION['affiliate_id'] ?? 0);
    if ($id <= 0) {
        header('Location: index.php');
        exit;
    }
    return ['id' => $id];
}

function auraai_affiliate_require_portal(mysqli $con): array
{
    $session = auraai_affiliate_require_login();

    return auraai_affiliate_assert_portal_access($con, $session['id']);
}

/** Block portal access for deleted/blocked accounts; allow active + suspended (paused). */
function auraai_affiliate_assert_portal_access(mysqli $con, int $affiliateId): array
{
    $affiliate = auraai_affiliate_by_id($con, $affiliateId);
    if (!$affiliate) {
        unset($_SESSION['affiliate_id']);
        header('Location: index.php?error=auth');
        exit;
    }

    $status = strtolower((string) ($affiliate['status'] ?? 'active'));
    if ($status === 'blocked') {
        unset($_SESSION['affiliate_id']);
        header('Location: index.php?error=blocked');
        exit;
    }
    if (!in_array($status, ['active', 'paused'], true)) {
        unset($_SESSION['affiliate_id']);
        header('Location: index.php?error=inactive');
        exit;
    }

    return $affiliate;
}

function auraai_affiliate_status_label(string $status): string
{
    $status = strtolower(trim($status));
    $map = [
        'active' => 'Active',
        'paused' => 'Suspended',
        'blocked' => 'Blocked',
    ];

    return $map[$status] ?? ucfirst($status);
}

function auraai_affiliate_status_pill_class(string $status): string
{
    $status = strtolower(trim($status));
    if ($status === 'active') {
        return 'active';
    }
    if ($status === 'blocked') {
        return 'rejected';
    }

    return 'paused';
}

function auraai_affiliate_admin_update_status(mysqli $con, int $affiliateId, string $status): array
{
    auraai_affiliate_ensure_tables($con);
    $status = auraai_sec_enum($status, ['active', 'paused', 'blocked']);
    if ($status === null || $affiliateId <= 0) {
        return ['ok' => false, 'error' => 'Invalid affiliate or status.'];
    }

    if (!auraai_affiliate_by_id($con, $affiliateId)) {
        return ['ok' => false, 'error' => 'Affiliate not found.'];
    }

    $stmt = $con->prepare('UPDATE affiliates SET status = ?, updated_at = NOW() WHERE id = ?');
    $stmt->bind_param('si', $status, $affiliateId);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        return ['ok' => false, 'error' => 'Could not update affiliate status.'];
    }

    return ['ok' => true, 'status' => $status];
}

function auraai_affiliate_admin_delete(mysqli $con, int $affiliateId): array
{
    auraai_affiliate_ensure_tables($con);
    if ($affiliateId <= 0) {
        return ['ok' => false, 'error' => 'Invalid affiliate.'];
    }

    $affiliate = auraai_affiliate_by_id($con, $affiliateId);
    if (!$affiliate) {
        return ['ok' => false, 'error' => 'Affiliate not found.'];
    }

    $pending = $con->prepare("SELECT id FROM affiliate_withdrawals WHERE affiliate_id = ? AND status IN ('pending','processing') LIMIT 1");
    $pending->bind_param('i', $affiliateId);
    $pending->execute();
    $hasPending = (bool) $pending->get_result()->fetch_assoc();
    $pending->close();

    if ($hasPending) {
        return ['ok' => false, 'error' => 'Resolve or reject pending withdrawals before deleting this affiliate.'];
    }

    $tables = [
        'affiliate_withdrawals',
        'affiliate_payout_methods',
        'affiliate_payment_ips',
        'affiliate_conversions',
        'affiliate_clicks',
        'affiliate_attributions',
        'affiliate_ip_attributions',
        'affiliate_visitor_attributions',
    ];

    foreach ($tables as $table) {
        $stmt = $con->prepare("DELETE FROM {$table} WHERE affiliate_id = ?");
        $stmt->bind_param('i', $affiliateId);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $con->prepare('DELETE FROM affiliates WHERE id = ?');
    $stmt->bind_param('i', $affiliateId);
    $ok = $stmt->execute();
    $deleted = $ok && $stmt->affected_rows > 0;
    $stmt->close();

    if (!$deleted) {
        return ['ok' => false, 'error' => 'Could not delete affiliate.'];
    }

    return ['ok' => true];
}

function auraai_affiliate_member_is_paid(mysqli $con, string $email): bool
{
    $email = auraai_sec_email($email);
    if ($email === null) {
        return false;
    }
    $stmt = $con->prepare('SELECT paid FROM members WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return !empty($row['paid']);
}

function auraai_affiliate_has_conversion_for_email(mysqli $con, string $email): bool
{
    $email = auraai_sec_email($email);
    if ($email === null) {
        return false;
    }
    $stmt = $con->prepare("SELECT id FROM affiliate_conversions WHERE email = ? AND status = 'confirmed' LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (bool) $row;
}

/** Persist customer IP when a paid member is saved and commission is attributed. */
function auraai_affiliate_record_payment_ip(
    mysqli $con,
    string $email,
    ?array $affiliate,
    ?string $clientIp,
    string $gateway,
    string $gatewayRef,
    ?int $memberId = null
): void {
    $email = auraai_sec_email($email);
    $clientIp = auraai_affiliate_normalize_client_ip($clientIp);
    if ($email === null || $clientIp === null || $gatewayRef === '') {
        return;
    }

    $affiliateId = $affiliate ? (int) $affiliate['id'] : 0;
    $code = $affiliate ? (string) $affiliate['code'] : '';
    $ipHash = auraai_affiliate_ip_hash($clientIp);
    $memberIdVal = $memberId ?? 0;

    $stmt = $con->prepare(
        'INSERT IGNORE INTO affiliate_payment_ips
        (member_id, email, affiliate_id, ref_code, ip_hash, gateway, gateway_ref)
        VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('isissss', $memberIdVal, $email, $affiliateId, $code, $ipHash, $gateway, $gatewayRef);
    $stmt->execute();
    $stmt->close();

    if ($affiliate && $code !== '') {
        auraai_affiliate_record_ip_attribution($con, $code, $clientIp);
    }
}

/**
 * Run after a member row exists with paid=1 (webhook or app confirm).
 * Records payment IP for the affiliate and credits commission when attributable.
 */
function auraai_affiliate_handle_member_payment_success(
    mysqli $con,
    string $email,
    string $gateway,
    string $gatewayRef,
    int $amountCents,
    string $productType = 'vps',
    ?string $referrer = null,
    $customFields = null,
    string $currency = 'ZAR',
    ?string $visitorId = null,
    ?string $clientIp = null
): bool {
    if (!auraai_affiliate_member_is_paid($con, $email)) {
        error_log("[Affiliate] Payment success skipped — member not paid in DB: {$email}");
        return false;
    }

    $memberId = auraai_affiliate_member_id_by_email($con, $email);
    if ($clientIp !== null && $clientIp !== '') {
        $affiliateForIp = auraai_affiliate_resolve_for_payment(
            $con,
            $email,
            $referrer,
            $customFields,
            $visitorId,
            $clientIp
        );
        auraai_affiliate_record_payment_ip(
            $con,
            $email,
            $affiliateForIp,
            $clientIp,
            $gateway,
            $gatewayRef,
            $memberId
        );
    }

    return auraai_affiliate_process_member_payment(
        $con,
        $email,
        $gateway,
        $gatewayRef,
        $amountCents,
        $productType,
        $referrer,
        $customFields,
        $currency,
        $visitorId,
        $clientIp
    );
}

/** Call from payment webhooks after a successful member payment. */
function auraai_affiliate_process_member_payment(
    mysqli $con,
    string $email,
    string $gateway,
    string $gatewayRef,
    int $amountCents,
    string $productType = 'vps',
    ?string $referrer = null,
    $customFields = null,
    string $currency = 'ZAR',
    ?string $visitorId = null,
    ?string $clientIp = null
): bool {
    $email = auraai_sec_email($email);
    if ($email === null || $gatewayRef === '') {
        return false;
    }

    $code = auraai_affiliate_extract_code_from_custom_fields(is_array($customFields) ? $customFields : null);
    if ($code !== null) {
        auraai_affiliate_record_attribution_signals($con, $code, $visitorId, $clientIp);
        auraai_affiliate_bind_email($con, $email, $code);
    }

    auraai_affiliate_ensure_email_attribution($con, $email, $code, $visitorId, $clientIp);

    $affiliate = auraai_affiliate_resolve_for_payment(
        $con,
        $email,
        $referrer,
        $customFields,
        $visitorId,
        $clientIp
    );
    if (!$affiliate) {
        error_log("[Affiliate] No attribution for {$email} (gateway={$gateway}, ref=" . ($code ?? 'none') . ', vid=' . ($visitorId ?? 'none') . ', ip=' . ($clientIp ?? 'none') . ')');
        return false;
    }
    $memberId = auraai_affiliate_member_id_by_email($con, $email);
    $inserted = auraai_affiliate_record_conversion(
        $con,
        $affiliate,
        $email,
        $productType,
        $gateway,
        $gatewayRef,
        $amountCents,
        $currency,
        $memberId
    );

    return $inserted;
}
