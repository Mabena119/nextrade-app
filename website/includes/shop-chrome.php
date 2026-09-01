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
        echo '<meta name="theme-color" content="#020B18" />' . "\n";
        echo '<meta name="description" content="Get NexTradeAI cloud VPS access for R' . $price . ' once. Instant membership — run MetaTrader automations from your phone 24/7." />' . "\n";
        echo '<link rel="canonical" href="' . $canonical . '" />' . "\n";
        echo '<title>' . $titleEsc . '</title>' . "\n";
        echo '<link rel="icon" type="image/png" href="/assets/img/sitelogo.png" />' . "\n";
        echo '<link rel="apple-touch-icon" href="/assets/img/sitelogo.png" />' . "\n";
        echo '<link rel="stylesheet" href="/assets/css/platform.css" />' . "\n";
        echo '<link rel="stylesheet" href="/assets/css/shop-nextrade.css" />' . "\n";
    }

    function nextrade_shop_topbar(): void
    {
        $price = (int) NEXTRADE_SHOP_PRICE_ZAR;
        echo '<header class="shop-topbar"><div class="shop-topbar__inner">';
        echo '<a class="shop-brand" href="/"><img src="/assets/img/sitelogo.png" alt="" width="32" height="32" /><span>Nex<b>Trade</b>AI</span></a>';
        echo '<nav class="shop-nav" aria-label="Shop navigation">';
        echo '<a href="/">Home</a>';
        echo '<a href="/#download">Download</a>';
        echo '<a href="/how-to-install/">Install</a>';
        echo '<a href="/shop/" aria-current="page">Access · R' . $price . '</a>';
        echo '<a href="/admin/">Mentor login</a>';
        echo '<a href="/affiliate/">Affiliates</a>';
        echo '</nav>';
        echo '</div></header>';
    }

    function nextrade_shop_footer(): void
    {
        $year = date('Y');
        $app = htmlspecialchars(NEXTRADE_APP_URL, ENT_QUOTES, 'UTF-8');
        echo <<<HTML
<footer class="shop-footer">
  <div class="shop-footer__inner">
    <p>© {$year} NexTradeAI</p>
    <nav>
      <a href="{$app}" target="_blank" rel="noopener noreferrer">Open app</a>
      <a href="/admin/">Mentor login</a>
      <a href="/privacy-policy.html">Privacy</a>
      <a href="/terms-of-service.html">Terms</a>
    </nav>
  </div>
</footer>
HTML;
    }
}
