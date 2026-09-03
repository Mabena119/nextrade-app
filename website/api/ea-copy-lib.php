<?php
declare(strict_types=1);

/**
 * Shared helpers for MT5 / Python copy-trade publishers (EA secret_code auth).
 */

/** Minutes a copy signal stays executable (results = active/pending). */
const NEXTRADE_SIGNAL_ACTIVE_MINUTES = 10;

/** Total minutes before a signal row is deleted from the database. */
const NEXTRADE_SIGNAL_TTL_MINUTES = 20;

/** SQL fragment: signal `time` is still inside the execution window. */
function nextrade_signal_active_time_sql(string $timeColumn = 'time'): string
{
    $mins = (int) NEXTRADE_SIGNAL_ACTIVE_MINUTES;
    return "{$timeColumn} > UTC_TIMESTAMP() - INTERVAL {$mins} MINUTE";
}

/** SQL fragment: open copy signal (active/pending + within execution window). */
function nextrade_signal_open_where_sql(string $resultsColumn = 'results', string $timeColumn = 'time'): string
{
    return "LOWER(COALESCE({$resultsColumn}, '')) IN ('active', 'pending')
            AND " . nextrade_signal_active_time_sql($timeColumn);
}

/**
 * Mark signals inactive after the execution window, delete rows older than TTL.
 *
 * @return array{inactivated: int, deleted: int}
 */
function nextrade_purge_expired_copy_signals(mysqli $db): array
{
    $activeMins = (int) NEXTRADE_SIGNAL_ACTIVE_MINUTES;
    $ttlMins = (int) NEXTRADE_SIGNAL_TTL_MINUTES;
    $now = gmdate('Y-m-d H:i:s');

    $inactivated = 0;
    $inactiveStmt = $db->prepare(
        "UPDATE signals
         SET results = 'inactive', latestupdate = ?
         WHERE LOWER(COALESCE(results, '')) IN ('active', 'pending')
           AND time <= UTC_TIMESTAMP() - INTERVAL {$activeMins} MINUTE"
    );
    if ($inactiveStmt) {
        $inactiveStmt->bind_param('s', $now);
        $inactiveStmt->execute();
        $inactivated = $inactiveStmt->affected_rows;
        $inactiveStmt->close();
    }

    $deleted = 0;
    $deleteStmt = $db->prepare(
        "DELETE FROM signals
         WHERE time <= UTC_TIMESTAMP() - INTERVAL {$ttlMins} MINUTE"
    );
    if ($deleteStmt) {
        $deleteStmt->execute();
        $deleted = $deleteStmt->affected_rows;
        $deleteStmt->close();
    }

    return ['inactivated' => $inactivated, 'deleted' => $deleted];
}

/** Effective UI/API status from row age + stored results. */
function nextrade_signal_effective_status(array $row): string
{
    $stored = strtolower(trim((string) ($row['results'] ?? '')));
    $timeRaw = trim((string) ($row['time'] ?? ''));
    if ($timeRaw === '') {
        return $stored !== '' ? $stored : 'unknown';
    }

    $ts = strtotime($timeRaw . ' UTC');
    if ($ts === false) {
        return $stored !== '' ? $stored : 'unknown';
    }

    $ageSeconds = time() - $ts;
    if ($ageSeconds >= NEXTRADE_SIGNAL_TTL_MINUTES * 60) {
        return 'expired';
    }
    if ($ageSeconds >= NEXTRADE_SIGNAL_ACTIVE_MINUTES * 60) {
        return 'inactive';
    }
    if ($stored === 'active' || $stored === 'pending') {
        return 'active';
    }

    return $stored !== '' ? $stored : 'unknown';
}

function nextrade_signal_is_executable(array $row): bool
{
    return nextrade_signal_effective_status($row) === 'active';
}

function nextrade_ea_by_secret(mysqli $db, string $secret): ?array
{
    $secret = trim($secret);
    if ($secret === '') {
        return null;
    }

    $stmt = $db->prepare(
        'SELECT id, name, martingale, secret_code, owner
         FROM eas
         WHERE secret_code = ?
         LIMIT 1'
    );
    $stmt->bind_param('s', $secret);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function nextrade_normalize_optional_level($value): string
{
    $v = trim((string) ($value ?? ''));
    if ($v === '' || !is_numeric($v) || (float) $v === 0.0) {
        return '0';
    }
    return $v;
}

function nextrade_normalize_trade_action(?string $action): ?string
{
    $a = strtolower(trim((string) $action));
    if ($a === 'buy' || $a === 'sell') {
        return $a;
    }
    return null;
}

/** Resolve broker symbol (e.g. EURUSDm) to a symbol configured on this EA. */
function nextrade_resolve_ea_symbol(mysqli $db, int $eaId, string $rawAsset): ?string
{
    $raw = strtoupper(trim($rawAsset));
    if ($raw === '') {
        return null;
    }

    $stmt = $db->prepare('SELECT name FROM symbols WHERE ea = ?');
    $stmt->bind_param('i', $eaId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (!$rows) {
        return null;
    }

    $exact = null;
    $prefix = null;
    foreach ($rows as $row) {
        $name = strtoupper(trim((string) ($row['name'] ?? '')));
        if ($name === '') {
            continue;
        }
        if ($raw === $name) {
            $exact = (string) $row['name'];
            break;
        }
        if (strpos($raw, $name) === 0 && ($prefix === null || strlen($name) > strlen($prefix))) {
            $prefix = (string) $row['name'];
        }
    }

    return $exact ?? $prefix;
}

/**
 * @param array<string, mixed> $signal
 * @return array{ok: bool, message: string, signal_id?: int}
 */
function nextrade_insert_copy_signal(mysqli $db, int $eaId, bool $isMartingale, array $signal): array
{
    $asset = nextrade_resolve_ea_symbol($db, $eaId, (string) ($signal['asset'] ?? ''));
    if ($asset === null) {
        return ['ok' => false, 'message' => 'symbol_not_allowed'];
    }

    $action = nextrade_normalize_trade_action($signal['action'] ?? null);
    if ($action === null) {
        return ['ok' => false, 'message' => 'invalid_action'];
    }

    $price = trim((string) ($signal['price'] ?? '0'));
    $tp = nextrade_normalize_optional_level($signal['tp'] ?? null);
    $sl = nextrade_normalize_optional_level($signal['sl'] ?? null);
    $lotRaw = trim((string) ($signal['lot'] ?? ''));

    $lot = '';
    if ($isMartingale) {
        if ($lotRaw === '' || !is_numeric($lotRaw) || (float) $lotRaw <= 0) {
            return ['ok' => false, 'message' => 'lot_required'];
        }
        $lot = (string) $lotRaw;
    } elseif ($lotRaw !== '' && is_numeric($lotRaw) && (float) $lotRaw > 0) {
        $lot = (string) $lotRaw;
    }

    $type = 'all';
    $results = 'active';
    $now = gmdate('Y-m-d H:i:s');

    $insert = $db->prepare(
        'INSERT INTO signals (ea, asset, type, action, price, tp, sl, lot, results, time, latestupdate)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insert->bind_param(
        'issssssssss',
        $eaId,
        $asset,
        $type,
        $action,
        $price,
        $tp,
        $sl,
        $lot,
        $results,
        $now,
        $now
    );

    if (!$insert->execute()) {
        error_log('[NexTradeAI] insert signal failed: ' . $db->error);
        return ['ok' => false, 'message' => 'database_error'];
    }

    $signalId = (int) $insert->insert_id;
    $insert->close();

    return ['ok' => true, 'message' => 'accept', 'signal_id' => $signalId];
}

/** Close (delete) active signals for EA + asset — mirrors admin close_signal.php. */
function nextrade_close_copy_signal(mysqli $db, int $eaId, string $rawAsset): array
{
    $asset = nextrade_resolve_ea_symbol($db, $eaId, $rawAsset);
    if ($asset === null) {
        return ['ok' => false, 'message' => 'symbol_not_allowed'];
    }

    $stmt = $db->prepare(
        "DELETE FROM signals
         WHERE ea = ? AND asset = ? AND " . nextrade_signal_open_where_sql('results', 'time') . "
         ORDER BY latestupdate DESC
         LIMIT 1"
    );
    $stmt->bind_param('is', $eaId, $asset);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected < 1) {
        return ['ok' => false, 'message' => 'no_active_signal'];
    }

    return ['ok' => true, 'message' => 'accept'];
}

/** MySQL stores signal datetimes in UTC — expose ISO Z to clients. */
function nextrade_mysql_utc_datetime_to_iso(?string $value): ?string
{
    if ($value === null) {
        return null;
    }
    $raw = trim($value);
    if ($raw === '') {
        return null;
    }
    if (preg_match('/[zZ]$/', $raw) || preg_match('/[+-]\d{2}:?\d{2}$/', $raw)) {
        $ts = strtotime($raw);
        return $ts !== false ? gmdate('Y-m-d\TH:i:s.000\Z', $ts) : $raw;
    }
    if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}:\d{2})/', $raw, $m)) {
        return $m[1] . 'T' . $m[2] . '.000Z';
    }

    return $raw;
}

/** @param array<string, mixed> $row */
function nextrade_normalize_signal_row_timestamps(array $row): array
{
    foreach (['latestupdate', 'time'] as $field) {
        if (!array_key_exists($field, $row) || $row[$field] === null || $row[$field] === '') {
            continue;
        }
        $iso = nextrade_mysql_utc_datetime_to_iso((string) $row[$field]);
        if ($iso !== null) {
            $row[$field] = $iso;
        }
    }

    return $row;
}
