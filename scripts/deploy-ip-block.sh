#!/usr/bin/env bash
# Deploy IP block list (super admin + site guard) to auraai-vps.com production.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
REMOTE="${1:-aura}"
BASE="public_html"

ssh "$REMOTE" "mkdir -p $BASE/admin/home/include $BASE/admin/home/tools $BASE/includes"

scp "$ROOT/website/includes/security.php" "$REMOTE:$BASE/includes/security.php"
scp "$ROOT/website/includes/ip-block.php" "$REMOTE:$BASE/includes/ip-block.php"
scp "$ROOT/website/includes/ip-block-guard.php" "$REMOTE:$BASE/includes/ip-block-guard.php"
scp "$ROOT/website/admin/home/include/require_super.php" "$REMOTE:$BASE/admin/home/include/require_super.php"
scp "$ROOT/website/admin/home/blocked-ips.php" "$REMOTE:$BASE/admin/home/blocked-ips.php"
scp "$ROOT/website/admin/home/blocked-ip-update.php" "$REMOTE:$BASE/admin/home/blocked-ip-update.php"
scp "$ROOT/website/admin/home/tools/ensure-blocked-ips-nav.php" "$REMOTE:$BASE/admin/home/tools/ensure-blocked-ips-nav.php"
scp "$ROOT/website/index.php" "$REMOTE:$BASE/index.php"
scp "$ROOT/website/shop/index2.php" "$REMOTE:$BASE/shop/index2.php"

ssh "$REMOTE" "php $BASE/admin/home/tools/ensure-blocked-ips-nav.php"

ssh "$REMOTE" 'php -r "
require \"public_html/admin/php-includes/connect.php\";
require \"public_html/includes/ip-block.php\";
auraai_ip_block_ensure_table(\$con);
auraai_ip_block_refresh_cache(\$con);
echo \"blocked_ips table OK\n\";
"'

echo ""
echo "IP block system deployed."
echo "Super admin: https://auraai-vps.com/admin/home/blocked-ips.php"
