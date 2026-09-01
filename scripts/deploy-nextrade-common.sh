#!/usr/bin/env bash
# NexTradeAI Lightsail (nextradeai.io) — cPanel paths via ec2-user + sudo.
REMOTE="${DEPLOY_REMOTE:-nextrade}"
WEBROOT="/home/xghchgcjhfy/public_html"
CPANEL_USER="xghchgcjhfy"
SECRETS="/home/${CPANEL_USER}/nextradeai-secrets.php"

CPANEL_DB_HOST="localhost"
CPANEL_DB_USER="xghchgcjhfy_nextradeai"
CPANEL_DB_NAME="xghchgcjhfy_nextradeai"
CPANEL_DB_PASS="${NEXTRADE_DB_PASS:-NexTrade@2026}"

deploy_scp() {
  local src="$1"
  local dest="$2"
  local base
  base="$(basename "$src")"
  scp "$src" "${REMOTE}:/tmp/_nextrade_deploy_${base}"
  ssh "$REMOTE" "sudo mkdir -p $(dirname "$dest") && sudo mv /tmp/_nextrade_deploy_${base} '$dest' && sudo chown ${CPANEL_USER}:${CPANEL_USER} '$dest'"
}
