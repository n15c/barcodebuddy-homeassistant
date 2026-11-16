<?php
/**
 * Home-Assistant-Add-on-Erweiterung fuer Barcode Buddy: Einlagerungsmodus.
 *
 * Auf der Screen-Seite wird ein Lagerort gewaehlt (z.B. Kuehlschrank). Solange die Einlagerung
 * laeuft, werden alle Einkaeufe an diesem Lagerort gebucht und jedes eingebuchte Produkt landet
 * in einer Warteschlange, damit die Screen-Seite das Mindesthaltbarkeitsdatum abfragen kann.
 * Die Antwort aendert die eben angelegten Bestandseintraege in Grocy.
 *
 * Wird nichts eingegeben, bleibt das von Grocy/BarcodeBuddy gesetzte Standard-Datum.
 */

require_once __DIR__ . "/haAutoCreate.inc.php";

class HaStorage {

    public static $stateFile = __DIR__ . "/../data/ha_storage.json";

    /** Einlagerung endet automatisch nach so langer Inaktivitaet */
    const SESSION_TIMEOUT_S = 7200;
    /** So kennzeichnet Grocy "laeuft nicht ab" */
    const NEVER_EXPIRES = "2999-12-31";
    const MAX_PENDING   = 50;
    const MAX_DONE      = 10;

    // ---------------------------------------------------------------- Zustand

    /**
     * Fuehrt $fn mit dem Zustand unter exklusiver Dateisperre aus und speichert ihn danach.
     * Der Zustand wird von Webseite und Scanner-Prozess gleichzeitig benutzt.
     */
    private static function withState(callable $fn) {
        $handle = @fopen(self::$stateFile, "c+");
        if ($handle === false)
            throw new RuntimeException("Zustandsdatei " . self::$stateFile . " ist nicht beschreibbar");
        try {
            flock($handle, LOCK_EX);
            $state = json_decode((string)stream_get_contents($handle), true);
            if (!is_array($state))
                $state = array();
            $state += array("session" => null, "pending" => array(), "last_date" => null, "done" => array());

            if ($state["session"] !== null && time() - (int)($state["session"]["last_activity"] ?? 0) > self::SESSION_TIMEOUT_S) {
                $state["session"] = null;
                $state["pending"] = array();
            }

            $result = $fn($state);

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
            fflush($handle);
            return $result;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @return array|null Laufende Einlagerung (location_id, location_name, ...) oder null
     */
    public static function activeSession(): ?array {
        try {
            return self::withState(function (array &$state) {
                return $state["session"];
            });
        } catch (Throwable $e) {
            return null;
        }
    }

    /** Zustand fuer die Screen-Seite */
    public static function getState(): array {
        return self::withState(function (array &$state) {
            return array(
                "session"   => $state["session"],
                "pending"   => array_values($state["pending"]),
                "last_date" => $state["last_date"],
                "done"      => $state["done"],
                "lang"      => HaAutoCreate::language(),
            );
        });
    }

    public static function getLocations(): array {
        $locations = array();
        foreach (HaAutoCreate::grocyGet("objects/locations") as $location) {
            $locations[] = array("id" => (int)$location["id"], "name" => (string)$location["name"]);
        }
        usort($locations, function ($a, $b) {
            return strcasecmp($a["name"], $b["name"]);
        });
        return $locations;
    }

    public static function start(int $locationId): array {
        $location = null;
        foreach (self::getLocations() as $entry) {
            if ($entry["id"] === $locationId)
                $location = $entry;
        }
        if ($location === null)
            throw new InvalidArgumentException("Lagerort $locationId gibt es in Grocy nicht");

        self::withState(function (array &$state) use ($location) {
            $state["session"] = array(
                "location_id"   => $location["id"],
                "location_name" => $location["name"],
                "started"       => time(),
                "last_activity" => time(),
            );
            $state["pending"] = array();
            $state["done"]    = array();
        });
        return $location;
    }

    public static function stop(): void {
        self::withState(function (array &$state) {
            $state["session"] = null;
            $state["pending"] = array();
        });
    }

    // ---------------------------------------------------------------- Hooks in API::purchaseProduct()

    /** Vor dem Einkauf: Lagerort der laufenden Einlagerung setzen */
    public static function beforePurchase(int $productId, array $data): array {
        $session = self::activeSession();
        if ($session !== null)
            $data["location_id"] = (int)$session["location_id"];
        return $data;
    }

    /** Nach dem Einkauf: Bestandseintraege fuer die MHD-Abfrage vormerken */
    public static function afterPurchase(int $productId, $response): void {
        try {
            if (self::activeSession() === null)
                return;
            $rows = is_string($response) ? json_decode($response, true) : null;
            if (!is_array($rows))
                return;

            $stockIds   = array();
            $amount     = 0.0;
            $bestBefore = null;
            foreach ($rows as $row) {
                if (!is_array($row) || empty($row["stock_id"]))
                    continue;
                $stockIds[] = (string)$row["stock_id"];
                $amount     += (float)($row["amount"] ?? 0);
                if ($bestBefore === null && !empty($row["best_before_date"]))
                    $bestBefore = (string)$row["best_before_date"];
            }
            if (count($stockIds) == 0)
                return;

            $product = HaAutoCreate::grocyRequest("GET", "objects/products/" . $productId);
            $item    = array(
                "id"               => bin2hex(random_bytes(8)),
                "product_id"       => $productId,
                "name"             => is_array($product) ? (string)$product["name"] : "#$productId",
                "has_picture"      => is_array($product) && !empty($product["picture_file_name"]),
                "amount"           => $amount,
                "stock_ids"        => array_values(array_unique($stockIds)),
                "best_before_date" => $bestBefore,
                "created"          => time(),
            );

            self::withState(function (array &$state) use ($item) {
                if ($state["session"] === null)
                    return;
                $state["pending"][] = $item;
                if (count($state["pending"]) > self::MAX_PENDING)
                    $state["pending"] = array_slice($state["pending"], -self::MAX_PENDING);
                $state["session"]["last_activity"] = time();
            });
        } catch (Throwable $e) {
            // Der Einkauf selbst ist gebucht; ohne Abfrage bleibt das Standard-MHD
            $log = new LogOutput("Einlagerung: MHD-Abfrage konnte nicht vorgemerkt werden: " . $e->getMessage(), EVENT_TYPE_ERROR);
            $log->dontSendWebsocket()->createLog();
        }
    }

    // ---------------------------------------------------------------- MHD setzen

    /**
     * Setzt das Mindesthaltbarkeitsdatum fuer einen vorgemerkten Einkauf.
     * @param string|null $date YYYY-MM-DD, "never" oder null (ueberspringen = Standard behalten)
     */
    public static function setBestBefore(string $pendingId, ?string $date): array {
        $item = self::withState(function (array &$state) use ($pendingId) {
            foreach ($state["pending"] as $item) {
                if ($item["id"] === $pendingId)
                    return $item;
            }
            return null;
        });
        if ($item === null)
            throw new InvalidArgumentException("Eintrag nicht (mehr) vorhanden");

        if ($date !== null) {
            if ($date === "never")
                $date = self::NEVER_EXPIRES;
            if (!self::isValidDate($date))
                throw new InvalidArgumentException("Ungültiges Datum");
            self::updateStockEntries((int)$item["product_id"], $item["stock_ids"], $date);
        }

        self::withState(function (array &$state) use ($pendingId, $item, $date) {
            $state["pending"] = array_values(array_filter($state["pending"], function ($entry) use ($pendingId) {
                return $entry["id"] !== $pendingId;
            }));
            if ($date !== null) {
                if ($date !== self::NEVER_EXPIRES)
                    $state["last_date"] = $date;
                array_unshift($state["done"], array("name" => $item["name"], "date" => $date));
                $state["done"] = array_slice($state["done"], 0, self::MAX_DONE);
            }
            if ($state["session"] !== null)
                $state["session"]["last_activity"] = time();
        });
        return array("name" => $item["name"], "date" => $date);
    }

    private static function isValidDate(string $date): bool {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m))
            return false;
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
    }

    /** Grocy ueberschreibt beim Bearbeiten alle Felder, daher die aktuellen Werte mitsenden */
    private static function updateStockEntries(int $productId, array $stockIds, string $date): void {
        $entries = HaAutoCreate::grocyGet("stock/products/" . $productId . "/entries");
        $found   = 0;
        foreach ($entries as $entry) {
            if (!in_array((string)$entry["stock_id"], $stockIds, true))
                continue;
            $found++;
            HaAutoCreate::grocyRequest("PUT", "stock/entry/" . (int)$entry["id"], array(
                "amount"               => $entry["amount"],
                "best_before_date"     => $date,
                "location_id"          => $entry["location_id"],
                "shopping_location_id" => $entry["shopping_location_id"] ?? null,
                "price"                => $entry["price"] ?? null,
                "open"                 => !empty($entry["open"]),
                "purchased_date"       => $entry["purchased_date"],
                "note"                 => $entry["note"] ?? null,
            ));
        }
        if ($found == 0)
            throw new RuntimeException("Bestandseintrag in Grocy nicht gefunden (bereits verbraucht?)");
    }

    // ---------------------------------------------------------------- Produktbild

    /** @return array|null [Mime-Type, Bilddaten] */
    public static function getPicture(int $productId): ?array {
        $product = HaAutoCreate::grocyRequest("GET", "objects/products/" . $productId);
        if (!is_array($product) || empty($product["picture_file_name"]))
            return null;
        $config = BBConfig::getInstance();
        $url    = $config["GROCY_API_URL"] . "files/productpictures/" . base64_encode($product["picture_file_name"])
            . "?force_serve_as=picture&best_fit_width=400&best_fit_height=400";
        list($status, $data) = HaAutoCreate::http("GET", $url, null, array("GROCY-API-KEY: " . $config["GROCY_API_KEY"]), true);
        if ($status != 200 || $data === "")
            return null;
        $info = @getimagesizefromstring($data);
        if ($info === false)
            return null;
        return array($info["mime"], $data);
    }
}
