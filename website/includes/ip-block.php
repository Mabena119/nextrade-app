<?php
/**
 * IP block list — super admin managed, file-cached for fast checks.
 */
if (!function_exists('auraai_sec_storage_dir')) {
    require_once __DIR__ . '/security.php';
}

function auraai_ip_block_cache_file(): string
{
    return auraai_sec_storage_dir() . '/blocked_ips.cache.json';
}

function auraai_ip_block_normalize(?string $ip): ?string
{
    $ip = trim((string) $ip);
    if ($ip === '') {
        return null;
    }
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return null;
    }
    return $ip;
}

function auraai_ip_block_request_exempt(): bool
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    if (preg_match('#^/admin(/|$)#', $uri)) {
        return true;
    }
    return false;
}

function auraai_ip_block_ensure_table(mysqli $con): void
{
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS blocked_ips (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        reason VARCHAR(255) NOT NULL DEFAULT '',
        blocked_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NULL,
        UNIQUE KEY uq_ip (ip_address),
        KEY idx_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** @return list<array{id:int,ip_address:string,reason:string,blocked_by:?int,created_at:string,expires_at:?string}> */
function auraai_ip_block_load_cache(): array
{
    $file = auraai_ip_block_cache_file();
    if (!is_readable($file)) {
        return [];
    }
    $raw = @file_get_contents($file);
    $decoded = $raw ? json_decode($raw, true) : null;
    if (!is_array($decoded)) {
        return [];
    }
    $now = time();
    $active = [];
    foreach ($decoded as $row) {
        if (!is_array($row) || empty($row['ip_address'])) {
            continue;
        }
        $expires = $row['expires_at'] ?? null;
        if ($expires !== null && $expires !== '' && strtotime((string) $expires) < $now) {
            continue;
        }
        $active[] = $row;
    }
    return $active;
}

function auraai_ip_block_save_cache(array $rows): void
{
    @file_put_contents(
        auraai_ip_block_cache_file(),
        json_encode(array_values($rows), JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

function auraai_ip_block_refresh_cache(mysqli $con): void
{
    auraai_ip_block_ensure_table($con);
    $sql = "SELECT id, ip_address, reason, blocked_by, created_at, expires_at
            FROM blocked_ips
            WHERE expires_at IS NULL OR expires_at > NOW()
            ORDER BY created_at DESC";
    $result = mysqli_query($con, $sql);
    $rows = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
    }
    auraai_ip_block_save_cache($rows);
}

function auraai_ip_block_is_blocked(?string $ip = null): bool
{
    $ip = auraai_ip_block_normalize($ip ?? auraai_sec_client_ip());
    if ($ip === null) {
        return false;
    }
    foreach (auraai_ip_block_load_cache() as $row) {
        if (($row['ip_address'] ?? '') === $ip) {
            return true;
        }
    }
    return false;
}

function auraai_sec_ip_block_check_or_exit(): void
{
    if (auraai_ip_block_request_exempt()) {
        return;
    }
    if (!auraai_ip_block_is_blocked()) {
        return;
    }
    http_response_code(403);
    if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Access denied.']);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Access denied</title></head><body style="margin:0;font-family:system-ui,sans-serif;background:#0f1115;color:#e5e7eb;display:flex;min-height:100vh;align-items:center;justify-content:center;padding:24px;">'
            . '<div style="max-width:420px;text-align:center;"><h1 style="margin:0 0 12px;font-size:1.5rem;">Access denied</h1>'
            . '<p style="margin:0;color:#9ca3af;line-height:1.6;">Your connection is not allowed to access this site.</p></div></body></html>';
    }
    exit;
}

function auraai_ip_block_list(mysqli $con): array
{
    auraai_ip_block_ensure_table($con);
    $result = mysqli_query(
        $con,
        "SELECT b.*, a.displayname AS blocked_by_name
         FROM blocked_ips b
         LEFT JOIN admin a ON a.id = b.blocked_by
         ORDER BY b.created_at DESC"
    );
    $rows = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function auraai_ip_block_add(mysqli $con, string $ip, string $reason, ?int $adminId, ?string $expiresAt = null): array
{
    $ip = auraai_ip_block_normalize($ip);
    if ($ip === null) {
        return ['ok' => false, 'error' => 'Enter a valid IPv4 or IPv6 address.'];
    }
    $reason = auraai_sec_string($reason, 255, 0) ?? '';
    auraai_ip_block_ensure_table($con);

    $stmt = $con->prepare(
        'INSERT INTO blocked_ips (ip_address, reason, blocked_by, expires_at)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE reason = VALUES(reason), blocked_by = VALUES(blocked_by), expires_at = VALUES(expires_at), created_at = NOW()'
    );
    $adminVal = ($adminId !== null && $adminId > 0) ? $adminId : 0;
    $expiresVal = ($expiresAt !== null && $expiresAt !== '') ? $expiresAt : null;
    $stmt->bind_param('ssis', $ip, $reason, $adminVal, $expiresVal);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) {
        return ['ok' => false, 'error' => 'Could not save block.'];
    }

    auraai_ip_block_refresh_cache($con);
    return ['ok' => true, 'ip' => $ip];
}

function auraai_ip_block_remove(mysqli $con, int $id): array
{
    if ($id <= 0) {
        return ['ok' => false, 'error' => 'Invalid block id.'];
    }
    auraai_ip_block_ensure_table($con);
    $stmt = $con->prepare('DELETE FROM blocked_ips WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $deleted = $stmt->affected_rows > 0;
    $stmt->close();
    if (!$deleted) {
        return ['ok' => false, 'error' => 'Block not found.'];
    }
    auraai_ip_block_refresh_cache($con);
    return ['ok' => true];
}

function auraai_ip_block_bootstrap_cache(mysqli $con): void
{
    if (is_readable(auraai_ip_block_cache_file())) {
        return;
    }
    auraai_ip_block_refresh_cache($con);
}
