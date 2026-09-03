<?php

function copy_trades_wants_json(): bool
{
    return stripos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false
        || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch';
}

function copy_trades_finish(bool $ok, string $query = '', int $httpCode = 200): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    $target = 'copy_trades.php' . $query;
    if (copy_trades_wants_json()) {
        http_response_code($ok ? $httpCode : ($httpCode >= 400 ? $httpCode : 400));
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => $ok,
            'redirect' => $target,
        ], JSON_UNESCAPED_SLASHES);
        exit();
    }

    header('Location: ' . $target, true, 303);
    exit();
}

function copy_trades_require_post(): void
{
    if (strcasecmp((string) ($_SERVER['REQUEST_METHOD'] ?? ''), 'POST') === 0) {
        return;
    }
    copy_trades_finish(true, '');
}
