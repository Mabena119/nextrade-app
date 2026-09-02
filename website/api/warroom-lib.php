<?php
/**
 * Warroom — mint lifetime KILLZONE 3 licence keys and email them.
 */
declare(strict_types=1);

const NEXTRADE_WARROOM_DEFAULT_EA_NAME = 'KILLZONE 3';
const NEXTRADE_WARROOM_LIFETIME_PLAN_DAYS = 3652;

function nextrade_warroom_ea_name(): string
{
    if (defined('WARROOM_EA_NAME') && trim((string) WARROOM_EA_NAME) !== '') {
        return trim((string) WARROOM_EA_NAME);
    }
    return NEXTRADE_WARROOM_DEFAULT_EA_NAME;
}

function nextrade_warroom_load_secrets(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $candidates = [
        getenv('NEXTRADEAI_SECRETS_FILE') ?: '',
        (getenv('HOME') ?: '') . '/nextradeai-secrets.php',
        dirname(__DIR__, 2) . '/nextradeai-secrets.php',
    ];
    foreach ($candidates as $path) {
        if ($path !== '' && is_readable($path)) {
            require_once $path;
            return;
        }
    }
}

function nextrade_warroom_verify_secret(): bool
{
    nextrade_warroom_load_secrets();
    $expected = defined('WARROOM_API_SECRET') ? trim((string) WARROOM_API_SECRET) : '';
    if ($expected === '') {
        return false;
    }

    $header = trim((string) ($_SERVER['HTTP_X_WARROOM_SECRET'] ?? ''));
    if ($header === '') {
        $auth = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
        if (stripos($auth, 'Bearer ') === 0) {
            $header = trim(substr($auth, 7));
        }
    }

    return $header !== '' && hash_equals($expected, $header);
}

function nextrade_warroom_generate_license_key(): string
{
    $parts = [];
    for ($i = 0; $i < 4; $i++) {
        $parts[] = strtoupper(substr(bin2hex(random_bytes(3)), 0, 3));
    }
    return implode('-', $parts);
}

/** @return array{id:int,name:string,owner_id:int,mentor_name:string}|null */
function nextrade_warroom_resolve_ea(mysqli $db): ?array
{
    $eaName = nextrade_warroom_ea_name();
    $stmt = $db->prepare(
        'SELECT e.id, e.name, e.owner AS owner_id, a.displayname AS mentor_name
         FROM eas e
         LEFT JOIN admin a ON a.id = e.owner
         WHERE TRIM(e.name) = ?
         LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $eaName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || empty($row['id'])) {
        return null;
    }

    return [
        'id' => (int) $row['id'],
        'name' => (string) ($row['name'] ?? $eaName),
        'owner_id' => (int) ($row['owner_id'] ?? 0),
        'mentor_name' => (string) ($row['mentor_name'] ?? 'FXWAAROOM'),
    ];
}

function nextrade_warroom_lifetime_expires(): string
{
    $expires = new DateTime('today');
    $expires->add(new DateInterval('P' . NEXTRADE_WARROOM_LIFETIME_PLAN_DAYS . 'D'));
    return $expires->format('Y-m-d');
}

/**
 * @return array{ok:bool,error?:string,key?:string,created?:bool,email_sent?:bool}
 */
function nextrade_warroom_issue_key(mysqli $db, string $email): array
{
    $ea = nextrade_warroom_resolve_ea($db);
    if (!$ea || $ea['owner_id'] <= 0) {
        return ['ok' => false, 'error' => 'KILLZONE 3 automation not configured'];
    }

    $existing = $db->prepare(
        "SELECT k_ey FROM licences
         WHERE ea = ? AND LOWER(TRIM(user)) = LOWER(TRIM(?)) AND status = 'Active'
         ORDER BY id DESC LIMIT 1"
    );
    if (!$existing) {
        return ['ok' => false, 'error' => 'Database error'];
    }
    $existing->bind_param('is', $ea['id'], $email);
    $existing->execute();
    $existingRow = $existing->get_result()->fetch_assoc();
    $existing->close();

    $created = false;
    if ($existingRow && !empty($existingRow['k_ey'])) {
        $licenseKey = (string) $existingRow['k_ey'];
    } else {
        $licenseKey = nextrade_warroom_generate_license_key();
        $expires = nextrade_warroom_lifetime_expires();
        $plan = NEXTRADE_WARROOM_LIFETIME_PLAN_DAYS;
        $ownerId = $ea['owner_id'];
        $eaId = $ea['id'];

        $insert = $db->prepare(
            "INSERT INTO licences (owner, ea, user, k_ey, expires, plan, status)
             VALUES (?, ?, ?, ?, ?, ?, 'Active')"
        );
        if (!$insert) {
            return ['ok' => false, 'error' => 'Database error'];
        }
        $insert->bind_param('iisssi', $ownerId, $eaId, $email, $licenseKey, $expires, $plan);
        $ok = $insert->execute();
        $insert->close();
        if (!$ok) {
            return ['ok' => false, 'error' => 'Could not create licence key'];
        }
        $created = true;
    }

    $emailSent = false;
    try {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/email-hooks.php';
        $emailSent = nextrade_email_license_key(
            $email,
            $licenseKey,
            $ea['name'],
            $ea['mentor_name']
        );
    } catch (Throwable $e) {
        error_log('[Warroom] email: ' . $e->getMessage());
    }

    return [
        'ok' => true,
        'key' => $licenseKey,
        'created' => $created,
        'email_sent' => $emailSent,
        'ea_name' => $ea['name'],
    ];
}
