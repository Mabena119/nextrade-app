#!/usr/bin/env bash
# Upload release APK to nextradeai.io download page.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck source=deploy-nextrade-common.sh
source "$ROOT/scripts/deploy-nextrade-common.sh"
APK="${1:-$ROOT/android/app/build/outputs/apk/release/app-release.apk}"
DEST="${WEBROOT}/admin/downloads/nextradeai.apk"

if [[ ! -f "$APK" ]]; then
  echo "APK not found. Build first: npm run android:apk"
  exit 1
fi

echo "Uploading $(basename "$APK") ($(du -h "$APK" | cut -f1)) → https://nextradeai.io/admin/downloads/nextradeai.apk"
deploy_scp "$APK" "$DEST"
ssh "$REMOTE" "ls -lh '$DEST'"
echo "Done. Test: https://nextradeai.io/admin/downloads/nextradeai.apk"
