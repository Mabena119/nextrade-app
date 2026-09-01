#!/usr/bin/env bash
# Deploy Aura AI website security layer to cPanel
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
REMOTE="aura"
BASE="public_html"

scp "$ROOT/website/includes/security.php" "$REMOTE:$BASE/includes/security.php"
scp "$ROOT/website/includes/ip-block.php" "$REMOTE:$BASE/includes/ip-block.php"
scp "$ROOT/website/includes/ip-block-guard.php" "$REMOTE:$BASE/includes/ip-block-guard.php"
scp "$ROOT/website/includes/password-reset.php" "$REMOTE:$BASE/includes/password-reset.php"
scp "$ROOT/website/admin/php-includes/security-bridge.php" "$REMOTE:$BASE/admin/php-includes/security-bridge.php"
scp "$ROOT/website/admin/login.php" "$REMOTE:$BASE/admin/login.php"
scp "$ROOT/website/admin/index.php" "$REMOTE:$BASE/admin/index.php"
scp "$ROOT/website/admin/forgot-password.php" "$REMOTE:$BASE/admin/forgot-password.php"
scp "$ROOT/website/admin/reset-password.php" "$REMOTE:$BASE/admin/reset-password.php"
scp "$ROOT/website/admin/user_request.php" "$REMOTE:$BASE/admin/user_request.php"
scp "$ROOT/website/admin/hostsignup.php" "$REMOTE:$BASE/admin/hostsignup.php"
scp "$ROOT/website/admin/home/send_license_email.php" "$REMOTE:$BASE/admin/home/send_license_email.php"
scp "$ROOT/website/admin/home/updateuser.php" "$REMOTE:$BASE/admin/home/updateuser.php"
scp "$ROOT/website/shop/webhook1.php" "$REMOTE:$BASE/shop/webhook1.php"
scp "$ROOT/website/shop/notifyb.php" "$REMOTE:$BASE/shop/notifyb.php"

echo "Security deploy complete."
