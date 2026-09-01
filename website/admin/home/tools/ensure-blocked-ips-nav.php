<?php
/**
 * Idempotent sidebar patch — Blocked IPs link for super admin.
 */
$headerFile = dirname(__DIR__) . '/include/header.php';
if (!is_readable($headerFile)) {
    fwrite(STDERR, "Header not found: $headerFile\n");
    exit(1);
}

$html = file_get_contents($headerFile);
$original = $html;

if (strpos($html, 'href="blocked-ips.php"') === false) {
    $search = '<a class="nav-link <?php echo admin_nav_active(\'affiliates.php\'); ?>" href="affiliates.php">
              <i class="ti ti-coin menu-icon"></i>
              <span class="menu-title">Affiliates</span>
            </a>
          </li>';
    $replace = '<a class="nav-link <?php echo admin_nav_active(\'affiliates.php\'); ?>" href="affiliates.php">
              <i class="ti ti-coin menu-icon"></i>
              <span class="menu-title">Affiliates</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?php echo admin_nav_active(\'blocked-ips.php\'); ?>" href="blocked-ips.php">
              <i class="ti ti-ban menu-icon"></i>
              <span class="menu-title">Blocked IPs</span>
            </a>
          </li>';
    if (strpos($html, $search) === false) {
        fwrite(STDERR, "Could not find Affiliates nav block.\n");
        exit(1);
    }
    $html = str_replace($search, $replace, $html);
    echo "Added super admin Blocked IPs nav link.\n";
}

if ($html === $original) {
    echo "Blocked IPs nav already up to date.\n";
} else {
    file_put_contents($headerFile, $html);
    echo "Header updated.\n";
}
