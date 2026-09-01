<?php
/**
 * Bootstrap NexTradeAI email library from admin/ or shop/ scripts.
 *
 * Usage (from public_html/admin/ or public_html/admin/home/):
 *   require_once auraai_email_bootstrap();
 */

function auraai_email_bootstrap(): string
{
    $candidates = [
        __DIR__ . '/includes/auraai-emails.php',
        __DIR__ . '/../includes/auraai-emails.php',
        __DIR__ . '/../../includes/auraai-emails.php',
        dirname(__DIR__, 2) . '/includes/auraai-emails.php',
        $_SERVER['DOCUMENT_ROOT'] . '/includes/auraai-emails.php',
    ];

    foreach ($candidates as $path) {
        if (is_readable($path)) {
            require_once $path;
            return $path;
        }
    }

    throw new RuntimeException('NexTradeAI email library not found. Upload website/includes/ to public_html/includes/');
}
