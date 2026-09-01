#!/usr/bin/env bash
# Open SSH shell to NexTrade AI Lightsail (Host alias "nextrade" in ~/.ssh/config).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
"$ROOT/scripts/ssh-add-nextrade.sh" || true
exec ssh nextrade
