<?php
/**
 * Home-Assistant-Add-on-Erweiterung fuer Barcode Buddy:
 * Unbekannte Barcodes werden bei Open Food Facts nachgeschlagen und als Produkt in Grocy
 * angelegt (Name, Bild, Barcode, Beschreibung mit Naehrwerten/Zutaten/Allergenen, Energie,
 * Produktgruppe). Mengeneinheit und Lagerort werden aus den Add-on-Optionen bzw. den
 * Grocy-Voreinstellungen fuer neue Produkte gesetzt und koennen spaeter in Grocy angepasst werden.
 *
 * Danach wird der Barcode wie ein bekannter Barcode verarbeitet (z.B. Einkauf gebucht).
 * Schlaegt etwas fehl, laeuft der normale Ablauf fuer unbekannte Barcodes weiter.
 */

require_once __DIR__ . "/api.inc.php";

class HaAutoCreate {

    /** Wird vom Add-on-Start aus /data/options.json kopiert */
    public static $optionsFile = "/etc/bbuddy-ha/options.json";
    public static $offBaseUrl = "https://world.openfoodfacts.org";
    /** Bilder nur von Open-Food-Facts-Servern laden (keine beliebigen Adressen aus den Produktdaten) */
    public static $imageUrlPattern = '#^https://([a-z0-9-]+\.)*openfoodfacts\.(org|net)/#i';

    const USER_AGENT     = "BarcodeBuddy-HomeAssistant-Addon/1.0 (https://github.com/n15c/barcodebuddy-homeassistant)";
    const MAX_IMAGE_SIZE = 8 * 1024 * 1024;
    const MAX_NAME_LEN   = 150;

    private static $options = null;

    /**
     * Wird am Anfang von processUnknownBarcode() aufgerufen.
     * @return string|null Log-Ausgabe, wenn das Produkt angelegt und verarbeitet wurde, sonst null
     */
    public static function tryCreate(string $barcode, bool $websocketEnabled, LockGenerator &$fileLock, ?string $bestBeforeInDays, ?string $price): ?string {
        if (!self::isEnabled() || !preg_match('/^\d{8,14}$/', $barcode))
            return null;
        $db = DatabaseConnection::getInstance();
        // Bereits als unbekannt gespeicherte Barcodes (mit evtl. gesammelten Mengen) nicht anfassen
        if ($db->isUnknownBarcodeAlreadyStored($barcode))
            return null;

        try {
            $off = self::lookupOff($barcode);
            if ($off === null)
                return null;
            $created = self::createInGrocy($barcode, $off);
        } catch (Throwable $e) {
            $log = new LogOutput("Automatisches Anlegen aus Open Food Facts fehlgeschlagen: " . $e->getMessage(), EVENT_TYPE_ERROR, $barcode);
            $log->dontSendWebsocket()->createLog();
            return null;
        }

        RedisConnection::expireAllBarcodes();
        RedisConnection::expireAllProductInfo();

        $text = self::isGerman()
            ? "Neues Produkt aus Open Food Facts angelegt: %s (%s, %s – bitte Mengeneinheit und Lagerort in Grocy prüfen)"
            : "New product created from Open Food Facts: %s (%s, %s – please check quantity unit and location in Grocy)";
        $log  = new LogOutput(sprintf($text, $created["name"], $created["location"], $created["qu"]), EVENT_TYPE_ASSOCIATE_PRODUCT, $barcode);
        $createdText = $log->dontSendWebsocket()->createLog();

        $productInfo = API::getProductInfo($created["id"]);
        if ($productInfo === null) {
            $fileLock->removeLock();
            return $createdText;
        }
        $db->saveLastBarcode($barcode, $productInfo->name);
        return $createdText . "\n" . processKnownBarcode($productInfo, $barcode, $websocketEnabled, $fileLock, $bestBeforeInDays, $price);
    }

    // ---------------------------------------------------------------- Optionen

    /** Nur fuer Tests: Pfade/Adressen per Umgebungsvariable umlenken (php-fpm reicht keine Umgebung durch) */
    private static function applyTestOverrides(): void {
        if (getenv("BBUDDY_HA_OPTIONS_FILE"))
            self::$optionsFile = getenv("BBUDDY_HA_OPTIONS_FILE");
        if (getenv("BBUDDY_HA_OFF_URL"))
            self::$offBaseUrl = getenv("BBUDDY_HA_OFF_URL");
        if (getenv("BBUDDY_HA_IMAGE_PATTERN"))
            self::$imageUrlPattern = getenv("BBUDDY_HA_IMAGE_PATTERN");
    }

    private static function options(): array {
        if (self::$options === null) {
            self::applyTestOverrides();
            self::$options = array();
            $raw = @file_get_contents(self::$optionsFile);
            if ($raw !== false) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded))
                    self::$options = $decoded;
            }
        }
        return self::$options;
    }

    private static function option(string $key, $default = null) {
        $options = self::options();
        return (isset($options[$key]) && $options[$key] !== "") ? $options[$key] : $default;
    }

    public static function isEnabled(): bool {
        return self::option("auto_create_products", false) === true;
    }

    public static function language(): string {
        $lang = strtolower((string)self::option("product_language", "de"));
        return preg_match('/^[a-z]{2}$/', $lang) ? $lang : "de";
    }

    private static function isGerman(): bool {
        return self::language() === "de";
    }

    // ---------------------------------------------------------------- Open Food Facts

    /**
     * @return array|null Produktdaten von Open Food Facts oder null, wenn nicht gefunden
     */
    private static function lookupOff(string $barcode): ?array {
        $candidates = array($barcode);
        if (strlen($barcode) == 14 && $barcode[0] === "0")
            $candidates[] = substr($barcode, 1);

        $lang   = self::language();
        $fields = array("code", "product_name", "product_name_$lang", "generic_name", "generic_name_$lang",
            "brands", "quantity", "product_quantity", "product_quantity_unit", "serving_size",
            "image_front_url", "image_url", "ingredients_text", "ingredients_text_$lang",
            "allergens_tags", "traces_tags", "nutriscore_grade", "nova_group", "nutriments",
            "categories", "categories_tags");

        foreach ($candidates as $code) {
            $url = rtrim(self::$offBaseUrl, "/") . "/api/v2/product/" . $code . "?lc=" . $lang . "&fields=" . implode(",", $fields);
            list($status, $body) = self::http("GET", $url, null, array(), false);
            if ($status == 404)
                continue;
            if ($status != 200)
                throw new RuntimeException("Open Food Facts antwortete mit HTTP $status");
            $data = json_decode($body, true);
            if (!is_array($data) || ($data["status"] ?? 0) != 1 || !isset($data["product"]) || !is_array($data["product"]))
                continue;
            $product = $data["product"];
            if (self::productName($product) === null)
                continue;
            if (empty($product["code"]))
                $product["code"] = $code;
            return $product;
        }
        return null;
    }

    private static function text($value): ?string {
        if (!is_string($value))
            return null;
        $value = mb_convert_encoding($value, "UTF-8", "UTF-8");
        $value = trim((string)preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, "UTF-8"))));
        return $value === "" ? null : $value;
    }

    private static function localized(array $p, string $field): ?string {
        return self::text($p[$field . "_" . self::language()] ?? null) ?? self::text($p[$field] ?? null);
    }

    private static function productName(array $p): ?string {
        $name = self::localized($p, "product_name") ?? self::localized($p, "generic_name");
        if ($name === null)
            return null;
        return mb_substr($name, 0, self::MAX_NAME_LEN);
    }

    private static function brands(array $p): array {
        $brands = array();
        foreach (explode(",", (string)($p["brands"] ?? "")) as $brand) {
            $brand = self::text($brand);
            if ($brand !== null)
                $brands[] = $brand;
        }
        return $brands;
    }

    private static function nutriment(array $p, string $key): ?float {
        $value = $p["nutriments"][$key] ?? null;
        return is_numeric($value) ? (float)$value : null;
    }

    private static function kcalPer100(array $p): ?float {
        $kcal = self::nutriment($p, "energy-kcal_100g");
        if ($kcal === null) {
            $kj = self::nutriment($p, "energy-kj_100g") ?? self::nutriment($p, "energy_100g");
            if ($kj !== null)
                $kcal = $kj / 4.184;
        }
        return $kcal;
    }

    /** Packungsinhalt in g bzw. ml (OFF normalisiert product_quantity darauf) */
    private static function packageAmount(array $p): ?float {
        $qty  = $p["product_quantity"] ?? null;
        $unit = strtolower((string)($p["product_quantity_unit"] ?? "g"));
        if (!is_numeric($qty) || (float)$qty <= 0 || !in_array($unit, array("g", "ml")))
            return null;
        return (float)$qty;
    }

    // ---------------------------------------------------------------- Grocy

    /**
     * Legt Produkt, Barcode und Bild in Grocy an.
     * @return array id, name, location, qu
     */
    private static function createInGrocy(string $barcode, array $off): array {
        $settings  = self::grocyGet("user/settings");
        $locations = self::grocyGet("objects/locations");
        $qus       = self::grocyGet("objects/quantity_units");
        $groups    = self::grocyGet("objects/product_groups");
        $products  = self::grocyGet("objects/products");

        $location = null;
        require_once __DIR__ . "/haStorage.inc.php";
        if (($session = HaStorage::activeSession()) !== null) {
            // Laufende Einlagerung: deren Lagerort auch als Standard-Lagerort des Produkts nehmen
            foreach ($locations as $entity) {
                if ((int)$entity["id"] === (int)$session["location_id"])
                    $location = $entity;
            }
        }
        if ($location === null)
            $location = self::pickEntity($locations, self::option("auto_create_location"), $settings["product_presets_location_id"] ?? -1, "Lagerort");
        $qu       = self::pickEntity($qus, self::option("auto_create_quantity_unit"), $settings["product_presets_qu_id"] ?? -1, "Mengeneinheit");
        if ($location === null)
            throw new RuntimeException("In Grocy ist kein Lagerort vorhanden");
        if ($qu === null)
            throw new RuntimeException("In Grocy ist keine Mengeneinheit vorhanden");

        $name = self::uniqueName(self::productName($off), $off, $barcode, $products);

        $product = array(
            "name"           => $name,
            "description"    => self::buildDescription($off),
            "location_id"    => (int)$location["id"],
            "qu_id_purchase" => (int)$qu["id"],
            "qu_id_stock"    => (int)$qu["id"],
            "qu_id_consume"  => (int)$qu["id"],
            "qu_id_price"    => (int)$qu["id"],
        );

        $group = self::matchProductGroup($groups, $off);
        if ($group === null && isset($settings["product_presets_product_group_id"]) && (int)$settings["product_presets_product_group_id"] > 0)
            $group = (int)$settings["product_presets_product_group_id"];
        if ($group !== null)
            $product["product_group_id"] = $group;

        if (isset($settings["product_presets_default_due_days"]) && is_numeric($settings["product_presets_default_due_days"]))
            $product["default_best_before_days"] = (int)$settings["product_presets_default_due_days"];
        if (isset($settings["product_presets_treat_opened_as_out_of_stock"]))
            $product["treat_opened_as_out_of_stock"] = $settings["product_presets_treat_opened_as_out_of_stock"] ? 1 : 0;

        // Energie pro Packung (= pro Bestandsmengeneinheit, solange diese "Stück/Packung" ist)
        $kcal = self::kcalPer100($off);
        $pack = self::packageAmount($off);
        if ($kcal !== null && $pack !== null)
            $product["calories"] = (int)round($kcal * $pack / 100);

        $result = self::grocyRequest("POST", "objects/products", $product);
        if (!isset($result["created_object_id"]))
            throw new RuntimeException("Grocy hat keine Produkt-ID zurückgegeben");
        $productId = (int)$result["created_object_id"];

        try {
            self::grocyRequest("POST", "objects/product_barcodes", array(
                "product_id" => $productId,
                "barcode"    => $barcode,
                "note"       => "Open Food Facts"
            ));
        } catch (Throwable $e) {
            // Ohne Barcode wuerde beim naechsten Scan ein Duplikat entstehen -> Produkt wieder entfernen
            try {
                self::grocyRequest("DELETE", "objects/products/" . $productId);
            } catch (Throwable $ignored) {
            }
            throw $e;
        }

        // Bild ist optional: Fehler hier verhindern das Anlegen nicht
        try {
            $fileName = self::uploadPicture($barcode, $off);
            if ($fileName !== null)
                self::grocyRequest("PUT", "objects/products/" . $productId, array("picture_file_name" => $fileName));
        } catch (Throwable $e) {
            $log = new LogOutput("Produktbild konnte nicht übernommen werden: " . $e->getMessage(), EVENT_TYPE_ERROR, $barcode);
            $log->dontSendWebsocket()->createLog();
        }

        return array(
            "id"       => $productId,
            "name"     => $name,
            "location" => (string)$location["name"],
            "qu"       => (string)$qu["name"],
        );
    }

    /**
     * Waehlt einen Lagerort bzw. eine Mengeneinheit: Name aus den Add-on-Optionen,
     * sonst Grocy-Voreinstellung fuer neue Produkte, sonst den ersten Eintrag.
     */
    private static function pickEntity(array $entities, ?string $wantedName, $presetId, string $label): ?array {
        if (count($entities) == 0)
            return null;
        if ($wantedName !== null) {
            foreach ($entities as $entity) {
                foreach (array("name", "name_plural") as $field) {
                    if (isset($entity[$field]) && mb_strtolower(trim($entity[$field])) === mb_strtolower(trim($wantedName)))
                        return $entity;
                }
            }
            $log = new LogOutput("$label \"$wantedName\" gibt es in Grocy nicht, verwende Voreinstellung", EVENT_TYPE_ERROR);
            $log->dontSendWebsocket()->createLog();
        }
        if (is_numeric($presetId) && (int)$presetId > 0) {
            foreach ($entities as $entity) {
                if ((int)$entity["id"] === (int)$presetId)
                    return $entity;
            }
        }
        return $entities[0];
    }

    /** Grocy erlaubt jeden Produktnamen nur einmal */
    private static function uniqueName(string $name, array $off, string $barcode, array $products): string {
        $existing = array();
        foreach ($products as $product)
            $existing[mb_strtolower($product["name"])] = true;

        $candidates = array($name);
        $brands     = self::brands($off);
        if (count($brands) > 0 && mb_stripos($name, $brands[0]) === false)
            $candidates[] = $name . " (" . $brands[0] . ")";
        $candidates[] = $name . " (" . $barcode . ")";

        foreach ($candidates as $candidate) {
            if (!isset($existing[mb_strtolower($candidate)]))
                return $candidate;
        }
        return $name . " (" . $barcode . ", " . date("YmdHis") . ")";
    }

    /** Ordnet eine Grocy-Produktgruppe zu, wenn ihr Name einer OFF-Kategorie entspricht */
    private static function matchProductGroup(array $groups, array $off): ?int {
        if (count($groups) == 0)
            return null;
        $categories = array();
        foreach (explode(",", (string)($off["categories"] ?? "")) as $category)
            $categories[] = mb_strtolower(trim(preg_replace('/^[a-z]{2}:/', '', $category)));
        foreach (($off["categories_tags"] ?? array()) as $tag) {
            if (is_string($tag))
                $categories[] = mb_strtolower(str_replace("-", " ", preg_replace('/^[a-z]{2}:/', '', $tag)));
        }
        // Spezifischste Kategorie zuerst (OFF listet von allgemein nach speziell)
        foreach (array_reverse($categories) as $category) {
            if ($category === "")
                continue;
            foreach ($groups as $group) {
                if (mb_strtolower(trim($group["name"])) === $category)
                    return (int)$group["id"];
            }
        }
        return null;
    }

    private static function uploadPicture(string $barcode, array $off): ?string {
        $url = $off["image_front_url"] ?? ($off["image_url"] ?? null);
        if (!is_string($url) || !preg_match(self::$imageUrlPattern, $url))
            return null;
        list($status, $data) = self::http("GET", $url, null, array(), false);
        if ($status != 200 || $data === "" || strlen($data) > self::MAX_IMAGE_SIZE)
            return null;
        $info = @getimagesizefromstring($data);
        $extensions = array("image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp", "image/gif" => "gif");
        if ($info === false || !isset($extensions[$info["mime"]]))
            return null;

        $fileName = "bbuddy_" . $barcode . "_" . time() . "." . $extensions[$info["mime"]];
        self::grocyRequest("PUT", "files/productpictures/" . base64_encode($fileName), $data, "application/octet-stream");
        return $fileName;
    }

    // ---------------------------------------------------------------- Beschreibung

    private static function labels(): array {
        if (self::isGerman()) {
            return array(
                "brand" => "Marke", "quantity" => "Menge", "serving" => "Portion", "ingredients" => "Zutaten",
                "allergens" => "Allergene", "traces" => "Kann Spuren enthalten von", "nutrition" => "Nährwerte pro 100",
                "energy" => "Energie", "fat" => "Fett", "saturated" => "davon gesättigte Fettsäuren",
                "carbs" => "Kohlenhydrate", "sugars" => "davon Zucker", "fiber" => "Ballaststoffe",
                "protein" => "Eiweiß", "salt" => "Salz", "kcalPack" => "Energie pro Packung",
                "source" => "Automatisch aus %s angelegt (Daten unter ODbL-Lizenz). Bitte Mengeneinheit und Lagerort prüfen.",
            );
        }
        return array(
            "brand" => "Brand", "quantity" => "Quantity", "serving" => "Serving size", "ingredients" => "Ingredients",
            "allergens" => "Allergens", "traces" => "May contain traces of", "nutrition" => "Nutrition facts per 100",
            "energy" => "Energy", "fat" => "Fat", "saturated" => "of which saturates",
            "carbs" => "Carbohydrates", "sugars" => "of which sugars", "fiber" => "Fibre",
            "protein" => "Protein", "salt" => "Salt", "kcalPack" => "Energy per package",
            "source" => "Created automatically from %s (data licensed under ODbL). Please check quantity unit and location.",
        );
    }

    const ALLERGENS_DE = array(
        "gluten" => "Gluten", "milk" => "Milch", "eggs" => "Eier", "nuts" => "Schalenfrüchte",
        "peanuts" => "Erdnüsse", "soybeans" => "Soja", "fish" => "Fisch", "crustaceans" => "Krebstiere",
        "molluscs" => "Weichtiere", "celery" => "Sellerie", "mustard" => "Senf", "sesame-seeds" => "Sesam",
        "sulphur-dioxide-and-sulphites" => "Schwefeldioxid und Sulfite", "lupin" => "Lupinen",
    );

    private static function tagList($tags): ?string {
        if (!is_array($tags) || count($tags) == 0)
            return null;
        $names = array();
        foreach ($tags as $tag) {
            if (!is_string($tag))
                continue;
            $key = preg_replace('/^[a-z]{2}:/', '', $tag);
            if (self::isGerman() && isset(self::ALLERGENS_DE[$key]))
                $names[] = self::ALLERGENS_DE[$key];
            else
                $names[] = ucfirst(str_replace("-", " ", $key));
        }
        return count($names) ? implode(", ", array_unique($names)) : null;
    }

    private static function e(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    }

    private static function number(float $value, ?int $decimals = null): string {
        if ($decimals === null)
            $decimals = ($value > 0 && $value < 1) ? 2 : 1;
        $formatted = self::isGerman() ? number_format($value, $decimals, ",", ".") : number_format($value, $decimals, ".", ",");
        // Nullen am Ende entfernen (6,0 -> 6)
        $separator = self::isGerman() ? "," : ".";
        if ($decimals > 0)
            $formatted = rtrim(rtrim($formatted, "0"), $separator);
        return $formatted;
    }


    private static function buildDescription(array $off): string {
        $l    = self::labels();
        $html = "";

        $facts  = array();
        $brands = self::brands($off);
        if (count($brands))
            $facts[] = "<b>" . $l["brand"] . ":</b> " . self::e(implode(", ", $brands));
        if (($quantity = self::text($off["quantity"] ?? null)) !== null)
            $facts[] = "<b>" . $l["quantity"] . ":</b> " . self::e($quantity);
        if (($serving = self::text($off["serving_size"] ?? null)) !== null)
            $facts[] = "<b>" . $l["serving"] . ":</b> " . self::e($serving);
        $scores = array();
        if (!empty($off["nutriscore_grade"]) && preg_match('/^[a-e]$/', $off["nutriscore_grade"]))
            $scores[] = "<b>Nutri-Score:</b> " . strtoupper($off["nutriscore_grade"]);
        if (!empty($off["nova_group"]) && is_numeric($off["nova_group"]))
            $scores[] = "<b>NOVA:</b> " . (int)$off["nova_group"];
        if (count($scores))
            $facts[] = implode(" · ", $scores);
        if (count($facts))
            $html .= "<p>" . implode("<br>", $facts) . "</p>";

        if (($ingredients = self::localized($off, "ingredients_text")) !== null)
            $html .= "<p><b>" . $l["ingredients"] . ":</b> " . self::e($ingredients) . "</p>";

        $allergyInfo = array();
        if (($allergens = self::tagList($off["allergens_tags"] ?? null)) !== null)
            $allergyInfo[] = "<b>" . $l["allergens"] . ":</b> " . self::e($allergens);
        if (($traces = self::tagList($off["traces_tags"] ?? null)) !== null)
            $allergyInfo[] = "<b>" . $l["traces"] . ":</b> " . self::e($traces);
        if (count($allergyInfo))
            $html .= "<p>" . implode("<br>", $allergyInfo) . "</p>";

        $unit = strtolower((string)($off["product_quantity_unit"] ?? "g")) === "ml" ? "ml" : "g";
        $rows = array();
        $kcal = self::kcalPer100($off);
        if ($kcal !== null) {
            $kj     = self::nutriment($off, "energy-kj_100g") ?? $kcal * 4.184;
            $rows[] = array($l["energy"], self::number($kj, 0) . " kJ / " . self::number($kcal, 0) . " kcal");
        }
        foreach (array("fat" => "fat_100g", "saturated" => "saturated-fat_100g", "carbs" => "carbohydrates_100g",
                     "sugars" => "sugars_100g", "fiber" => "fiber_100g", "protein" => "proteins_100g", "salt" => "salt_100g") as $label => $key) {
            $value = self::nutriment($off, $key);
            if ($value !== null)
                $rows[] = array($l[$label], self::number($value) . " g");
        }
        if (count($rows)) {
            $html .= "<p><b>" . $l["nutrition"] . " " . $unit . "</b></p><table class=\"table table-sm\"><tbody>";
            foreach ($rows as $row)
                $html .= "<tr><td>" . self::e($row[0]) . "</td><td>" . self::e($row[1]) . "</td></tr>";
            $html .= "</tbody></table>";
            $pack = self::packageAmount($off);
            if ($kcal !== null && $pack !== null)
                $html .= "<p><b>" . $l["kcalPack"] . " (" . self::number($pack) . " " . $unit . "):</b> " . self::number($kcal * $pack / 100, 0) . " kcal</p>";
        }

        $link  = "<a href=\"https://world.openfoodfacts.org/product/" . self::e((string)$off["code"]) . "\" target=\"_blank\">Open Food Facts</a>";
        $html .= "<p><small>" . sprintf($l["source"], $link) . "</small></p>";
        return $html;
    }

    // ---------------------------------------------------------------- HTTP

    public static function grocyGet(string $path): array {
        $result = self::grocyRequest("GET", $path);
        return is_array($result) ? $result : array();
    }

    /**
     * @param array|string|null $body Array wird als JSON gesendet, String unveraendert
     * @return mixed Dekodierte JSON-Antwort
     */
    public static function grocyRequest(string $method, string $path, $body = null, string $contentType = "application/json") {
        $config  = BBConfig::getInstance();
        $headers = array("GROCY-API-KEY: " . $config["GROCY_API_KEY"], "Accept: application/json");
        if ($body !== null) {
            if (is_array($body))
                $body = json_encode($body, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
            $headers[] = "Content-Type: " . $contentType;
        }
        list($status, $response) = self::http($method, $config["GROCY_API_URL"] . $path, $body, $headers, true);
        $decoded = json_decode($response, true);
        if ($status < 200 || $status >= 300) {
            $message = (is_array($decoded) && isset($decoded["error_message"])) ? $decoded["error_message"] : "HTTP $status";
            throw new RuntimeException("Grocy ($method $path): " . $message);
        }
        return $decoded;
    }

    /**
     * @return array [HTTP-Status, Antwort]
     */
    public static function http(string $method, string $url, ?string $body, array $headers, bool $isGrocy): array {
        global $CONFIG;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, max(5, (int)$CONFIG->CURL_TIMEOUT_S));
        curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        if (!$isGrocy) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
            curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
        } else {
            if ($CONFIG->CURL_ALLOW_INSECURE_SSL_CA)
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            if ($CONFIG->CURL_ALLOW_INSECURE_SSL_HOST)
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        if ($body !== null)
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException(($isGrocy ? "Grocy" : "Open Food Facts") . " nicht erreichbar: " . $error);
        }
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return array($status, $response);
    }
}
