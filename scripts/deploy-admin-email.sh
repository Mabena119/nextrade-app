#!/usr/bin/env bash
# Deploy Aura AI admin email pages (reactivate email + license key sender).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
REMOTE="${1:-aura}"
BASE="public_html"

echo "Deploying aemail.php (Reactivate Email page)..."
scp "$ROOT/website/admin/home/aemail.php" "$REMOTE:$BASE/admin/home/aemail.php"

echo "Deploying send_license_email.php..."
scp "$ROOT/website/admin/home/send_license_email.php" "$REMOTE:$BASE/admin/home/send_license_email.php"

echo "Patching key-info.php fetch credentials..."
ssh "$REMOTE" "python3 - <<'PY'
from pathlib import Path
path = Path('public_html/admin/home/key-info.php')
text = path.read_text()
old = \"fetch('send_license_email.php', {\\n\\t\\t\\tmethod: 'POST',\\n\\t\\t\\theaders: { 'Content-Type': 'application/json' },\"
new = \"fetch('send_license_email.php', {\\n\\t\\t\\tmethod: 'POST',\\n\\t\\t\\tcredentials: 'same-origin',\\n\\t\\t\\theaders: { 'Content-Type': 'application/json', 'Accept': 'application/json' },\"
if old not in text:
    if \"credentials: 'same-origin'\" in text:
        print('key-info.php already patched')
    else:
        raise SystemExit('key-info.php pattern not found — patch manually')
else:
    path.write_text(text.replace(old, new, 1))
    print('key-info.php patched')
PY"

echo "Email admin deploy complete."
