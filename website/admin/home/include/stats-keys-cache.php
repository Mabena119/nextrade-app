<?php
/**
 * Fast Analytics keys list: one JOIN query + short file cache per mentor.
 */

function nextrade_stats_keys_cache_path(int $ownerId): string
{
    return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'nextrade_stats_keys_' . $ownerId . '.json';
}

function nextrade_bust_stats_keys_cache(int $ownerId): void
{
    $path = nextrade_stats_keys_cache_path($ownerId);
    if (is_file($path)) {
        @unlink($path);
    }
}

/**
 * @return list<array{id:int,user:string,k_ey:string,ea:int,status:string,created:string,ea_name:string}>
 */
function nextrade_load_stats_keys(mysqli $con, int $ownerId, int $ttlSeconds = 20): array
{
    $cachePath = nextrade_stats_keys_cache_path($ownerId);
    if (is_readable($cachePath)) {
        $mtime = @filemtime($cachePath);
        if ($mtime !== false && (time() - $mtime) <= $ttlSeconds) {
            $raw = @file_get_contents($cachePath);
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && isset($decoded['rows']) && is_array($decoded['rows'])) {
                    return $decoded['rows'];
                }
            }
        }
    }

    $rows = [];
    $sql = "SELECT l.id, l.user, l.k_ey, l.ea, l.status, l.created,
                   COALESCE(e.name, '') AS ea_name
            FROM licences l
            LEFT JOIN eas e ON e.id = l.ea AND e.owner = l.owner
            WHERE l.owner = {$ownerId}
            ORDER BY l.id DESC";
    $query = mysqli_query($con, $sql);
    if ($query) {
        while ($u = mysqli_fetch_assoc($query)) {
            $rows[] = [
                'id' => (int) ($u['id'] ?? 0),
                'user' => (string) ($u['user'] ?? ''),
                'k_ey' => (string) ($u['k_ey'] ?? ''),
                'ea' => (int) ($u['ea'] ?? 0),
                'status' => (string) ($u['status'] ?? ''),
                'created' => (string) ($u['created'] ?? ''),
                'ea_name' => (string) ($u['ea_name'] ?? ''),
            ];
        }
    }

    @file_put_contents(
        $cachePath,
        json_encode(['ts' => time(), 'rows' => $rows], JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );

    return $rows;
}
