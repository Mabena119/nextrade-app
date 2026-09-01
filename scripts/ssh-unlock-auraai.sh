#!/usr/bin/env bash
# Prompt for the auraai-vps.com SSH key passphrase (macOS dialog).
# Called on project open and at Cursor agent session start.

set -euo pipefail

KEY="${HOME}/.ssh/aura.pem"
HOST="aura"

if [ ! -f "$KEY" ]; then
  exit 0
fi

PASS=$(osascript 2>/dev/null <<'APPLESCRIPT' || true
try
  set d to display dialog "Unlock SSH for auraai-vps.com?" & return & return & "Enter your key passphrase:" default answer "" with hidden answer buttons {"Skip", "Unlock"} default button "Unlock" with title "Aura AI SSH"
  if button returned of d is "Skip" then
    return ""
  end if
  return text returned of d
on error
  return ""
end try
APPLESCRIPT
)

if [ -z "${PASS:-}" ]; then
  exit 0
fi

TMP_ASK=$(mktemp)
chmod 700 "$TMP_ASK"
export EA_SSH_PASS="$PASS"
cat > "$TMP_ASK" <<'ASKPASS'
#!/bin/sh
printf '%s\n' "$EA_SSH_PASS"
ASKPASS
chmod 700 "$TMP_ASK"

# Replace any existing agent entry for this key, then add with the new passphrase.
ssh-add -d "$KEY" >/dev/null 2>&1 || true
DISPLAY="${DISPLAY:-:0}" SSH_ASKPASS="$TMP_ASK" SSH_ASKPASS_REQUIRE=force ssh-add "$KEY" </dev/null >/dev/null 2>&1 || true

rm -f "$TMP_ASK"
unset EA_SSH_PASS PASS

# Quick connectivity check (non-blocking if it fails).
ssh -o BatchMode=yes -o ConnectTimeout=8 "$HOST" "echo ok" >/dev/null 2>&1 || true

exit 0
