#!/usr/bin/env bash
# Create ~/nextradeai-secrets.php + import empty schema on nextradeai.io (first-time setup).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck source=deploy-nextrade-common.sh
source "$ROOT/scripts/deploy-nextrade-common.sh"

SCHEMA="${1:-/tmp/nextradeai-schema.sql}"
if [[ ! -f "$SCHEMA" ]]; then
  echo "Schema file not found: $SCHEMA" >&2
  echo "Generate with: python3 scripts/extract-schema.py /path/to/dump.sql > /tmp/nextradeai-schema.sql" >&2
  exit 1
fi

echo "Deploying connect stubs..."
deploy_scp "$ROOT/website/includes/db-config.php" "${WEBROOT}/includes/db-config.php"
deploy_scp "$ROOT/website/admin/php-includes/connect.php" "${WEBROOT}/admin/php-includes/connect.php"
deploy_scp "$ROOT/website/php-includes/connect.php" "${WEBROOT}/php-includes/connect.php"
deploy_scp "$ROOT/website/admin/home/php-includes/connect.php" "${WEBROOT}/admin/home/php-includes/connect.php"

scp "$SCHEMA" "${REMOTE}:/tmp/nextradeai-schema.sql"
ssh "$REMOTE" "sudo bash -s" <<REMOTE
set -euo pipefail
SECRETS="${SECRETS}"
touch "\$SECRETS"
chown ${CPANEL_USER}:${CPANEL_USER} "\$SECRETS"
chmod 600 "\$SECRETS"
sed -i '/NEXTRADEAI_DB_/d' "\$SECRETS" 2>/dev/null || true
sed -i '/AURAAI_DB_/d' "\$SECRETS" 2>/dev/null || true
cat >> "\$SECRETS" <<DBEOF

/** NexTradeAI cPanel MySQL */
define('NEXTRADEAI_DB_HOST', '${CPANEL_DB_HOST}');
define('NEXTRADEAI_DB_USER', '${CPANEL_DB_USER}');
define('NEXTRADEAI_DB_PASS', '${CPANEL_DB_PASS}');
define('NEXTRADEAI_DB_NAME', '${CPANEL_DB_NAME}');
DBEOF

mysql -u '${CPANEL_DB_USER}' -p'${CPANEL_DB_PASS}' '${CPANEL_DB_NAME}' < /tmp/nextradeai-schema.sql
rm -f /tmp/nextradeai-schema.sql
mysql -u '${CPANEL_DB_USER}' -p'${CPANEL_DB_PASS}' '${CPANEL_DB_NAME}' -e "SHOW TABLES;"
REMOTE

echo "Done. DB: ${CPANEL_DB_NAME} | user: ${CPANEL_DB_USER}"
