#!/usr/bin/env bash
# Deploy NexTradeAI marketing site + PHP to nextradeai.io (Lightsail).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck source=deploy-nextrade-common.sh
source "$ROOT/scripts/deploy-nextrade-common.sh"

echo "Syncing website/ → ${REMOTE}:${WEBROOT} ..."
tar czf - -C "$ROOT/website" . | ssh "$REMOTE" "sudo tar xzf - -C ${WEBROOT} && sudo chown -R ${CPANEL_USER}:${CPANEL_USER} ${WEBROOT}"

echo "Done. Site: https://nextradeai.io/"
