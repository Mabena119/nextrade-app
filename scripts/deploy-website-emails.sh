#!/usr/bin/env bash
# Upload Aura AI email files to cPanel (requires working SSH: aura)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck source=deploy-common.sh
source "$ROOT/scripts/deploy-common.sh"

echo "Uploading email includes ..."
deploy_scp "$ROOT/website/includes/email-config.php" "${WEBROOT}/includes/email-config.php"
deploy_scp "$ROOT/website/includes/mailer.php" "${WEBROOT}/includes/mailer.php"
deploy_scp "$ROOT/website/includes/auraai-emails.php" "${WEBROOT}/includes/auraai-emails.php"
deploy_scp "$ROOT/website/includes/password-reset.php" "${WEBROOT}/includes/password-reset.php"
deploy_scp "$ROOT/website/includes/.htaccess" "${WEBROOT}/includes/.htaccess"

echo "Uploading admin forgot/reset password pages ..."
deploy_scp "$ROOT/website/admin/forgot-password.php" "${WEBROOT}/admin/forgot-password.php"
deploy_scp "$ROOT/website/admin/reset-password.php" "${WEBROOT}/admin/reset-password.php"

if [[ -f "$ROOT/website/admin/home/send_license_email.php" ]]; then
  deploy_scp "$ROOT/website/admin/home/send_license_email.php" "${WEBROOT}/admin/home/send_license_email.php"
fi

echo "Done. Email files uploaded to ${WEBROOT}."
echo "Secrets stay in /home/${CPANEL_USER}/auraai-secrets.php (see secure-email-relay.sh)."
