<?php
/**
 * Whop webhook config — copy to whop_config.php on the server (not committed).
 *
 * Dashboard: https://whop.com/dashboard → Developer → Webhooks
 * Endpoint:  https://www.nextradeai.io/shop/notifyb.php
 * Events:    payment.succeeded, membership.activated
 * API ver:   v1
 *
 * After creating the webhook, paste the signing secret below.
 */
define('WHOP_COMPANY_ID', 'biz_1dqEi47Dd9V33m');
define('WHOP_PRODUCT_VPS_ID', 'prod_6NsYylkl5Nfwr');
define('WHOP_PRODUCT_VPS_TITLE', 'NexTradeAI');
// define('WHOP_PRODUCT_SCANNER_ID', 'prod_2ZlqLG9vBe3tF');
// define('WHOP_PRODUCT_SCANNER_TITLE', 'NexTradeAI Scanner');
define('WHOP_WEBHOOK_SECRET', 'whsec_YOUR_SECRET_HERE');
define('WHOP_ALLOW_UNVERIFIED_FOR_TESTING', true);
define('WHOP_DEFAULT_MENTOR_ID', 1);
