<?php
/**
 * Home-Assistant-Add-on: Deutsche Oberflaeche fuer Barcode Buddy.
 *
 * Uebersetzt die fertige HTML-Ausgabe der Weboberflaeche (WebUiGenerator::printHtml), statt jede
 * Zeichenkette im Originalcode anzufassen. So bleibt der Code nah am Original und neue
 * Barcode-Buddy-Versionen lassen sich weiter einfach uebernehmen.
 *
 * Ersetzt werden nur Textknoten, die als Ganzes (ohne umgebende Leerzeichen) im Woerterbuch stehen,
 * sowie die Attribute placeholder, title und aria-label. Skripte, Styles, Textfelder und
 * Auswahllisten (Produktnamen) bleiben unveraendert. Unbekannte Texte bleiben englisch.
 *
 * Die Sprache kommt aus der Add-on-Option "product_language".
 */

require_once __DIR__ . "/haAutoCreate.inc.php";

class HaI18n {

    const DE = array(
        // Navigation und Kopfzeile
        "Overview"                    => "Übersicht",
        "Settings"                    => "Einstellungen",
        "Quantities"                  => "Mengen",
        "Chores"                      => "Aufgaben",
        "Tags"                        => "Tags",
        "API"                         => "API",
        "Admin"                       => "Verwaltung",
        "Screen"                      => "Screen",
        "Documentation"               => "Dokumentation",
        "Source Code (Home Assistant Add-on)" => "Quellcode (Home-Assistant-Add-on)",
        "License (AGPL-3.0)"          => "Lizenz (AGPL-3.0)",
        "by Marc Ole Bulling"         => "von Marc Ole Bulling",

        // Uebersicht
        "New Barcodes"                => "Neue Barcodes",
        "Unknown Barcodes"            => "Unbekannte Barcodes",
        "Processed Barcodes"          => "Verarbeitete Barcodes",
        "Action required"             => "Aktion erforderlich",
        "No known barcodes yet."      => "Noch keine neuen Barcodes.",
        "No unknown barcodes yet."    => "Keine unbekannten Barcodes.",
        "No processed items yet."     => "Noch nichts verarbeitet.",
        "Barcode"                     => "Barcode",
        "Name"                        => "Name",
        "Look up"                     => "Suchen",
        "Search for barcode"          => "Barcode suchen",
        "Quantity"                    => "Menge",
        "Product"                     => "Produkt",
        "Action"                      => "Aktion",
        "Create"                      => "Anlegen",
        "Remove"                      => "Entfernen",
        "Add"                         => "Kaufen",
        "Consume"                     => "Verbrauchen",
        "Create Product"              => "Produkt anlegen",
        "Clear log"                   => "Protokoll leeren",
        "Delete all"                  => "Alle löschen",
        "Add barcode"                 => "Barcode hinzufügen",
        "Enter your barcodes below, one each line." => "Barcodes eingeben, einen pro Zeile.",
        "Current Weight"              => "Aktuelles Gewicht",
        "= None ="                    => "= Keins =",
        "Barcode will be deleted"     => "Barcode wird gelöscht",

        // Einstellungen
        "General Settings"            => "Allgemeine Einstellungen",
        "Barcode: Consume"            => "Barcode: Verbrauchen",
        "Barcode: Consume (spoiled)"  => "Barcode: Verdorben",
        "Barcode: Consume all"        => "Barcode: Alles verbrauchen",
        "Barcode: Purchase"           => "Barcode: Kaufen",
        "Barcode: Open"               => "Barcode: Öffnen",
        "Barcode: Inventory"          => "Barcode: Bestand",
        "Barcode: Quantity"           => "Barcode: Menge",
        "Barcode: Add to shopping list" => "Barcode: Einkaufsliste",
        "Revert state to \"Consume\" after time passed in minutes" => "Nach so vielen Minuten zurück zu „Verbrauchen“",
        "Revert after single item scan in \"Open\" or \"Spoiled\" mode" => "Nach einem Scan in „Öffnen“ oder „Verdorben“ zurückwechseln",
        "Remove purchased items from shoppinglist" => "Gekaufte Artikel von der Einkaufsliste entfernen",
        "Consume amount of quantity saved for barcode" => "Beim Verbrauchen die gespeicherte Menge des Barcodes verwenden",
        "Use Grocys quantity conversion" => "Mengenumrechnung von Grocy verwenden",
        "Show Screen module in fullscreen" => "Screen-Seite im Vollbild anzeigen",
        "Use generic names for lookup" => "Allgemeine Produktnamen für die Suche verwenden",
        "Show stock amount on scan"   => "Bestand beim Scannen anzeigen",
        "Save name from lookup to barcode" => "Gefundenen Namen beim Barcode speichern",
        "Enable GS1/Datamatrix parsing" => "GS1-/Datamatrix-Codes auswerten",
        "More verbose logs"           => "Ausführlicheres Protokoll",
        "Hint: You can find picture files of the default barcodes in the \"example\" folder or" => "Tipp: Die Standard-Barcodes zum Ausdrucken gibt es im Ordner „example“ oder",
        "online"                      => "online",
        "Barcode Lookup Providers"    => "Quellen für die Barcode-Suche",
        "Use Drag&Drop for changing lookup order" => "Reihenfolge per Ziehen und Ablegen ändern",
        "Uses OpenFoodFacts.org"      => "Nutzt OpenFoodFacts.org",
        "Uses UPCitemDB.com"          => "Nutzt UPCitemDB.com",
        "Uses UPCDatabase.org"        => "Nutzt UPCDatabase.org",
        "Uses AH.nl"                  => "Nutzt AH.nl",
        "Uses PLUS.nl"                => "Nutzt PLUS.nl",
        "Uses Jumbo.com (slow)"       => "Nutzt Jumbo.com (langsam)",
        "Uses OpenGtinDb.org"         => "Nutzt OpenGtinDb.org",
        "Uses Discogs.com"            => "Nutzt Discogs.com",
        "Grocy API"                   => "Grocy-API",
        "Grocy API URL"               => "Grocy-API-Adresse",
        "Grocy API Key"               => "Grocy-API-Schlüssel",
        "Successfully connected to Grocy, valid API key." => "Verbindung zu Grocy steht, API-Schlüssel gültig.",
        "Redis Cache"                 => "Redis-Zwischenspeicher",
        "Use Redis cache"             => "Redis-Zwischenspeicher verwenden",
        "Redis Server IP"             => "Redis-Server-IP",
        "Redis Server Port"           => "Redis-Server-Port",
        "Redis Password"              => "Redis-Passwort",
        "Update Cache"                => "Zwischenspeicher aktualisieren",
        "Websocket Server Status"     => "Status des Websocket-Servers",
        "Websocket server is running." => "Der Websocket-Server läuft.",
        "Save"                        => "Speichern",
        "leave blank if none set"     => "leer lassen, wenn keins gesetzt",
        "e.g. 127.0.0.1"              => "z.B. 127.0.0.1",
        "e.g. 6379"                   => "z.B. 6379",
        "e.g. https://your.grocy.com/api/" => "z.B. https://grocy.example.com/api/",

        // Mengen, Aufgaben, Tags
        "Saved Quantities"            => "Gespeicherte Mengen",
        "No saved quantities yet."    => "Noch keine gespeicherten Mengen.",
        "Chore"                       => "Aufgabe",
        "No chores yet."              => "Noch keine Aufgaben.",
        "Tag"                         => "Tag",
        "Active Tags"                 => "Aktive Tags",
        "Inactive Tags"               => "Inaktive Tags",
        "No tags stored."             => "Keine Tags gespeichert.",
        "Edit"                        => "Bearbeiten",
        "Delete"                      => "Löschen",
        "Enter new barcode"           => "Neuen Barcode eingeben",

        // API
        "API Keys"                    => "API-Schlüssel",
        "API Key"                     => "API-Schlüssel",
        "Last Used"                   => "Zuletzt verwendet",
        "New API key"                 => "Neuer API-Schlüssel",
        "Revoke"                      => "Widerrufen",
        "Revoke all"                  => "Alle widerrufen",
        "Add mobile app"              => "Mobile App hinzufügen",
        "Show QR Code"                => "QR-Code anzeigen",
        "QR Code"                     => "QR-Code",
        "Management of API keys. For more information about the Barcode Buddy API, click" => "Verwaltung der API-Schlüssel. Mehr zur Barcode-Buddy-API gibt es",
        "here"                        => "hier",

        // Verwaltung, Anmeldung, Einrichtung
        "Download Database"           => "Datenbank herunterladen",
        "Change Password"             => "Passwort ändern",
        "Logout"                      => "Abmelden",
        "Login"                       => "Anmelden",
        "Create User"                 => "Benutzer anlegen",
        "Forgot Password"             => "Passwort vergessen",
        "Setup"                       => "Einrichtung",
        "Submit"                      => "Absenden",
        "Go back"                     => "Zurück",
        "Cancel"                      => "Abbrechen",
        "Confirm"                     => "Bestätigen",
        "Refresh"                     => "Aktualisieren",
        "Welcome to Barcode Buddy! Please enter your Grocy API details below. For more information, please visit the" => "Willkommen bei Barcode Buddy! Bitte unten die Grocy-API-Daten eintragen. Mehr dazu in der",
        "documentation"               => "Dokumentation",
        "It is recommended to use a script that grabs the barcode scanner input, instead of doing it manually. See the" => "Am besten liest ein Skript den Barcodescanner direkt aus, statt Barcodes von Hand einzugeben. Siehe",
        "on how to do this."          => "für die Einrichtung.",
    );

    public static function isGerman(): bool {
        return HaAutoCreate::language() === "de";
    }

    /** Uebersetzt eine fertige HTML-Seite, wenn Deutsch eingestellt ist */
    public static function translatePage(string $html): string {
        if (!self::isGerman())
            return $html;

        // Bereiche, die nicht angefasst werden duerfen, voruebergehend herausnehmen
        $protected = array();
        $html      = preg_replace_callback('#<(script|style|textarea|select)\b.*?</\1>#is', function ($m) use (&$protected) {
            $key             = "\x00" . count($protected) . "\x00";
            $protected[$key] = $m[0];
            return $key;
        }, $html);

        // Textknoten
        // (Text kann auch direkt hinter einem herausgenommenen Skript stehen; fuehrende/abschliessende
        // Leerzeichen inkl. &nbsp; bleiben erhalten)
        $space = '(?:\s|&nbsp;|&#160;|\x{00A0})*';
        $html  = preg_replace_callback('/(?<=>|\x00)([^<>\x00]+)(?=<|\x00)/u', function ($m) use ($space) {
            if (!preg_match('/^(' . $space . ')(.*?)(' . $space . ')$/us', $m[1], $parts))
                return $m[0];
            $key = html_entity_decode($parts[2], ENT_QUOTES | ENT_HTML5, "UTF-8");
            if ($key === "" || !isset(self::DE[$key]))
                return $m[0];
            return $parts[1] . htmlspecialchars(self::DE[$key], ENT_QUOTES, "UTF-8") . $parts[3];
        }, $html);

        // Attribute fuer Hinweise
        $html = preg_replace_callback('/\b(placeholder|title|aria-label)="([^"]*)"/u', function ($m) {
            $value = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, "UTF-8");
            if (!isset(self::DE[$value]))
                return $m[0];
            return $m[1] . '="' . htmlspecialchars(self::DE[$value], ENT_QUOTES, "UTF-8") . '"';
        }, $html);

        $html = preg_replace('/<html lang="en">/', '<html lang="de">', $html, 1);
        return strtr($html, $protected);
    }
}
