#!/bin/bash
set -euo pipefail

TARGET="/Users/justvino__/Desktop/expo/aura-ai-app"
MT5="/Users/justvino__/Library/Application Support/net.metaquotes.wine.metatrader5/drive_c/Program Files/MetaTrader 5/MQL5/Experts/Advisors/AURAAI/AURAAI.mq5"

replace_in_file() {
  local file="$1"
  if [[ ! -f "$file" ]] || ! file "$file" | grep -q text; then
    return 0
  fi
  perl -i -pe '
    s/www\.auraai\.io/auraai-vps.com/g;
    s/aura-ai-app\.onrender\.com/auraai-vps.com/g;
    s/auraai\.io/auraai-vps.com/g;
    s/auraai-vps-cpanel/auraai-vps-cpanel/g;
    s/your-app\.onrender\.com/auraai-vps.com/g;
  ' "$file"
}

while IFS= read -r -d '' file; do
  replace_in_file "$file"
done < <(find "$TARGET" -type f \
  ! -path '*/node_modules/*' \
  ! -path '*/.git/*' \
  ! -path '*/android/build/*' \
  ! -path '*/android/app/build/*' \
  ! -path '*/android/app/.cxx/*' \
  ! -path '*/ios/Pods/*' \
  -print0)

replace_in_file "$MT5"
cp "$TARGET/mt5/AURAAI.mq5" "$MT5" 2>/dev/null || true

echo "Domain update complete"
