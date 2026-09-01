<?php
/**
 * Shared members table helpers (app login / VPS membership).
 */

/**
 * Upsert a paid member row so the email can sign into the NexTradeAI app.
 *
 * @return array{ok:bool,action:string}
 */
function auraai_upsert_paid_member(mysqli $con, string $email, int $mentorId, string $token = ''): array
{
    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'action' => 'invalid_email'];
    }

    if ($mentorId < 1) {
        $mentorId = 1;
    }

    if ($token === '') {
        $token = 'admin-active-' . $mentorId;
    }

    $stmt = $con->prepare('SELECT id FROM members WHERE LOWER(email) = LOWER(?) LIMIT 1');
    if (!$stmt) {
        error_log('[NexTradeAI members] prepare select failed: ' . $con->error);
        return ['ok' => false, 'action' => 'failed'];
    }
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if ($exists) {
        $stmt = $con->prepare(
            'UPDATE members SET used = 0, sub_tocken = ?, paid = 1, mentor_id = ? WHERE LOWER(email) = LOWER(?)'
        );
        if (!$stmt) {
            error_log('[NexTradeAI members] prepare update failed: ' . $con->error);
            return ['ok' => false, 'action' => 'failed'];
        }
        $stmt->bind_param('sis', $token, $mentorId, $email);
        $action = 'updated';
    } else {
        $stmt = $con->prepare(
            'INSERT INTO members (used, email, sub_tocken, mentor_id, paid, scanner) VALUES (0, ?, ?, ?, 1, 0)'
        );
        if (!$stmt) {
            error_log('[NexTradeAI members] prepare insert failed: ' . $con->error);
            return ['ok' => false, 'action' => 'failed'];
        }
        $stmt->bind_param('ssi', $email, $token, $mentorId);
        $action = 'inserted';
    }

    $ok = $stmt->execute();
    if (!$ok) {
        error_log('[NexTradeAI members] upsert failed: ' . $stmt->error);
        $action = 'failed';
    }
    $stmt->close();

    return ['ok' => $ok, 'action' => $action];
}
