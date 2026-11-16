<?php
/**
 * Ende-zu-Ende-Tests der Add-on-Erweiterungen mit dem echten Barcode-Buddy-Code.
 * Wird von test/run.sh gestartet (Barcode Buddy auf $BB_URL, Grocy-Nachbau auf Port 9385).
 */

$work    = getenv("WORK");
$bbUrl   = getenv("BB_URL");
$app     = "$work/app";
$failed  = 0;
$checked = 0;

function check(string $what, bool $ok, string $detail = ""): void {
    global $failed, $checked;
    $checked++;
    if (!$ok)
        $failed++;
    echo ($ok ? "  ok   " : "  FAIL ") . $what . ($ok || $detail === "" ? "" : " – $detail") . "\n";
}

function scan(string $barcode): void {
    global $app;
    exec("cd " . escapeshellarg($app) . " && php index.php " . escapeshellarg($barcode) . " 2>&1", $out);
}

function grocy(): array {
    global $work;
    return json_decode((string)@file_get_contents("$work/state.json"), true) ?: array();
}

function http(string $method, string $path, ?array $json = null, ?string $rawBody = null, string $contentType = "application/json"): array {
    global $bbUrl;
    $ch = curl_init($bbUrl . "/" . $path);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($json !== null || $rawBody !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody ?? json_encode($json));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: $contentType"));
    }
    $body   = (string)curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return array($status, json_decode($body, true), $body);
}

function product(string $name): ?array {
    foreach (grocy()["products"] ?? array() as $p)
        if ($p["name"] === $name)
            return $p;
    return null;
}

function lastStock(): ?array {
    $stock = grocy()["stock"] ?? array();
    return count($stock) ? end($stock) : null;
}

echo "-- Automatische Produktanlage\n";
scan("BBUDDY-P");
scan("4000417025005");
$p = product("Nuss-Nougat-Creme & Kakao");
check("Produkt aus Open Food Facts angelegt", $p !== null);
check("Beschreibung mit Nährwerten", $p !== null && strpos($p["description"], "Nährwerte pro 100 g") !== false);
check("Produktbild hochgeladen", $p !== null && !empty($p["picture_file_name"]));
check("Barcode gespeichert", in_array("4000417025005", array_column(grocy()["product_barcodes"], "barcode"), true));
check("Einkauf gebucht", lastStock() !== null && (int)lastStock()["product_id"] === (int)($p["id"] ?? -1));

scan("4099999999999");
check("Unbekannt bei Open Food Facts: kein Produkt angelegt", count(grocy()["products"]) === 2);

echo "-- Bild nachladen\n";
scan("4008400301020");
$queue = json_decode((string)@file_get_contents("$work/data/ha_pictures.json"), true) ?: array();
check("Bildserver nicht erreichbar: Bild vorgemerkt", count($queue) === 1);
$queue[0]["next_try"] = 0;
file_put_contents("$work/data/ha_pictures.json", json_encode($queue));
@mkdir("$work/imageserver");
$imageServer = proc_open(array(PHP_BINARY, "-S", "127.0.0.1:9399", __DIR__ . "/mock/grocy.php"), array(1 => array("file", "/dev/null", "w"), 2 => array("file", "/dev/null", "w")), $pipes, null, array_merge(getenv(), array("STATE_DIR" => "$work/imageserver")));
usleep(600000);
exec("cd " . escapeshellarg($app) . " && php cron.php");
proc_terminate($imageServer);
check("Hintergrundjob lädt das Bild nach", !empty(product("Bildtest")["picture_file_name"]));
check("Warteschlange danach leer", (json_decode((string)file_get_contents("$work/data/ha_pictures.json"), true) ?: array()) === array());

echo "-- Einlagern und MHD\n";
list($status, $location) = http("POST", "ha_storage.php?action=start", array("location_id" => 2));
check("Einlagerung im Kühlschrank gestartet", $status === 200 && $location["location"]["name"] === "Kühlschrank");
scan("4000000000001");
check("Einkauf im Kühlschrank gebucht", (int)lastStock()["location_id"] === 2);
list(, $state) = http("GET", "ha_storage.php?action=state");
check("MHD-Abfrage vorgemerkt", count($state["pending"]) === 1 && $state["pending"][0]["name"] === "Milch");
list($status) = http("POST", "ha_storage.php?action=bestbefore", array("id" => $state["pending"][0]["id"], "date" => "2027-02-30"));
check("Ungültiges Datum abgelehnt", $status === 400);
http("POST", "ha_storage.php?action=bestbefore", array("id" => $state["pending"][0]["id"], "date" => "2027-02-01"));
check("MHD in Grocy gesetzt, Lagerort erhalten", lastStock()["best_before_date"] === "2027-02-01" && (int)lastStock()["location_id"] === 2);
list($status) = http("POST", "ha_storage.php?action=start", null, "location_id=2", "application/x-www-form-urlencoded");
check("POST ohne JSON abgelehnt", $status === 415);

echo "-- Menge korrigieren\n";
list($status, $result) = http("POST", "ha_storage.php?action=quantity", array("amount" => 6));
check("Menge des letzten Einkaufs auf 6", $status === 200 && (float)lastStock()["amount"] === 6.0, json_encode($result));

echo "-- Produkt bearbeiten\n";
list($status, $edit) = http("GET", "ha_storage.php?action=product");
check("Produkt des letzten Einkaufs geladen", $status === 200 && $edit["name"] === "Milch" && count($edit["units"]) >= 2);
list($status) = http("POST", "ha_storage.php?action=product", array("product_id" => $edit["product_id"], "qu_id" => 5));
check("Mengeneinheit ohne Umrechnung abgelehnt", $status === 400);
list($status, $result) = http("POST", "ha_storage.php?action=product", array("product_id" => $edit["product_id"], "qu_id" => 5, "factor" => 1000, "location_id" => 3));
$milk = product("Milch");
check("Mengeneinheit gewechselt", $status === 200 && (int)$milk["qu_id_stock"] === 5, json_encode($result));
check("Umrechnung angelegt, Bestand umgerechnet", (float)lastStock()["amount"] === 6000.0);
check("Standard-Lagerort geändert, letzter Einkauf umgezogen", (int)$milk["location_id"] === 3 && (int)lastStock()["location_id"] === 3);
http("POST", "ha_storage.php?action=stop", array());

echo "-- Oberfläche\n";
list($status, , $html) = http("GET", "index.php");
check("Übersicht auf Deutsch", $status === 200 && strpos($html, ">Übersicht<") !== false && strpos($html, '<html lang="de">') !== false);
check("Federation entfernt", strpos($html, "federation") === false && http("GET", "menu/federation.php")[0] === 404);
exec("cd " . escapeshellarg($app) . " && php -r 'require \"incl/configProcessing.inc.php\"; require \"incl/db.inc.php\"; DatabaseConnection::getInstance()->updateConfig(\"LOOKUP_ORDER\", \"1,2,3,4,5,6,7,8,9\");'");
list($status, , $html) = http("GET", "menu/settings.php");
check("Einstellungen mit alter Lookup-Reihenfolge (Federation-ID 7)", $status === 200 && strpos($html, "Fatal") === false);
check("Screen-Seite lädt", http("GET", "screen.php")[0] === 200);

echo "\n$checked Prüfungen, " . ($checked - $failed) . " bestanden\n";
exit($failed > 0 ? 1 : 0);
