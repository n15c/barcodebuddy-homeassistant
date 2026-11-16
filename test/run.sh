#!/bin/bash
# Tests ohne Docker: echter Barcode-Buddy-Code (barcodebuddy/) gegen einen Nachbau von Grocy und
# Open Food Facts (test/mock/grocy.php). Braucht PHP 8 (curl, sqlite3, sockets, mbstring) und Node.
# Browsertests laufen zusaetzlich, wenn Playwright mit Chromium installiert ist (UI_TESTS=0 schaltet ab).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WORK="$(mktemp -d)"
PIDS=()
cleanup() {
    for pid in "${PIDS[@]}"; do kill "$pid" 2>/dev/null || true; done
    [ -n "${KEEP_WORK:-}" ] && echo "Arbeitsordner: $WORK" && return
    rm -rf -- "$WORK"
}
trap cleanup EXIT

export WORK
export BB_URL="http://127.0.0.1:9386"
export BBUDDY_HA_OPTIONS_FILE="$WORK/options.json"
export BBUDDY_HA_OFF_URL="http://127.0.0.1:9385/off"
export BBUDDY_HA_IMAGE_PATTERN='#^http://127\.0\.0\.1:(9385|9399)/#'
export BBUDDY_DISABLE_AUTHENTICATION=true
export STATE_DIR="$WORK"

cp -r "$ROOT/barcodebuddy" "$WORK/app"
rm -rf "$WORK/app/data"
mkdir -p "$WORK/data"
ln -s "$WORK/data" "$WORK/app/data"
cat > "$WORK/options.json" <<JSON
{"auto_create_products": true, "auto_create_location": "", "auto_create_quantity_unit": "", "product_language": "de"}
JSON
touch "$WORK/delay_4001234567890"

php -S 127.0.0.1:9385 "$ROOT/test/mock/grocy.php" > "$WORK/mock.log" 2>&1 & PIDS+=($!)
(cd "$WORK/app" && PHP_CLI_SERVER_WORKERS=6 exec php -S 127.0.0.1:9386) > "$WORK/bb.log" 2>&1 & PIDS+=($!)
(cd "$WORK/app" && exec php wsserver.php) > "$WORK/ws.log" 2>&1 & PIDS+=($!)
sleep 1.5

php -r '
require_once getenv("WORK") . "/app/incl/configProcessing.inc.php";
require_once getenv("WORK") . "/app/incl/db.inc.php";
$db = DatabaseConnection::getInstance();
$db->updateConfig("GROCY_API_URL", "http://127.0.0.1:9385/grocy/api/");
$db->updateConfig("GROCY_API_KEY", "testkey");
foreach (array("LOOKUP_USE_OFF","LOOKUP_USE_UPC","LOOKUP_USE_JUMBO","LOOKUP_USE_AH","LOOKUP_USE_UPC_DATABASE","LOOKUP_USE_OPEN_GTIN_DATABASE","LOOKUP_USE_DISCOGS","LOOKUP_USE_PLUS") as $k)
    $db->updateConfig($k, "0");
'

echo "== JavaScript"
for t in "$ROOT"/test/js/*.test.js; do node "$t"; done

echo "== PHP Ende-zu-Ende"
php "$ROOT/test/e2e.php"

if [ "${UI_TESTS:-1}" = "1" ] && node -e "require('playwright')" 2>/dev/null; then
    echo "== Browser"
    rm -f "$WORK/state.json" "$WORK/data/ha_storage.json"
    php -r 'require_once getenv("WORK") . "/app/incl/configProcessing.inc.php"; require_once getenv("WORK") . "/app/incl/db.inc.php"; DatabaseConnection::getInstance()->getDatabaseReference()->exec("DELETE FROM Barcodes");'
    node "$ROOT/test/ui/screen.test.js"
else
    echo "== Browser: uebersprungen (Playwright nicht installiert oder UI_TESTS=0)"
fi
echo "Alle Tests bestanden"
