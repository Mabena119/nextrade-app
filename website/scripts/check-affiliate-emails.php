<?php
/**
 * One-off: inspect affiliate attribution for specific emails.
 * Run on server: php /tmp/check-affiliate-emails.php
 * Delete after use.
 */
require __DIR__ . '/../public_html/admin/php-includes/connect.php';
require __DIR__ . '/../public_html/includes/affiliate.php';

auraai_affiliate_ensure_tables($con);

$emails = ['elden7825@gmail.com', 'collen.sibanda@yahoo.com'];

foreach ($emails as $raw) {
    $e = strtolower(trim($raw));
    echo "===== {$e} =====\n";

    $stmt = $con->prepare('SELECT id, email, paid, scanner, mentor_id, sub_tocken, used FROM members WHERE LOWER(email) = LOWER(?) LIMIT 1');
    $stmt->bind_param('s', $e);
    $stmt->execute();
    $m = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    echo 'member: ' . json_encode($m) . "\n";

    $stmt = $con->prepare('SELECT att.*, a.code, a.full_name FROM affiliate_attributions att LEFT JOIN affiliates a ON a.id = att.affiliate_id WHERE LOWER(att.email) = LOWER(?) ORDER BY att.created_at DESC');
    $stmt->bind_param('s', $e);
    $stmt->execute();
    $r = $stmt->get_result();
    $found = false;
    while ($row = $r->fetch_assoc()) {
        $found = true;
        echo 'attrib: ' . json_encode($row) . "\n";
    }
    if (!$found) {
        echo "attrib: none\n";
    }
    $stmt->close();

    $stmt = $con->prepare('SELECT c.*, a.code, a.full_name FROM affiliate_conversions c LEFT JOIN affiliates a ON a.id = c.affiliate_id WHERE LOWER(c.email) = LOWER(?) ORDER BY c.converted_at DESC');
    $stmt->bind_param('s', $e);
    $stmt->execute();
    $r = $stmt->get_result();
    $found = false;
    while ($row = $r->fetch_assoc()) {
        $found = true;
        echo 'conv: ' . json_encode($row) . "\n";
    }
    if (!$found) {
        echo "conv: none\n";
    }
    $stmt->close();

    $aff = auraai_affiliate_resolve_for_payment($con, $e, null, null);
    echo 'resolve_now: ' . json_encode($aff ? ['id' => $aff['id'], 'code' => $aff['code'], 'name' => $aff['full_name']] : null) . "\n";
}
