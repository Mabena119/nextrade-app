#!/usr/bin/env bash
# Upload release APK to auraai-vps.com download page.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck source=deploy-common.sh
source "$ROOT/scripts/deploy-common.sh"
REMOTE="${1:-$REMOTE}"
APK="$ROOT/android/app/build/outputs/apk/release/app-release.apk"
DEST="${WEBROOT}/admin/downloads/auraai.apk"

if [[ ! -f "$APK" ]]; then
  echo "APK not found. Build first: npm run android:apk"
  exit 1
fi

echo "Uploading $(basename "$APK") ($(du -h "$APK" | cut -f1)) → https://auraai-vps.com/admin/downloads/auraai.apk"
deploy_scp "$APK" "$DEST"
ssh "$REMOTE" "ls -lh '$DEST'"
echo "Done. Test: https://auraai-vps.com/admin/downloads/auraai.apk"
