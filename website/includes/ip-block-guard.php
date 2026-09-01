<?php
/**
 * Early IP block check — use as auto_prepend_file or require at top of public entry scripts.
 */
if (!function_exists('auraai_sec_client_ip')) {
    require_once __DIR__ . '/security.php';
}
require_once __DIR__ . '/ip-block.php';
auraai_sec_ip_block_check_or_exit();
