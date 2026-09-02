#!/usr/bin/env bash
# Deploy Gmail + relay secret to NexTradeAI Lightsail (~/nextradeai-secrets.php).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck source=deploy-nextrade-common.sh
source "$ROOT/scripts/deploy-nextrade-common.sh"

RELAY_SECRET="${AURAAI_EMAIL_RELAY_SECRET:-}"
GMAIL_PASS="${GMAIL_PASS:-}"
GMAIL_USER="${GMAIL_USER:-}"

if [[ -z "$RELAY_SECRET" ]]; then
  RELAY_SECRET="$(openssl rand -hex 24)"
  echo "Generated relay secret."
fi

if [[ -z "$GMAIL_PASS" || -z "$GMAIL_USER" ]]; then
  echo "ERROR: Set GMAIL_USER and GMAIL_PASS."
  echo "  GMAIL_USER=you@gmail.com GMAIL_PASS=xxxx AURAAI_EMAIL_RELAY_SECRET=yyyy $0"
  exit 1
fi

echo "Uploading email config to ${WEBROOT}..."
deploy_scp "$ROOT/website/includes/email-config.php" "${WEBROOT}/includes/email-config.php"
deploy_scp "$ROOT/website/includes/mailer.php" "${WEBROOT}/includes/mailer.php"
deploy_scp "$ROOT/website/includes/auraai-emails.php" "${WEBROOT}/includes/auraai-emails.php"

echo "Writing ${SECRETS} on server..."
ssh "$REMOTE" "sudo bash -s" <<REMOTE
set -euo pipefail
SECRETS="${SECRETS}"
if [[ ! -f "\$SECRETS" ]]; then
  cat > "\$SECRETS" <<SECRETS
<?php
define('GMAIL_USER', '${GMAIL_USER}');
define('GMAIL_PASS', '${GMAIL_PASS}');
define('GMAIL_FROM_NAME', 'NexTradeAI');
define('AURAAI_EMAIL_RELAY_SECRET', '${RELAY_SECRET}');

define('NEXTRADEAI_DB_HOST', 'localhost');
define('NEXTRADEAI_DB_USER', '${CPANEL_DB_USER}');
define('NEXTRADEAI_DB_PASS', '${CPANEL_DB_PASS}');
define('NEXTRADEAI_DB_NAME', '${CPANEL_DB_NAME}');
SECRETS
else
  grep -q "define('GMAIL_USER'" "\$SECRETS" && sed -i "s|define('GMAIL_USER',.*|define('GMAIL_USER', '${GMAIL_USER}');|" "\$SECRETS" || sed -i "/^<?php/a define('GMAIL_USER', '${GMAIL_USER}');" "\$SECRETS"
  grep -q "define('GMAIL_PASS'" "\$SECRETS" && sed -i "s|define('GMAIL_PASS',.*|define('GMAIL_PASS', '${GMAIL_PASS}');|" "\$SECRETS" || sed -i "/^<?php/a define('GMAIL_PASS', '${GMAIL_PASS}');" "\$SECRETS"
  grep -q "define('GMAIL_FROM_NAME'" "\$SECRETS" && sed -i "s|define('GMAIL_FROM_NAME',.*|define('GMAIL_FROM_NAME', 'NexTradeAI');|" "\$SECRETS" || sed -i "/^<?php/a define('GMAIL_FROM_NAME', 'NexTradeAI');" "\$SECRETS"
  if grep -q "AURAAI_EMAIL_RELAY_SECRET" "\$SECRETS"; then
    sed -i "s|define('AURAAI_EMAIL_RELAY_SECRET',.*|define('AURAAI_EMAIL_RELAY_SECRET', '${RELAY_SECRET}');|" "\$SECRETS"
  else
    sed -i "/^<?php/a define('AURAAI_EMAIL_RELAY_SECRET', '${RELAY_SECRET}');" "\$SECRETS"
  fi
fi
chown ${CPANEL_USER}:${CPANEL_USER} "\$SECRETS"
chmod 600 "\$SECRETS"
REMOTE

echo ""
echo "=== NexTradeAI email secrets deployed ==="
echo "Relay secret: (stored in ${SECRETS})"
echo "Next: export AURAAI_EMAIL_RELAY_SECRET and run ./scripts/update-render-env.sh"
