#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
REMOTE="aura"
BASE="public_html"

ssh "$REMOTE" "mkdir -p $BASE/affiliate/include"
scp "$ROOT/website/includes/affiliate.php" "$REMOTE:$BASE/includes/affiliate.php"
scp "$ROOT/website/includes/auraai-emails.php" "$REMOTE:$BASE/includes/auraai-emails.php"
scp "$ROOT/website/includes/email-config.php" "$REMOTE:$BASE/includes/email-config.php"
scp "$ROOT/website/affiliate/index.php" "$REMOTE:$BASE/affiliate/index.php"
scp "$ROOT/website/affiliate/signup.php" "$REMOTE:$BASE/affiliate/signup.php"
scp "$ROOT/website/affiliate/register.php" "$REMOTE:$BASE/affiliate/register.php"
scp "$ROOT/website/affiliate/login.php" "$REMOTE:$BASE/affiliate/login.php"
scp "$ROOT/website/affiliate/logout.php" "$REMOTE:$BASE/affiliate/logout.php"
scp "$ROOT/website/affiliate/dashboard.php" "$REMOTE:$BASE/affiliate/dashboard.php"
scp "$ROOT/website/affiliate/payout-method.php" "$REMOTE:$BASE/affiliate/payout-method.php"
scp "$ROOT/website/affiliate/payout-method-delete.php" "$REMOTE:$BASE/affiliate/payout-method-delete.php"
scp "$ROOT/website/affiliate/request-withdrawal.php" "$REMOTE:$BASE/affiliate/request-withdrawal.php"
scp "$ROOT/website/affiliate/include/styles.php" "$REMOTE:$BASE/affiliate/include/styles.php"
scp "$ROOT/website/affiliate/include/nav.php" "$REMOTE:$BASE/affiliate/include/nav.php"
scp "$ROOT/website/index.php" "$REMOTE:$BASE/index.php"
scp "$ROOT/website/index.html" "$REMOTE:$BASE/index.html"
scp "$ROOT/website/shop/index2.php" "$REMOTE:$BASE/shop/index2.php"
scp "$ROOT/website/shop/track-ref.php" "$REMOTE:$BASE/shop/track-ref.php"
scp "$ROOT/website/shop/attribution-ping.php" "$REMOTE:$BASE/shop/attribution-ping.php"
scp "$ROOT/website/shop/payment-attribution.php" "$REMOTE:$BASE/shop/payment-attribution.php"
scp "$ROOT/website/shop/webhook1.php" "$REMOTE:$BASE/shop/webhook1.php"
scp "$ROOT/website/shop/notifyb.php" "$REMOTE:$BASE/shop/notifyb.php"

ssh "$REMOTE" 'php -r "
require \"public_html/admin/php-includes/connect.php\";
require \"public_html/includes/affiliate.php\";
auraai_affiliate_ensure_tables(\$con);
echo \"affiliate tables OK\n\";
"'

echo "Affiliate system deployed."
