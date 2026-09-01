#!/usr/bin/env bash
# Start full NexTradeAI local stack (website + API + Expo web). No Aura VPS deploys.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

port_in_use() {
  lsof -i ":$1" -sTCP:LISTEN >/dev/null 2>&1
}

echo "NexTradeAI — local only"
echo ""

if port_in_use 8080; then
  echo "✓ Website already on http://localhost:8080"
else
  echo "→ Starting website on http://localhost:8080"
  python3 scripts/serve-website.py &
  sleep 1
fi

if port_in_use 3000; then
  echo "✓ API already on http://localhost:3000"
else
  echo "→ Starting API on http://localhost:3000 (proxies auth to auraai-vps.com)"
  API_UPSTREAM_URL="${API_UPSTREAM_URL:-https://auraai-vps.com}" bun server.ts &
  sleep 2
fi

if port_in_use 8081; then
  echo "✓ App already on http://localhost:8081"
  echo ""
  echo "Open http://localhost:8081"
  exit 0
fi

echo "→ Starting Expo web on http://localhost:8081"
echo ""
exec bunx expo start --web --port 8081
