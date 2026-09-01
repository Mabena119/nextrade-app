<?php
/**
 * NexTradeAI JSON API bootstrap (members, licences, signals).
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/php-includes/connect.php';

function nextrade_api_json(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function nextrade_api_read_json(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function nextrade_api_normalize_email(?string $value): ?string
{
    $email = strtolower(trim((string) $value));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    return $email;
}

function nextrade_api_normalize_license(?string $value): string
{
    return strtoupper(str_replace('-', '', trim((string) $value)));
}

function nextrade_api_db(): mysqli
{
    global $con;
    if (!($con instanceof mysqli)) {
        throw new RuntimeException('Database unavailable');
    }
    return $con;
}
