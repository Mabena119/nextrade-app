<?php
/**
 * Paginated Analytics keys — one indexed query per page (no full-table dump).
 */

function nextrade_stats_keys_cache_dir(): string
{
    $dir = __DIR__ . '/cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    return $dir;
}

function nextrade_bust_stats_keys_cache(int $ownerId): void
{
    $dir = nextrade_stats_keys_cache_dir();
    $pattern = $dir . '/keys_' . $ownerId . '_*.json';
    foreach (glob($pattern) ?: [] as $file) {
        @unlink($file);
    }
}

/**
 * @return array{rows: list<array>, total: int, page: int, per_page: int, pages: int, q: string}
 */
function nextrade_load_stats_keys_page(
    mysqli $con,
    int $ownerId,
    int $page = 1,
    int $perPage = 50,
    string $q = ''
): array {
    $perPage = max(10, min(100, $perPage));
    $page = max(1, $page);
    $q = trim($q);

    $where = "l.owner = {$ownerId}";
    if ($q !== '') {
        $safe = mysqli_real_escape_string($con, $q);
        $where .= " AND (l.k_ey LIKE '%{$safe}%' OR l.user LIKE '%{$safe}%' OR e.name LIKE '%{$safe}%')";
    }

    $total = 0;
    $countSql = "SELECT COUNT(*) AS c
                 FROM licences l
                 LEFT JOIN eas e ON e.id = l.ea AND e.owner = l.owner
                 WHERE {$where}";
    $countRes = mysqli_query($con, $countSql);
    if ($countRes && ($crow = mysqli_fetch_assoc($countRes))) {
        $total = (int) ($crow['c'] ?? 0);
    }

    $pages = max(1, (int) ceil($total / $perPage));
    if ($page > $pages) {
        $page = $pages;
    }
    $offset = ($page - 1) * $perPage;

    $rows = [];
    $sql = "SELECT l.id, l.user, l.k_ey, l.ea, l.status, l.created,
                   COALESCE(e.name, '') AS ea_name
            FROM licences l
            LEFT JOIN eas e ON e.id = l.ea AND e.owner = l.owner
            WHERE {$where}
            ORDER BY l.id DESC
            LIMIT {$perPage} OFFSET {$offset}";
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

    return [
        'rows' => $rows,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'pages' => $pages,
        'q' => $q,
    ];
}

/** @deprecated keep for older call sites */
function nextrade_load_stats_keys(mysqli $con, int $ownerId, int $ttlSeconds = 20): array
{
    $page = nextrade_load_stats_keys_page($con, $ownerId, 1, 50, '');
    return $page['rows'];
}
