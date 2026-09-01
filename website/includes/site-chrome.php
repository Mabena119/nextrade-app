<?php
/** Shared public site chrome for NexTradeAI pages. */
if (!function_exists('aura_site_head')) {
    function aura_site_head(string $title, string $description = ''): void
    {
        $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $descEsc = htmlspecialchars($description ?: 'NexTradeAI — AI powered cloud VPS built for traders.', ENT_QUOTES, 'UTF-8');
        echo <<<HTML
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
<meta name="theme-color" content="#020B18" />
<meta name="description" content="{$descEsc}" />
<title>{$titleEsc}</title>
<link rel="icon" type="image/png" href="/assets/img/sitelogo.png" />
<link rel="apple-touch-icon" href="/assets/img/sitelogo.png" />
<link rel="stylesheet" href="/assets/css/platform.css" />
HTML;
    }

    function aura_site_topbar(bool $showNav = true): void
    {
        echo '<div class="aura-atmosphere" aria-hidden="true"></div>';
        echo '<header class="aura-topbar"><div class="aura-topbar__inner">';
        echo '<a class="aura-brand" href="/"><img src="/assets/img/sitelogo.png" alt="" width="28" height="28" /><span>Nex<b>Trade</b>AI</span></a>';
        if ($showNav) {
            echo '<nav class="aura-nav" aria-label="Primary">';
            echo '<a href="/#product">Product</a>';
            echo '<a href="/#download">Download</a>';
            echo '<a href="/#setup">Install</a>';
            echo '</nav>';
        }
        echo '</div></header>';
    }

    function aura_site_footer(): void
    {
        $year = date('Y');
        echo <<<HTML
<footer class="aura-footer">
  <div class="aura-footer__inner">
    <p>© {$year} NexTradeAI · AI Powered · Cloud VPS · Built for Traders</p>
    <nav>
      <a href="/privacy-policy.html">Privacy</a>
      <a href="/terms-of-service.html">Terms</a>
    </nav>
  </div>
</footer>
HTML;
    }
}
