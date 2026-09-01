#!/bin/bash
# Cursor sessionStart hook — always prompt for auraai SSH passphrase.
exec "$(dirname "$0")/../../scripts/ssh-unlock-auraai.sh"
