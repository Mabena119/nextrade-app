#!/usr/bin/env bash
# Smoke-test NexTrade signal APIs (publish → poll → active lookup).
set -euo pipefail

API_BASE="${NEXTRADE_API_BASE:-https://www.nextradeai.io/api}"
EA_CODE="${NEXTRADE_EA_CODE:-}"
EA_ID="${NEXTRADE_TEST_EA_ID:-}"

pass() { echo "✓ $1"; }
fail() { echo "✗ $1" >&2; exit 1; }

echo "API base: $API_BASE"

auth_bad=$(curl -sS "${API_BASE}/ea-auth?key=__invalid__")
echo "$auth_bad" | grep -q 'invalid_ea_key' && pass "ea-auth rejects bad key" || fail "ea-auth bad key"

active=$(curl -sS "${API_BASE}/get-active-signal?eaId=1")
echo "$active" | grep -q '"signal"' && pass "get-active-signal JSON shape" || fail "get-active-signal"

new=$(curl -sS "${API_BASE}/get-new-signals?eaId=1&since=2020-01-01T00:00:00.000Z")
echo "$new" | grep -q '"signals"' && pass "get-new-signals JSON shape" || fail "get-new-signals"

post_bad=$(curl -sS -o /tmp/nx-post.json -w "%{http_code}" -X POST "${API_BASE}/post-signal" \
  -H "Content-Type: application/json" \
  -d '{"ea_secret":"bad","signal":{"asset":"XAUUSD","action":"buy","price":"0","tp":"0","sl":"0"}}')
[ "$post_bad" = "403" ] && pass "post-signal rejects bad secret (403)" || fail "post-signal bad secret got $post_bad"

if [ -z "$EA_CODE" ] || [ -z "$EA_ID" ]; then
  echo ""
  echo "Optional live round-trip skipped (set NEXTRADE_EA_CODE + NEXTRADE_TEST_EA_ID)."
  echo "All structural signal API checks passed."
  exit 0
fi

echo ""
echo "Live round-trip with EA $EA_ID …"

auth_ok=$(curl -sS "${API_BASE}/ea-auth?key=${EA_CODE}")
echo "$auth_ok" | grep -q '"message":"accept"' && pass "ea-auth accepts EA code" || fail "ea-auth live"

SYM="${NEXTRADE_TEST_SYMBOL:-XAUUSD}"
curl -sS -X POST "${API_BASE}/post-signal" \
  -H "Content-Type: application/json" \
  -d "{\"ea_secret\":\"${EA_CODE}\",\"signal\":{\"asset\":\"${SYM}\",\"type\":\"all\",\"action\":\"buy\",\"price\":\"0\",\"tp\":\"10\",\"sl\":\"10\",\"lot\":\"0.01\"}}" \
  | grep -q '"message":"accept"' && pass "post-signal published" || fail "post-signal publish"

sleep 1
active_live=$(curl -sS "${API_BASE}/get-active-signal?eaId=${EA_ID}")
echo "$active_live" | grep -q "\"asset\":\"${SYM}\"" && pass "get-active-signal picked up ${SYM}" || fail "active signal not found: $active_live"

since=$(date -u -v-1M '+%Y-%m-%dT%H:%M:%SZ' 2>/dev/null || date -u -d '1 min ago' '+%Y-%m-%dT%H:%M:%SZ')
poll=$(curl -sS "${API_BASE}/get-new-signals?eaId=${EA_ID}&since=${since}")
echo "$poll" | grep -q "\"asset\":\"${SYM}\"" && pass "get-new-signals poll picked up ${SYM}" || fail "poll missed signal: $poll"

curl -sS -X POST "${API_BASE}/close-signal" \
  -H "Content-Type: application/json" \
  -d "{\"ea_secret\":\"${EA_CODE}\",\"asset\":\"${SYM}\"}" \
  | grep -q '"message":"accept"' && pass "close-signal cleaned up" || fail "close-signal"

echo ""
echo "Signal pickup round-trip passed."
