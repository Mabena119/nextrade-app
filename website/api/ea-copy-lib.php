<?php
declare(strict_types=1);

/**
 * Shared helpers for MT5 / Python copy-trade publishers (EA secret_code auth).
 */

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
    $tp = trim((string) ($signal['tp'] ?? '0'));
    $sl = trim((string) ($signal['sl'] ?? '0'));
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
         WHERE ea = ? AND asset = ? AND LOWER(COALESCE(results, '')) IN ('active', 'pending')
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
