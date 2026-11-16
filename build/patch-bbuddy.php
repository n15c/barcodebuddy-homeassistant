<?php
// Wird beim Bauen des Images ausgefuehrt: haengt die Home-Assistant-Erweiterungen in
// BarcodeBuddy ein. Bricht den Build ab, wenn sich der BarcodeBuddy-Code geaendert hat und
// eine Stelle nicht mehr (genau einmal) gefunden wird.

$base = getenv("BBUDDY_DIR") ?: "/app/bbuddy";

$patches = array(
    // 1. Unbekannte Produkte aus Open Food Facts in Grocy anlegen
    array(
        "file"   => "incl/processing.inc.php",
        "marker" => "HaAutoCreate::tryCreate",
        "search" => 'function processUnknownBarcode(string $barcode, bool $websocketEnabled, LockGenerator &$fileLock, ?string $bestBeforeInDays, ?string $price): string {',
        "insert" => "after",
        "code"   => <<<'PHP'

    // Home-Assistant-Add-on: unbekannte Produkte aus Open Food Facts in Grocy anlegen
    require_once __DIR__ . "/haAutoCreate.inc.php";
    $haResult = HaAutoCreate::tryCreate($barcode, $websocketEnabled, $fileLock, $bestBeforeInDays, $price);
    if ($haResult !== null)
        return $haResult;
PHP
    ),
    // 1b. Sofortige Rueckmeldung an die Screen-Seite, sobald ein Barcode verarbeitet wird
    array(
        "file"   => "incl/processing.inc.php",
        "marker" => 'HaAutoCreate::progress("received"',
        "search" => '    $sanitizedBarcode = sanitizeString($barcode);',
        "insert" => "after",
        "code"   => <<<'PHP'

    // Home-Assistant-Add-on: Screen-Seite zeigt "wird verarbeitet", bis das Ergebnis da ist
    require_once __DIR__ . "/haAutoCreate.inc.php";
    HaAutoCreate::progress("received", $sanitizedBarcode);
PHP
    ),
    // 2. Einlagerungsmodus: Lagerort setzen und Einkauf fuer die MHD-Abfrage vormerken
    array(
        "file"    => "incl/api.inc.php",
        "marker"  => "HaStorage::beforePurchase",
        "search"  => <<<'PHP'
        $data_json                = json_encode($data);
        $url                      = API_STOCK . "/" . $id . "/add";

        $curl = new CurlGenerator($url, METHOD_POST, $data_json);
        try {
            $curl->execute();
        } catch (Exception $e) {
            self::processError($e, "Could not add product to inventory");
        }
PHP,
        "insert"  => "replace",
        "code"    => <<<'PHP'
        // Home-Assistant-Add-on: Einlagerungsmodus (Lagerort + MHD-Abfrage)
        require_once __DIR__ . "/haStorage.inc.php";
        $data = HaStorage::beforePurchase($id, $data);

        $data_json                = json_encode($data);
        $url                      = API_STOCK . "/" . $id . "/add";

        $curl = new CurlGenerator($url, METHOD_POST, $data_json);
        $haPurchaseResponse = null;
        try {
            $haPurchaseResponse = $curl->execute();
        } catch (Exception $e) {
            self::processError($e, "Could not add product to inventory");
        }
        HaStorage::afterPurchase($id, $haPurchaseResponse);
PHP
    ),
    // 3. Hintergrundjob (alle 2 Minuten): fehlgeschlagene Produktbilder nachladen
    array(
        "file"   => "cron.php",
        "marker" => "HaAutoCreate::retryPendingPictures",
        "search" => 'if (isset($_GET["ajax"]))',
        "insert" => "before",
        "code"   => <<<'PHP'
// Home-Assistant-Add-on: Produktbilder nachladen, deren Download beim Scan fehlschlug
require_once __DIR__ . "/incl/haAutoCreate.inc.php";
HaAutoCreate::retryPendingPictures();


PHP
    ),
    // 4. Screen-Seite: Menuepunkt "Einlagern" und Oberflaeche (entfaellt mit der eigenen screen.php)
    array(
        "file"   => "screen.php",
        "marker" => "ha-storage-menu",
        "search" => '        <a href="#" onclick="sendBarcode(\'<?php echo BBConfig::getInstance()["BARCODE_P"] ?>\')">Purchase</a>',
        "insert" => "before",
        "code"   => "        <a href=\"#\" id=\"ha-storage-menu\" onclick=\"return haStorageOpen()\">Einlagern …</a>\n",
    ),
    array(
        "file"   => "screen.php",
        "marker" => "haStorage.js",
        "search" => "</body>",
        "insert" => "before",
        "code"   => "<script src=\"./incl/js/haStorage.js\"></script>\n",
    ),
);

foreach ($patches as $patch) {
    $file = $base . "/" . $patch["file"];
    $code = file_get_contents($file);
    if ($code === false) {
        fwrite(STDERR, "Datei $file nicht lesbar\n");
        exit(1);
    }
    if (strpos($code, $patch["marker"]) !== false)
        continue; // bereits eingefuegt
    if (substr_count($code, $patch["search"]) !== 1) {
        fwrite(STDERR, "Stelle fuer '" . $patch["marker"] . "' in " . $patch["file"] . " nicht eindeutig gefunden - BarcodeBuddy-Version passt nicht zum Patch\n");
        exit(1);
    }
    switch ($patch["insert"]) {
        case "after":
            $replacement = $patch["search"] . $patch["code"];
            break;
        case "before":
            $replacement = $patch["code"] . $patch["search"];
            break;
        default:
            $replacement = $patch["code"];
    }
    if (file_put_contents($file, str_replace($patch["search"], $replacement, $code)) === false) {
        fwrite(STDERR, "Konnte $file nicht schreiben\n");
        exit(1);
    }
    echo "BarcodeBuddy: " . $patch["marker"] . " in " . $patch["file"] . " eingefuegt\n";
}
