<?php
/**
 * NexTradeAI — canonical public URLs (single source of truth for PHP site).
 */
if (!defined('NEXTRADE_SITE_URL')) {
    define('NEXTRADE_SITE_URL', 'https://nextradeai.io');
}
if (!defined('NEXTRADE_APP_URL')) {
    define('NEXTRADE_APP_URL', 'https://nextrade-app-uklj.onrender.com');
}
if (!defined('NEXTRADE_APK_URL')) {
    define('NEXTRADE_APK_URL', NEXTRADE_SITE_URL . '/admin/downloads/nextradeai.apk');
}
if (!defined('NEXTRADE_SHOP_URL')) {
    define('NEXTRADE_SHOP_URL', NEXTRADE_SITE_URL . '/shop/');
}
if (!defined('NEXTRADE_ADMIN_URL')) {
    define('NEXTRADE_ADMIN_URL', NEXTRADE_SITE_URL . '/admin/');
}
if (!defined('NEXTRADE_AFFILIATE_URL')) {
    define('NEXTRADE_AFFILIATE_URL', NEXTRADE_SITE_URL . '/affiliate/');
}
if (!defined('NEXTRADE_SUPPORT_EMAIL')) {
    define('NEXTRADE_SUPPORT_EMAIL', 'auraaiio@gmail.com');
}
if (!defined('NEXTRADE_SHOP_PRICE_ZAR')) {
    define('NEXTRADE_SHOP_PRICE_ZAR', 499);
}

/** Legacy aliases used by email + affiliate helpers. */
if (!defined('AURAAI_SHOP_URL')) {
    define('AURAAI_SHOP_URL', NEXTRADE_SHOP_URL);
}
if (!defined('AURAAI_APP_URL')) {
    define('AURAAI_APP_URL', NEXTRADE_APP_URL);
}
if (!defined('AURAAI_ADMIN_LOGIN')) {
    define('AURAAI_ADMIN_LOGIN', NEXTRADE_ADMIN_URL);
}
if (!defined('AURAAI_AFFILIATE_URL')) {
    define('AURAAI_AFFILIATE_URL', NEXTRADE_AFFILIATE_URL);
}
