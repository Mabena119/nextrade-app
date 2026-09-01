#!/usr/bin/env bash
# Open SSH shell to auraai-vps.com (Host alias "aura" in ~/.ssh/config).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
"$ROOT/scripts/ssh-unlock-auraai.sh" || true
exec ssh aura
