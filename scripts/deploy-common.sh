#!/usr/bin/env bash
# Shared paths for Aura AI VPS (SSH host: aura → ec2-user, site owned by cPanel user).
# NexTradeAI: blocked unless ALLOW_AURA_DEPLOY=1 (local dev only in this repo).
if [[ "${ALLOW_AURA_DEPLOY:-}" != "1" ]]; then
  echo "Blocked: Aura VPS deploy from NexTradeAI (local-only workspace)." >&2
  echo "Use aura-ai-app/ for production deploys. To force: ALLOW_AURA_DEPLOY=1 $0" >&2
  exit 1
fi

REMOTE="${DEPLOY_REMOTE:-aura}"
WEBROOT="/home/xghchgcjhfy/public_html"
CPANEL_USER="xghchgcjhfy"

# cPanel MySQL (registered via uapi — clean names like EA Trade: auraai / auraaiadmin)
CPANEL_DB_HOST="localhost"
CPANEL_DB_USER="auraaiadmin"
CPANEL_DB_NAME="auraai"

deploy_scp() {
  local src="$1"
  local dest="$2"
  local base
  base="$(basename "$src")"
  scp "$src" "${REMOTE}:/tmp/_aura_deploy_${base}"
  ssh "$REMOTE" "sudo mkdir -p $(dirname "$dest") && sudo mv /tmp/_aura_deploy_${base} '$dest' && sudo chown ${CPANEL_USER}:${CPANEL_USER} '$dest'"
}

deploy_scp_dir() {
  local src="$1"
  local dest="$2"
  local tmp="/tmp/_aura_deploy_dir_$$"
  ssh "$REMOTE" "rm -rf '$tmp' && mkdir -p '$tmp'"
  scp -r "$src" "${REMOTE}:${tmp}/"
  ssh "$REMOTE" "sudo mkdir -p '$dest' && sudo cp -a ${tmp}/. '$dest/' && sudo chown -R ${CPANEL_USER}:${CPANEL_USER} '$dest' && rm -rf '$tmp'"
}
