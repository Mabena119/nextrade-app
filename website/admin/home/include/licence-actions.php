<?php

/**
 * Shared licence restore / pause helpers for admin console.
 */
function nextrade_admin_session(): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['id'], $_SESSION['username'])) {
        return ['ok' => false, 'redirect' => '../index.php'];
    }

    $adminId = (int) get_admin($_SESSION['username'], 'id');
    $isSuper = (bool) get_admin($_SESSION['username'], 'super');

    return [
        'ok' => true,
        'admin_id' => $adminId,
        'is_super' => $isSuper,
        'username' => (string) $_SESSION['username'],
    ];
}

function nextrade_can_manage_licence_row(array $row, int $adminId, bool $isSuper): bool
{
    if ($isSuper) {
        return true;
    }

    return (int) ($row['owner'] ?? 0) === $adminId;
}

function nextrade_fetch_licence_by_key(mysqli $con, string $key): ?array
{
    $stmt = $con->prepare('SELECT * FROM licences WHERE k_ey = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function nextrade_reactivate_licence(mysqli $con, string $key, int $adminId, bool $isSuper): array
{
    $key = trim($key);
    if ($key === '') {
        return ['ok' => false, 'error' => 'missing_key'];
    }

    $row = nextrade_fetch_licence_by_key($con, $key);
    if (!$row) {
        return ['ok' => false, 'error' => 'not_found', 'key' => $key];
    }

    if (!nextrade_can_manage_licence_row($row, $adminId, $isSuper)) {
        return ['ok' => false, 'error' => 'forbidden', 'key' => $key];
    }

    $planDays = max(1, (int) ($row['plan'] ?? 30));
    $expiresAt = new DateTime('today');
    $expiresAt->add(new DateInterval('P' . $planDays . 'D'));
    $expires = $expiresAt->format('Y-m-d');

    $stmt = $con->prepare(
        "UPDATE licences SET status = 'Active', expires = ?, phone_secret_code = 'None' WHERE k_ey = ?"
    );
    if (!$stmt) {
        return ['ok' => false, 'error' => 'db_error', 'key' => $key];
    }
    $stmt->bind_param('ss', $expires, $key);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        return ['ok' => false, 'error' => 'db_error', 'key' => $key];
    }

    return ['ok' => true, 'key' => $key, 'expires' => $expires];
}

function nextrade_deactivate_licence(mysqli $con, string $key, int $adminId, bool $isSuper): array
{
    $key = trim($key);
    if ($key === '') {
        return ['ok' => false, 'error' => 'missing_key'];
    }

    $row = nextrade_fetch_licence_by_key($con, $key);
    if (!$row) {
        return ['ok' => false, 'error' => 'not_found', 'key' => $key];
    }

    if (!nextrade_can_manage_licence_row($row, $adminId, $isSuper)) {
        return ['ok' => false, 'error' => 'forbidden', 'key' => $key];
    }

    $stmt = $con->prepare("UPDATE licences SET status = 'Expired' WHERE k_ey = ?");
    if (!$stmt) {
        return ['ok' => false, 'error' => 'db_error', 'key' => $key];
    }
    $stmt->bind_param('s', $key);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        return ['ok' => false, 'error' => 'db_error', 'key' => $key];
    }

    return ['ok' => true, 'key' => $key];
}

/** Clear device binding so the customer can activate the key on a new phone. */
function nextrade_unbind_licence_device(mysqli $con, string $key, int $adminId, bool $isSuper): array
{
    $key = trim($key);
    if ($key === '') {
        return ['ok' => false, 'error' => 'missing_key'];
    }

    $row = nextrade_fetch_licence_by_key($con, $key);
    if (!$row) {
        return ['ok' => false, 'error' => 'not_found', 'key' => $key];
    }

    if (!nextrade_can_manage_licence_row($row, $adminId, $isSuper)) {
        return ['ok' => false, 'error' => 'forbidden', 'key' => $key];
    }

    $stmt = $con->prepare("UPDATE licences SET phone_secret_code = 'None' WHERE k_ey = ?");
    if (!$stmt) {
        return ['ok' => false, 'error' => 'db_error', 'key' => $key];
    }
    $stmt->bind_param('s', $key);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        return ['ok' => false, 'error' => 'db_error', 'key' => $key];
    }

    return ['ok' => true, 'key' => $key];
}

function nextrade_reactivate_member_email(mysqli $con, string $email): array
{
    $email = trim($email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'invalid_email'];
    }

    $stmt = $con->prepare('SELECT id FROM members WHERE email = ? LIMIT 1');
    if (!$stmt) {
        return ['ok' => false, 'error' => 'db_error', 'email' => $email];
    }
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['ok' => false, 'error' => 'not_found', 'email' => $email];
    }

    $upd = $con->prepare('UPDATE members SET used = 0 WHERE email = ?');
    if (!$upd) {
        return ['ok' => false, 'error' => 'db_error', 'email' => $email];
    }
    $upd->bind_param('s', $email);
    $ok = $upd->execute();
    $upd->close();

    if (!$ok) {
        return ['ok' => false, 'error' => 'db_error', 'email' => $email];
    }

    return ['ok' => true, 'email' => $email];
}

function nextrade_action_message(string $title, string $text, bool $success, string $backUrl, string $backLabel): void
{
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/site-config.php';
    $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $textEsc = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $backUrlEsc = htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8');
    $backLabelEsc = htmlspecialchars($backLabel, ENT_QUOTES, 'UTF-8');
    $icon = $success ? 'ti-circle-check' : 'ti-circle-x';
    $badgeClass = $success ? 'aura-badge-ok' : 'aura-badge-bad';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $titleEsc; ?> | NexTradeAI Admin</title>
  <link rel="icon" href="<?php echo htmlspecialchars(NEXTRADE_LOGO_URL, ENT_QUOTES, 'UTF-8'); ?>" />
  <link rel="stylesheet" href="/assets/css/platform.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.19.0/dist/tabler-icons.min.css" />
  <link rel="stylesheet" href="../assets/css/aura-console.css" />
</head>
<body class="aura-platform aura-console">
  <div class="aura-atmosphere" aria-hidden="true"></div>
  <main class="aura-console-page" style="max-width:520px;margin:4rem auto;padding:0 1.25rem;">
    <div class="aura-panel" style="text-align:center;padding:2.5rem 1.75rem;">
      <img src="<?php echo htmlspecialchars(NEXTRADE_LOGO_URL, ENT_QUOTES, 'UTF-8'); ?>" alt="NexTradeAI" width="56" height="56" style="margin-bottom:1.25rem;" />
      <span class="aura-badge <?php echo $badgeClass; ?>" style="margin-bottom:1rem;display:inline-flex;align-items:center;gap:0.35rem;">
        <i class="ti <?php echo $icon; ?>"></i> <?php echo $titleEsc; ?>
      </span>
      <p style="margin:0 0 1.5rem;color:var(--aura-muted);line-height:1.6;"><?php echo $textEsc; ?></p>
      <a class="aura-btn aura-btn-primary" href="<?php echo $backUrlEsc; ?>"><?php echo $backLabelEsc; ?></a>
    </div>
  </main>
  <script>setTimeout(function(){ window.location.href = <?php echo json_encode($backUrl); ?>; }, 4000);</script>
</body>
</html>
    <?php
}
