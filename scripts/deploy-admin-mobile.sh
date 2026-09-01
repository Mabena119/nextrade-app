#!/usr/bin/env bash
# Deploy Key analytics (stats.php) mobile scroll + row actions to auraai-vps.com production.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
REMOTE="${1:-aura}"
BASE="public_html"

ssh "$REMOTE" "mkdir -p $BASE/admin/home/include $BASE/admin/home/tools"

scp "$ROOT/website/admin/home/stats.php" \
  "$REMOTE:$BASE/admin/home/stats.php"
scp "$ROOT/website/admin/home/include/key-analytics-mobile.php" \
  "$REMOTE:$BASE/admin/home/include/key-analytics-mobile.php"
scp "$ROOT/website/admin/home/tools/ensure-key-analytics-nav.php" \
  "$REMOTE:$BASE/admin/home/tools/ensure-key-analytics-nav.php"

ssh "$REMOTE" "php $BASE/admin/home/tools/ensure-key-analytics-nav.php"

echo ""
echo "Key analytics deployed (stats.php + mobile scroll + reactivate/deactivate/delete)."
echo "Verify: https://auraai-vps.com/admin/home/stats.php"
