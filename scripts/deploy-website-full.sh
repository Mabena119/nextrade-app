#!/usr/bin/env bash
# Full website sync to auraai-vps.com (aura SSH host).
# NexTradeAI: local-only by default — set ALLOW_AURA_DEPLOY=1 to run intentionally.
set -euo pipefail
if [[ "${ALLOW_AURA_DEPLOY:-}" != "1" ]]; then
  echo "Blocked: NexTradeAI website/ is for local dev (python3 scripts/serve-website.py)." >&2
  echo "Live Aura AI site is deployed from aura-ai-app/. To force: ALLOW_AURA_DEPLOY=1 $0" >&2
  exit 1
fi
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck source=deploy-common.sh
source "$ROOT/scripts/deploy-common.sh"

echo "Syncing website/ → ${REMOTE}:${WEBROOT} ..."
tar czf - -C "$ROOT/website" . | ssh "$REMOTE" "sudo tar xzf - -C ${WEBROOT} && sudo chown -R ${CPANEL_USER}:${CPANEL_USER} ${WEBROOT}"

EX5="/Users/justvino__/Library/Application Support/net.metaquotes.wine.metatrader5/drive_c/Program Files/MetaTrader 5/MQL5/Experts/Advisors/AURAAI/AURAAI.ex5"
if [[ ! -f "$EX5" ]]; then
  EX5="$ROOT/mt5/AURAAI.ex5"
fi
if [[ -f "$EX5" ]]; then
  echo "Uploading AURAAI.ex5 ..."
  deploy_scp "$EX5" "${WEBROOT}/admin/downloads/AURAAI.ex5"
fi

echo "Done. Site: https://auraai-vps.com/"
echo "DB: run ./scripts/setup-cpanel-db.sh if connect.php or secrets changed."
