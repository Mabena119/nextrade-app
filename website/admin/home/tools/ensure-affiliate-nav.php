<?php
/**
 * Idempotent sidebar patch for affiliate nav links. Run on deploy via SSH.
 * Usage: php public_html/admin/home/tools/ensure-affiliate-nav.php
 */
$headerFile = dirname(__DIR__) . '/include/header.php';
if (!is_readable($headerFile)) {
    fwrite(STDERR, "Header not found: $headerFile\n");
    exit(1);
}

$html = file_get_contents($headerFile);
$original = $html;

if (strpos($html, 'href="affiliate.php"') === false) {
    $search = "<span class=\"menu-title\">Expert Advisors</span>\n            </a>\n          </li>";
    $replace = "<span class=\"menu-title\">Expert Advisors</span>\n            </a>\n          </li>\n          <li class=\"nav-item\">\n            <a class=\"nav-link <?php echo admin_nav_active('affiliate.php'); ?>\" href=\"affiliate.php\">\n              <i class=\"ti ti-share menu-icon\"></i>\n              <span class=\"menu-title\">Affiliate Program</span>\n            </a>\n          </li>";
    if (strpos($html, $search) === false) {
        fwrite(STDERR, "Could not find Expert Advisors nav block.\n");
        exit(1);
    }
    $html = str_replace($search, $replace, $html);
    echo "Added mentor Affiliate Program nav link.\n";
}

if (strpos($html, 'href="affiliates.php"') === false) {
    $search = "<a class=\"nav-link <?php echo admin_nav_active('users.php'); ?>\" href=\"users.php\">\n              <i class=\"ti ti-users menu-icon\"></i>\n              <span class=\"menu-title\">Manage Users</span>\n            </a>\n          </li>\n            <?php }?>";
    $replace = "<a class=\"nav-link <?php echo admin_nav_active('users.php'); ?>\" href=\"users.php\">\n              <i class=\"ti ti-users menu-icon\"></i>\n              <span class=\"menu-title\">Manage Users</span>\n            </a>\n          </li>\n          <li class=\"nav-item\">\n            <a class=\"nav-link <?php echo admin_nav_active('affiliates.php'); ?>\" href=\"affiliates.php\">\n              <i class=\"ti ti-coin menu-icon\"></i>\n              <span class=\"menu-title\">Affiliates</span>\n            </a>\n          </li>\n            <?php }?>";
    if (strpos($html, $search) === false) {
        fwrite(STDERR, "Could not find super admin Manage Users nav block.\n");
        exit(1);
    }
    $html = str_replace($search, $replace, $html);
    echo "Added super admin Affiliates nav link.\n";
}

if ($html === $original) {
    echo "Nav links already up to date.\n";
} else {
    file_put_contents($headerFile, $html);
    echo "Header updated.\n";
}
