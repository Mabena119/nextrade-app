<?php
/**
 * Shared marketing nav — Product · How it works · Tutorial · Download · Install
 */
require_once __DIR__ . '/site-config.php';

if (!function_exists('nextrade_public_nav_items')) {
    /** @return list<array{key: string, href: string, label: string}> */
    function nextrade_public_nav_items(): array
    {
        return [
            ['key' => 'product', 'href' => '/#product', 'label' => 'Product'],
            ['key' => 'tutorial', 'href' => '/#tutorial', 'label' => 'Tutorial'],
            ['key' => 'flow', 'href' => '/#flow', 'label' => 'How it works'],
            ['key' => 'download', 'href' => '/#download', 'label' => 'Download'],
            ['key' => 'install', 'href' => '/how-to-install/', 'label' => 'Install'],
        ];
    }

    function nextrade_public_nav_is_current(string $key, string $currentPath): bool
    {
        $path = strtolower(trim($currentPath));
        if ($path === '') {
            $path = strtolower(parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
        }
        if ($key === 'install') {
            return str_starts_with($path, '/how-to-install');
        }
        return false;
    }

    /** Dot-separated list nav (homepage, how-to-install, static pages). */
    function nextrade_public_nav_list(string $currentPath = ''): void
    {
        echo '<ul class="nav-links" aria-label="Primary">';
        foreach (nextrade_public_nav_items() as $item) {
            $href = htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8');
            $label = htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8');
            $current = nextrade_public_nav_is_current($item['key'], $currentPath) ? ' aria-current="page"' : '';
            echo "<li><a href=\"{$href}\"{$current}>{$label}</a></li>";
        }
        echo '</ul>';
    }

    /** Inline nav for shop / platform.css pages. */
    function nextrade_public_nav_inline(string $linkClass = '', string $currentPath = ''): void
    {
        $classAttr = $linkClass !== '' ? ' class="' . htmlspecialchars($linkClass, ENT_QUOTES, 'UTF-8') . '"' : '';
        echo '<nav class="aura-nav" aria-label="Primary">';
        foreach (nextrade_public_nav_items() as $item) {
            $href = htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8');
            $label = htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8');
            $current = nextrade_public_nav_is_current($item['key'], $currentPath) ? ' aria-current="page"' : '';
            echo "<a href=\"{$href}\"{$classAttr}{$current}>{$label}</a>";
        }
        echo '</nav>';
    }

    function nextrade_public_topbar(string $currentPath = ''): void
    {
        $logo = htmlspecialchars(NEXTRADE_LOGO_URL, ENT_QUOTES, 'UTF-8');
        echo '<header class="topbar" id="topbar"><div class="topbar__inner">';
        echo '<a class="brand" href="/"><img src="' . $logo . '" alt="" width="36" height="36" /><span>Nex<b>Trade</b>AI</span></a>';
        nextrade_public_nav_list($currentPath);
        echo '</div></header>';
    }
}
