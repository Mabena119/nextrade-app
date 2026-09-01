<?php
/**
 * Rename Analytics → Key analytics in sidebar + dashboard quick actions.
 * Idempotent — safe to run on every deploy.
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$root = dirname(__DIR__);
$files = [
    $root . '/include/header.php',
    $root . '/index.php',
];

$replacements = [
    '<span class="menu-title">Analytics</span>' => '<span class="menu-title">Key analytics</span>',
    '<span>Analytics</span>' => '<span>Key analytics</span>',
];

foreach ($files as $path) {
    if (!is_readable($path)) {
        fwrite(STDERR, "Skip missing: {$path}\n");
        continue;
    }
    $content = file_get_contents($path);
    $original = $content;
    foreach ($replacements as $from => $to) {
        $content = str_replace($from, $to, $content);
    }
    if ($content === $original) {
        echo "No change: {$path}\n";
        continue;
    }
    file_put_contents($path, $content);
    echo "Updated: {$path}\n";
}

echo "Key analytics nav labels OK\n";
