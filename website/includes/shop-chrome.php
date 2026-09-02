<?php
/**
 * Shared chrome for NexTradeAI shop / checkout pages.
 */
require_once __DIR__ . '/site-config.php';

if (!function_exists('nextrade_shop_head')) {
    function nextrade_shop_head(string $title = 'Access · NexTradeAI'): void
    {
        $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $price = (int) NEXTRADE_SHOP_PRICE_ZAR;
        $canonical = htmlspecialchars(NEXTRADE_SHOP_URL, ENT_QUOTES, 'UTF-8');
        echo '<meta charset="UTF-8" />' . "\n";
        echo '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />' . "\n";
        echo '<meta name="theme-color" content="#000000" />' . "\n";
        echo '<meta name="description" content="Get NexTradeAI cloud VPS access for R' . $price . ' once. Instant membership — run MetaTrader automations from your phone 24/7." />' . "\n";
        echo '<link rel="canonical" href="' . $canonical . '" />' . "\n";
        echo '<title>' . $titleEsc . '</title>' . "\n";
        echo '<link rel="icon" type="image/png" href="/assets/img/sitelogo.png" />' . "\n";
        echo '<link rel="apple-touch-icon" href="/assets/img/sitelogo.png" />' . "\n";
        echo '<link rel="stylesheet" href="/assets/css/shop-nextrade.css" />' . "\n";
    }

    function nextrade_shop_topbar(): void
    {
        echo '<header class="shop-topbar" id="shop-topbar"><div class="shop-topbar__inner">';
        echo '<a class="shop-brand" href="/"><img src="/assets/img/sitelogo.png" alt="" width="36" height="36" /><span>Nex<b>Trade</b>AI</span></a>';
        echo '<nav class="shop-nav" aria-label="Shop navigation">';
        echo '<a href="/#product">Product</a>';
        echo '<a href="/#flow">How it works</a>';
        echo '<a href="/#download">Download</a>';
        echo '<a href="/#setup">Install</a>';
        echo '</nav>';
        echo '</div></header>';
    }

    function nextrade_shop_footer(): void
    {
        $year = date('Y');
        echo <<<HTML
<footer class="shop-footer">
  <div class="shop-footer__inner">
    <p>© {$year} NexTradeAI</p>
    <nav>
      <a href="/privacy-policy.html">Privacy</a>
      <a href="/terms-of-service.html">Terms</a>
    </nav>
  </div>
</footer>
HTML;
    }
}
