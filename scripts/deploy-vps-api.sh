#!/usr/bin/env bash
# Deploy Aura AI Bun API on VPS (localhost MySQL) + Apache /api proxy.
# NexTradeAI: local-only by default — set ALLOW_AURA_DEPLOY=1 to run intentionally.
set -euo pipefail
if [[ "${ALLOW_AURA_DEPLOY:-}" != "1" ]]; then
  echo "Blocked: deploy Aura API from aura-ai-app/, not NexTradeAI." >&2
  echo "To force from here: ALLOW_AURA_DEPLOY=1 $0" >&2
  exit 1
fi
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
REMOTE="${DEPLOY_REMOTE:-aura}"
APP_DIR="/home/ec2-user/aura-ai-app"
ENV_FILE="/home/ec2-user/aura-ai-api.env"
SERVICE="aura-ai-api"
DOMAIN="auraai-vps.com"
CPUSER="xghchgcjhfy"

echo "Syncing app source to ${REMOTE}:${APP_DIR} ..."
ssh "$REMOTE" "mkdir -p '$APP_DIR'"
tar czf - \
  --exclude node_modules \
  --exclude .git \
  --exclude dist \
  -C "$ROOT" . | ssh "$REMOTE" "tar xzf - -C '$APP_DIR'"

echo "Installing Bun (API-only, no web build on VPS) ..."
ssh "$REMOTE" "bash -s" <<'REMOTE_BUILD'
set -euo pipefail
APP_DIR="/home/ec2-user/aura-ai-app"
ENV_FILE="/home/ec2-user/aura-ai-api.env"
DB_PASS="auraai@2026"

# Stop runaway builds from prior failed deploys
pkill -f 'expo export' 2>/dev/null || true
pkill -f 'metro' 2>/dev/null || true

if ! command -v bun >/dev/null 2>&1; then
  curl -fsSL https://bun.sh/install | bash
fi
export PATH="$HOME/.bun/bin:$PATH"

# Preserve secrets across deploys (AI keys + Gmail relay — same pattern as EA Trade)
EXISTING_GOOGLE=""
EXISTING_GEMINI=""
EXISTING_GMAIL_USER=""
EXISTING_GMAIL_PASS=""
EXISTING_RELAY_SECRET=""
if [[ -f "$ENV_FILE" ]]; then
  EXISTING_GOOGLE="$(grep -E '^GOOGLE_AI_API_KEY=' "$ENV_FILE" | head -1 | cut -d= -f2- || true)"
  EXISTING_GEMINI="$(grep -E '^GEMINI_API_KEY=' "$ENV_FILE" | head -1 | cut -d= -f2- || true)"
  EXISTING_GMAIL_USER="$(grep -E '^GMAIL_USER=' "$ENV_FILE" | head -1 | cut -d= -f2- || true)"
  EXISTING_GMAIL_PASS="$(grep -E '^GMAIL_PASS=' "$ENV_FILE" | head -1 | cut -d= -f2- || true)"
  EXISTING_RELAY_SECRET="$(grep -E '^AURAAI_EMAIL_RELAY_SECRET=' "$ENV_FILE" | head -1 | cut -d= -f2- || true)"
fi
# Prefer app .env if systemd env file has no key yet
if [[ -z "$EXISTING_GOOGLE" && -f "$APP_DIR/.env" ]]; then
  EXISTING_GOOGLE="$(grep -E '^GOOGLE_AI_API_KEY=' "$APP_DIR/.env" | head -1 | cut -d= -f2- || true)"
fi
if [[ -z "$EXISTING_GEMINI" && -f "$APP_DIR/.env" ]]; then
  EXISTING_GEMINI="$(grep -E '^GEMINI_API_KEY=' "$APP_DIR/.env" | head -1 | cut -d= -f2- || true)"
fi
if [[ -z "$EXISTING_GMAIL_USER" && -f "$APP_DIR/.env" ]]; then
  EXISTING_GMAIL_USER="$(grep -E '^GMAIL_USER=' "$APP_DIR/.env" | head -1 | cut -d= -f2- || true)"
fi
if [[ -z "$EXISTING_GMAIL_PASS" && -f "$APP_DIR/.env" ]]; then
  EXISTING_GMAIL_PASS="$(grep -E '^GMAIL_PASS=' "$APP_DIR/.env" | head -1 | cut -d= -f2- || true)"
fi
if [[ -z "$EXISTING_RELAY_SECRET" && -f "$APP_DIR/.env" ]]; then
  EXISTING_RELAY_SECRET="$(grep -E '^AURAAI_EMAIL_RELAY_SECRET=' "$APP_DIR/.env" | head -1 | cut -d= -f2- || true)"
fi

cat > "$ENV_FILE" <<ENV
PORT=3000
NODE_ENV=production
DB_HOST=localhost
DB_USER=auraaiadmin
DB_PASSWORD=${DB_PASS}
DB_NAME=auraai
DB_PORT=3306
CORS_ALLOWED_ORIGINS=https://aura-ai-app.onrender.com,https://auraai-vps.com,https://www.auraai-vps.com,http://localhost:8081,http://localhost:3000
ENV
if [[ -n "$EXISTING_GOOGLE" ]]; then
  echo "GOOGLE_AI_API_KEY=${EXISTING_GOOGLE}" >> "$ENV_FILE"
fi
if [[ -n "$EXISTING_GEMINI" ]]; then
  echo "GEMINI_API_KEY=${EXISTING_GEMINI}" >> "$ENV_FILE"
fi
if [[ -n "$EXISTING_GMAIL_USER" ]]; then
  echo "GMAIL_USER=${EXISTING_GMAIL_USER}" >> "$ENV_FILE"
fi
if [[ -n "$EXISTING_GMAIL_PASS" ]]; then
  echo "GMAIL_PASS=${EXISTING_GMAIL_PASS}" >> "$ENV_FILE"
fi
if [[ -n "$EXISTING_RELAY_SECRET" ]]; then
  echo "AURAAI_EMAIL_RELAY_SECRET=${EXISTING_RELAY_SECRET}" >> "$ENV_FILE"
fi
if [[ -n "$EXISTING_GMAIL_USER" ]]; then
  echo "GMAIL_FROM_NAME=Aura AI VPS" >> "$ENV_FILE"
fi
chmod 600 "$ENV_FILE"

cd "$APP_DIR"
bun install --frozen-lockfile

# Minimal dist so server.ts starts; static web stays on cPanel PHP
mkdir -p dist
printf '%s\n' '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=/"></head><body></body></html>' > dist/index.html

REMOTE_BUILD

ssh "$REMOTE" "sudo bash -s" <<REMOTE
set -euo pipefail
APP_DIR="/home/ec2-user/aura-ai-app"
ENV_FILE="/home/ec2-user/aura-ai-api.env"
SERVICE="aura-ai-api"
DOMAIN="$DOMAIN"
CPUSER="$CPUSER"

cat > /etc/systemd/system/\${SERVICE}.service <<UNIT
[Unit]
Description=Aura AI API (Bun)
After=network.target mariadb.service mysql.service

[Service]
Type=simple
User=ec2-user
WorkingDirectory=\${APP_DIR}
EnvironmentFile=\${ENV_FILE}
ExecStart=/home/ec2-user/.bun/bin/bun run serve:dist
Restart=always
RestartSec=5
MemoryMax=512M

[Install]
WantedBy=multi-user.target
UNIT

systemctl daemon-reload
systemctl enable \${SERVICE}
systemctl restart \${SERVICE}

mkdir -p /etc/apache2/conf.d/userdata/ssl/2_4/\${CPUSER}/\${DOMAIN}
cat > /etc/apache2/conf.d/userdata/ssl/2_4/\${CPUSER}/\${DOMAIN}/aura-api.conf <<APACHE
# Aura AI Bun API
ProxyPreserveHost On
ProxyPass /api/ http://127.0.0.1:3000/api/
ProxyPassReverse /api/ http://127.0.0.1:3000/api/
ProxyPass /terminal/ http://127.0.0.1:3000/terminal/
ProxyPassReverse /terminal/ http://127.0.0.1:3000/terminal/
ProxyPass /health http://127.0.0.1:3000/health
ProxyPassReverse /health http://127.0.0.1:3000/health
APACHE

/usr/local/cpanel/scripts/rebuildhttpdconf
/usr/local/cpanel/scripts/restartsrv_httpd

sleep 3
systemctl is-active \${SERVICE}
curl -sf http://127.0.0.1:3000/health && echo " bun-ok"
REMOTE

echo "Testing public API ..."
curl -sf -X POST "https://${DOMAIN}/api/check-email" \
  -H "Content-Type: application/json" \
  -H "Origin: https://aura-ai-app.onrender.com" \
  -d '{"email":"test@test.com"}' | head -c 200
echo ""
echo "Done. API: https://${DOMAIN}/api/"
