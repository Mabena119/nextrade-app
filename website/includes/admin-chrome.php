<?php
/**
 * Shared external links for NexTradeAI mentor console pages.
 */
require_once __DIR__ . '/site-config.php';

if (!function_exists('nextrade_logo_url')) {
    function nextrade_logo_url(): string
    {
        return NEXTRADE_LOGO_URL;
    }
}

if (!function_exists('nextrade_admin_auth_logo')) {
    function nextrade_admin_auth_logo(string $subtitle = 'Mentor console'): void
    {
        $logo = htmlspecialchars(nextrade_logo_url(), ENT_QUOTES, 'UTF-8');
        $sub = htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8');
        echo '<div class="aura-auth-logo">';
        echo '<img src="' . $logo . '" alt="NexTradeAI" width="56" height="56" />';
        echo '<h1>Nex<span style="color:var(--aura-cyan)">Trade</span>AI</h1>';
        echo '<p>' . $sub . '</p>';
        echo '</div>';
    }
}

if (!function_exists('nextrade_admin_auth_footer')) {
    function nextrade_admin_auth_footer(): void
    {
        echo '<div class="aura-auth-site-links">';
        echo '<a href="/">Home</a>';
        echo '<a href="/privacy-policy.html">Privacy</a>';
        echo '<a href="/terms-of-service.html">Terms</a>';
        echo '</div>';
    }
}
