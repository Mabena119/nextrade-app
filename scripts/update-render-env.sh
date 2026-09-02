#!/usr/bin/env bash
# Set Gmail + relay env vars on Render and trigger redeploy.
# Requires: RENDER_API_KEY from https://dashboard.render.com/u/settings#api-keys
set -euo pipefail

: "${RENDER_API_KEY:?Set RENDER_API_KEY}"
: "${GMAIL_USER:?Set GMAIL_USER}"
: "${GMAIL_PASS:?Set GMAIL_PASS}"
: "${AURAAI_EMAIL_RELAY_SECRET:?Set AURAAI_EMAIL_RELAY_SECRET}"

SERVICE_NAME="${RENDER_SERVICE_NAME:-nextrade-app}"
API="https://api.render.com/v1"

auth() { curl -fsS -H "Authorization: Bearer ${RENDER_API_KEY}" -H "Content-Type: application/json" "$@"; }

echo "Looking up Render service: ${SERVICE_NAME}..."
SERVICE_ID=$(auth "${API}/services?limit=100" | python3 -c "
import json,sys
data=json.load(sys.stdin)
for item in data:
    s=item.get('service') or item
    name=s.get('name','')
    if name=='${SERVICE_NAME}':
        print(s['id'])
        break
")

if [[ -z "${SERVICE_ID}" ]]; then
  echo "Service not found: ${SERVICE_NAME}"
  exit 1
fi

echo "Service ID: ${SERVICE_ID}"

upsert_env() {
  local key="$1" val="$2"
  auth -X PUT "${API}/services/${SERVICE_ID}/env-vars/${key}" -d "$(python3 -c "import json; print(json.dumps({'value': '''${val}'''}))")" >/dev/null
  echo "Set ${key}"
}

upsert_env "GMAIL_USER" "${GMAIL_USER}"
upsert_env "GMAIL_PASS" "${GMAIL_PASS}"
upsert_env "GMAIL_FROM_NAME" "${GMAIL_FROM_NAME:-NexTradeAI}"
upsert_env "AURAAI_EMAIL_RELAY_SECRET" "${AURAAI_EMAIL_RELAY_SECRET}"
upsert_env "NEXTTRADEAI_EMAIL_RELAY_SECRET" "${AURAAI_EMAIL_RELAY_SECRET}"

# Render web: relay /api/* to VPS (MySQL is localhost-only on VPS; port 3306 not open).
API_UPSTREAM="${API_UPSTREAM_URL:-https://nextradeai.io}"
upsert_env "API_UPSTREAM_URL" "${API_UPSTREAM}"

echo "Triggering deploy..."
auth -X POST "${API}/services/${SERVICE_ID}/deploys" -d '{"clearCache":"do_not_clear"}' >/dev/null
echo "Render deploy triggered."
