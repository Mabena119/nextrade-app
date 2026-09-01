#!/usr/bin/env bash
# Ensure ~/auraai-secrets.php includes cPanel MySQL constants (idempotent merge).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck source=deploy-common.sh
source "$ROOT/scripts/deploy-common.sh"

DB_PASS="${AURAAI_DB_PASS:-auraai@2026}"

echo "Deploying db-config.php + connect.php stubs..."
deploy_scp "$ROOT/website/includes/db-config.php" "${WEBROOT}/includes/db-config.php"
deploy_scp "$ROOT/website/admin/php-includes/connect.php" "${WEBROOT}/admin/php-includes/connect.php"
deploy_scp "$ROOT/website/php-includes/connect.php" "${WEBROOT}/php-includes/connect.php"
deploy_scp "$ROOT/website/admin/home/php-includes/connect.php" "${WEBROOT}/admin/home/php-includes/connect.php"

echo "Updating ~/auraai-secrets.php DB constants ..."
ssh "$REMOTE" "sudo bash -s" <<REMOTE
set -euo pipefail
SECRETS="/home/${CPANEL_USER}/auraai-secrets.php"
touch "\$SECRETS"
chown ${CPANEL_USER}:${CPANEL_USER} "\$SECRETS"
chmod 600 "\$SECRETS"

# Remove stale prefixed constants if present
sed -i '/AURAAI_DB_/d' "\$SECRETS" 2>/dev/null || true

if ! grep -q 'AURAAI_DB_HOST' "\$SECRETS" 2>/dev/null; then
  cat >> "\$SECRETS" <<DBEOF

/** cPanel MySQL */
define('AURAAI_DB_HOST', '${CPANEL_DB_HOST}');
define('AURAAI_DB_USER', '${CPANEL_DB_USER}');
define('AURAAI_DB_PASS', '${DB_PASS}');
define('AURAAI_DB_NAME', '${CPANEL_DB_NAME}');
DBEOF
fi

mysql -u '${CPANEL_DB_USER}' -p'${DB_PASS}' -e "SELECT COUNT(*) AS admin_rows FROM ${CPANEL_DB_NAME}.admin;"
REMOTE

echo "Done. phpMyAdmin DB: ${CPANEL_DB_NAME} | user: ${CPANEL_DB_USER}"
