<?php
/**
 * Home-Assistant-Add-on: Schnittstelle fuer den Einlagerungsmodus der Screen-Seite.
 *
 * GET  ?action=state             Zustand (laufende Einlagerung, offene MHD-Abfragen)
 * GET  ?action=locations         Lagerorte aus Grocy
 * GET  ?action=picture&product=N Produktbild (ueber BarcodeBuddy, da Grocy einen API-Schluessel braucht)
 * POST ?action=start             {"location_id": N}
 * POST ?action=stop
 * POST ?action=bestbefore        {"id": "...", "date": "YYYY-MM-DD" | "never" | null}
 * POST ?action=quantity          {"amount": N}  Menge des zuletzt gebuchten Einkaufs korrigieren
 *
 * POST-Anfragen muessen JSON senden; das verhindert, dass fremde Seiten sie per Formular ausloesen.
 */

require_once __DIR__ . "/incl/configProcessing.inc.php";
require_once __DIR__ . "/incl/db.inc.php";
require_once __DIR__ . "/incl/processing.inc.php";
require_once __DIR__ . "/incl/config.inc.php";
require_once __DIR__ . "/incl/haStorage.inc.php";

function haRespond(int $code, $data): void {
    http_response_code($code);
    header("Content-Type: application/json; charset=utf-8");
    header("Cache-Control: no-store");
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (!$CONFIG->checkIfAuthenticated(false))
    haRespond(401, array("error" => "Nicht angemeldet"));

$action = isset($_GET["action"]) ? (string)$_GET["action"] : "";
$method = $_SERVER["REQUEST_METHOD"] ?? "GET";

try {
    if ($method === "GET") {
        switch ($action) {
            case "state":
                haRespond(200, HaStorage::getState());
            case "locations":
                haRespond(200, HaStorage::getLocations());
            case "picture":
                $picture = HaStorage::getPicture(checkIfNumeric((string)($_GET["product"] ?? "")));
                if ($picture === null) {
                    http_response_code(404);
                    exit;
                }
                header("Content-Type: " . $picture[0]);
                header("Cache-Control: private, max-age=3600");
                echo $picture[1];
                exit;
        }
        haRespond(400, array("error" => "Unbekannte Aktion"));
    }

    if ($method !== "POST")
        haRespond(405, array("error" => "Methode nicht erlaubt"));
    if (stripos($_SERVER["CONTENT_TYPE"] ?? "", "application/json") !== 0)
        haRespond(415, array("error" => "JSON erwartet"));
    $input = json_decode((string)file_get_contents("php://input"), true);
    if (!is_array($input))
        $input = array();

    switch ($action) {
        case "start":
            $location = HaStorage::start((int)($input["location_id"] ?? 0));
            // In den Kauf-Modus wechseln (wie der Kauf-Barcode, inkl. Anzeige auf der Screen-Seite)
            processNewBarcode(BBConfig::getInstance()["BARCODE_P"]);
            haRespond(200, array("location" => $location));
        case "stop":
            HaStorage::stop();
            haRespond(200, array("ok" => true));
        case "bestbefore":
            $date = $input["date"] ?? null;
            if ($date !== null && !is_string($date))
                haRespond(400, array("error" => "Ungültiges Datum"));
            haRespond(200, HaStorage::setBestBefore((string)($input["id"] ?? ""), $date));
        case "quantity":
            if (!isset($input["amount"]) || !is_numeric($input["amount"]))
                haRespond(400, array("error" => "Ungültige Menge"));
            haRespond(200, HaStorage::setLastPurchaseAmount((float)$input["amount"]));
    }
    haRespond(400, array("error" => "Unbekannte Aktion"));
} catch (InvalidArgumentException $e) {
    haRespond(400, array("error" => $e->getMessage()));
} catch (Throwable $e) {
    haRespond(500, array("error" => $e->getMessage()));
}
