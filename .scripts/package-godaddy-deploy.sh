#!/usr/bin/env bash
# Package production assets for GoDaddy/cPanel: upload the zip, then Extract in File Manager.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

npm run build

STAGE="$ROOT/.deploy-staging"
rm -rf "$STAGE"
mkdir -p "$STAGE/assets/js" "$STAGE/assets/css" "$STAGE/components"

cp sw.js "$STAGE/"
cp assets/js/app.min.js "$STAGE/assets/js/"
cp assets/css/main.css "$STAGE/assets/css/"
cp index.php "$STAGE/"
cp components/setup_panel.php "$STAGE/components/"

# Fail if local bundle is invalid or looks like a prior bad upload.
node --check "$STAGE/assets/js/app.min.js"
if grep -q 'WebKitFormBoundary' "$STAGE/assets/js/app.min.js"; then
  echo "error: app.min.js contains WebKitFormBoundary" >&2
  exit 1
fi

OUT="$ROOT/godaddy-deploy.zip"
rm -f "$OUT"
(cd "$STAGE" && zip -r "$OUT" .)

BYTES=$(wc -c < "$STAGE/assets/js/app.min.js" | tr -d ' ')
echo "Created $OUT"
echo "app.min.js size: ${BYTES} bytes (must stay one line; no multipart junk after extract)"
echo ""
echo "On GoDaddy:"
echo "  1. Delete public_html/music/assets/js/app.min.js (and old zip if any)."
echo "  2. Upload godaddy-deploy.zip to public_html/music/"
echo "  3. File Manager → godaddy-deploy.zip → Extract → confirm paths under music/"
echo "  4. Verify: curl -s https://billkummerow.com/music/assets/js/app.min.js | grep WebKitFormBoundary"
echo "     (should print nothing)"
