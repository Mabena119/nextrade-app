<?php
/**
 * Shared external links for NexTradeAI mentor console pages.
 */
require_once __DIR__ . '/site-config.php';

if (!function_exists('nextrade_admin_external_nav')) {
    function nextrade_admin_external_nav(): void
    {
        $app = htmlspecialchars(NEXTRADE_APP_URL, ENT_QUOTES, 'UTF-8');
        $price = (int) NEXTRADE_SHOP_PRICE_ZAR;
        echo '<li class="aura-console-nav-divider" aria-hidden="true"></li>';
        echo '<li class="aura-console-nav-label">Site</li>';
        echo '<li><a href="/" target="_blank" rel="noopener noreferrer"><i class="ti ti-home"></i> Marketing site</a></li>';
        echo '<li><a href="/shop/" target="_blank" rel="noopener noreferrer"><i class="ti ti-shopping-cart"></i> Shop · R' . $price . '</a></li>';
        echo '<li><a href="' . $app . '" target="_blank" rel="noopener noreferrer"><i class="ti ti-device-mobile"></i> Open app</a></li>';
        echo '<li><a href="/affiliate/" target="_blank" rel="noopener noreferrer"><i class="ti ti-share"></i> Affiliates</a></li>';
        echo '<li><a href="/how-to-install/" target="_blank" rel="noopener noreferrer"><i class="ti ti-book"></i> Install guide</a></li>';
    }
}

if (!function_exists('nextrade_admin_auth_footer')) {
    function nextrade_admin_auth_footer(): void
    {
        $app = htmlspecialchars(NEXTRADE_APP_URL, ENT_QUOTES, 'UTF-8');
        $price = (int) NEXTRADE_SHOP_PRICE_ZAR;
        echo '<div class="aura-auth-site-links">';
        echo '<a href="/">Home</a>';
        echo '<a href="/shop/">Access · R' . $price . '</a>';
        echo '<a href="' . $app . '" target="_blank" rel="noopener noreferrer">App</a>';
        echo '<a href="/affiliate/">Affiliates</a>';
        echo '</div>';
    }
}
