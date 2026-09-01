#!/usr/bin/env bash
# Rotate relay secret + deploy email secrets (never prints passwords).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck source=deploy-common.sh
source "$ROOT/scripts/deploy-common.sh"
RELAY_SECRET="${AURAAI_EMAIL_RELAY_SECRET:-}"
GMAIL_PASS="${GMAIL_PASS:-}"
GMAIL_USER="${GMAIL_USER:-}"

if [[ -z "$RELAY_SECRET" ]]; then
  RELAY_SECRET="$(openssl rand -hex 24)"
  echo "Generated relay secret."
fi

if [[ -z "$GMAIL_PASS" || -z "$GMAIL_USER" ]]; then
  echo "ERROR: Set GMAIL_USER and GMAIL_PASS (new Gmail app password)."
  echo "  GMAIL_USER=you@gmail.com GMAIL_PASS=xxxx AURAAI_EMAIL_RELAY_SECRET=yyyy $0"
  exit 1
fi

echo "Uploading secured email files..."
deploy_scp "$ROOT/website/includes/email-config.php" "${WEBROOT}/includes/email-config.php"
deploy_scp "$ROOT/website/includes/mailer.php" "${WEBROOT}/includes/mailer.php"
deploy_scp "$ROOT/website/includes/.htaccess" "${WEBROOT}/includes/.htaccess"

echo "Writing ${CPANEL_USER} auraai-secrets.php on server..."
ssh "$REMOTE" "sudo bash -s" <<REMOTE
set -euo pipefail
SECRETS="/home/${CPANEL_USER}/auraai-secrets.php"
if [[ ! -f "\$SECRETS" ]]; then
  cat > "\$SECRETS" <<SECRETS
<?php
define('GMAIL_USER', '${GMAIL_USER}');
define('GMAIL_PASS', '${GMAIL_PASS}');
define('AURAAI_EMAIL_RELAY_SECRET', '${RELAY_SECRET}');

/** cPanel MySQL */
define('AURAAI_DB_HOST', 'localhost');
define('AURAAI_DB_USER', 'auraaiadmin');
define('AURAAI_DB_PASS', '${AURAAI_DB_PASS:-auraai@2026}');
define('AURAAI_DB_NAME', 'auraai');
SECRETS
else
  sed -i "s|define('GMAIL_USER',.*|define('GMAIL_USER', '${GMAIL_USER}');|" "\$SECRETS"
  sed -i "s|define('GMAIL_PASS',.*|define('GMAIL_PASS', '${GMAIL_PASS}');|" "\$SECRETS"
  if grep -q "AURAAI_EMAIL_RELAY_SECRET" "\$SECRETS"; then
    sed -i "s|define('AURAAI_EMAIL_RELAY_SECRET',.*|define('AURAAI_EMAIL_RELAY_SECRET', '${RELAY_SECRET}');|" "\$SECRETS"
  else
    sed -i "/^<?php/a define('AURAAI_EMAIL_RELAY_SECRET', '${RELAY_SECRET}');" "\$SECRETS"
  fi
fi
chown ${CPANEL_USER}:${CPANEL_USER} "\$SECRETS"
chmod 600 "\$SECRETS"
REMOTE

echo "Removing public test email endpoints..."
ssh "$REMOTE" "sudo rm -f ${WEBROOT}/test-all-emails.php ${WEBROOT}/test-email.php"

echo ""
echo "=== DONE (server) ==="
echo "Relay secret stored in /home/${CPANEL_USER}/auraai-secrets.php."
echo "Run: ./scripts/update-render-env.sh (requires RENDER_API_KEY)"
