#!/usr/bin/env bash
# Add NexTrade AI Lightsail key to ssh-agent (macOS keychain-friendly).
set -euo pipefail

KEY="${HOME}/.ssh/nextrade.pem"
HOST="nextrade"

if [ ! -f "$KEY" ]; then
  echo "Missing key: $KEY" >&2
  exit 1
fi

chmod 600 "$KEY"
ssh-add --apple-use-keychain "$KEY" 2>/dev/null || ssh-add "$KEY" 2>/dev/null || true
ssh -o BatchMode=yes -o ConnectTimeout=8 "$HOST" "echo ok" >/dev/null 2>&1 || true
