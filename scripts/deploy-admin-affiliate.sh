#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
REMOTE="${1:-aura}"
BASE="public_html"

ssh "$REMOTE" "mkdir -p $BASE/admin/home/tools"

scp "$ROOT/website/includes/affiliate.php" "$REMOTE:$BASE/includes/affiliate.php"
scp "$ROOT/website/includes/auraai-emails.php" "$REMOTE:$BASE/includes/auraai-emails.php"
scp "$ROOT/website/includes/email-config.php" "$REMOTE:$BASE/includes/email-config.php"
scp "$ROOT/website/admin/home/affiliate.php" "$REMOTE:$BASE/admin/home/affiliate.php"
scp "$ROOT/website/includes/password-reset.php" "$REMOTE:$BASE/includes/password-reset.php"
scp "$ROOT/website/admin/home/affiliates.php" "$REMOTE:$BASE/admin/home/affiliates.php"
scp "$ROOT/website/admin/home/affiliate-detail.php" "$REMOTE:$BASE/admin/home/affiliate-detail.php"
scp "$ROOT/website/admin/home/affiliate-status-update.php" "$REMOTE:$BASE/admin/home/affiliate-status-update.php"
scp "$ROOT/website/admin/home/affiliate-withdrawal-update.php" "$REMOTE:$BASE/admin/home/affiliate-withdrawal-update.php"
scp "$ROOT/website/admin/home/tools/ensure-affiliate-nav.php" "$REMOTE:$BASE/admin/home/tools/ensure-affiliate-nav.php"
scp "$ROOT/website/affiliate/include/nav.php" "$REMOTE:$BASE/affiliate/include/nav.php"
scp "$ROOT/website/affiliate/logout.php" "$REMOTE:$BASE/affiliate/logout.php"

ssh "$REMOTE" "php $BASE/admin/home/tools/ensure-affiliate-nav.php"

ssh "$REMOTE" 'php -r "
require \"public_html/admin/php-includes/connect.php\";
require \"public_html/includes/affiliate.php\";
auraai_affiliate_ensure_tables(\$con);
echo \"affiliate admin integration OK\n\";
"'

echo "Affiliate admin integration deployed."
