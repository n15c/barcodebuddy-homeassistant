<?php
/**
 * Home-Assistant-Add-on: Neu gestaltete Screen-Seite fuer Barcode Buddy (Touchscreen/Tablet).
 *
 * Ersetzt die Original-screen.php. Live-Anbindung (Server-Sent Events ueber incl/sse/sse_data.php),
 * Toene, Wake-Lock und Modus-Barcodes funktionieren wie im Original; dazu kommen grosse
 * Modus-Kacheln, Scan-Verlauf, Mengen-Tastatur, Dunkelmodus und der Einlagerungsmodus.
 */

require_once __DIR__ . "/incl/configProcessing.inc.php";
require_once __DIR__ . "/incl/config.inc.php";
require_once __DIR__ . "/incl/redis.inc.php";
require_once __DIR__ . "/incl/haAutoCreate.inc.php";

$CONFIG->checkIfAuthenticated(true);

$config = BBConfig::getInstance();
$lang   = HaAutoCreate::language() === "de" ? "de" : "en";

// Material-Icons (Apache License 2.0)
$icons = array(
    "purchase" => "M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49A1.003 1.003 0 0 0 20 4H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z",
    "consume"  => "M11 9H9V2H7v7H5V2H3v7c0 2.12 1.66 3.84 3.75 3.97V22h2.5v-9.03C11.34 12.84 13 11.12 13 9V2h-2v7zm5-3v8h2.5v8H21V2c-2.76 0-5 2.24-5 4z",
    "open"     => "M12 17c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm6-9h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6h1.9c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm0 12H6V10h12v10z",
    "stock"    => "M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z",
    "list"     => "M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm2 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z",
    "spoiled"  => "M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z",
    "all"      => "M18 7l-1.41-1.41-6.34 6.34 1.41 1.41L18 7zm4.24-1.41L11.66 16.17 7.48 12l-1.41 1.41L11.66 19l12-12-1.42-1.41zM.41 13.41L6 19l1.41-1.41L1.83 12 .41 13.41z",
    "quantity" => "M19 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4v2z",
    "store"    => "M18 2.01L6 2c-1.1 0-2 .89-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.11-.9-1.99-2-1.99zM18 20H6v-9.02h12V20zm0-11H6V4h12v5zM8 5h2v3H8zm0 7h2v5H8z",
    "backspace" => "M22 3H7c-.69 0-1.23.35-1.59.88L0 12l5.41 8.11c.36.53.9.89 1.59.89h15c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H7.07L2.4 12l4.66-7H22v14zm-11.59-2L14 13.41 17.59 17 19 15.59 15.41 12 19 8.41 17.59 7 14 10.59 10.41 7 9 8.41 12.59 12 9 15.59z",
    "soundOff" => "M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z",
    "soundOn"  => "M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z",
    "full"     => "M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z",
    "scan"     => "M9.5 6.5v3h-3v-3h3M11 5H5v6h6V5zm-1.5 9.5v3h-3v-3h3M11 13H5v6h6v-6zm6.5-6.5v3h-3v-3h3M19 5h-6v6h6V5zm-6 8h1.5v1.5H13V13zm1.5 1.5H16V16h-1.5v-1.5zM16 13h1.5v1.5H16V13zm-3 3h1.5v1.5H13V16zm1.5 1.5H16V19h-1.5v-1.5zM16 16h1.5v1.5H16V16zm1.5-1.5H19V16h-1.5v-1.5zm0 3H19V19h-1.5v-1.5zM22 7h-2V4h-3V2h5v5zm0 15v-5h-2v3h-3v2h5zM2 22h5v-2H4v-3H2v5zM2 2v5h2V4h3V2H2z",
    "ok"       => "M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z",
    "lookup"   => "M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z",
    "unknown"  => "M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z",
    "error"    => "M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z",
);

function haIcon(string $name, string $class = "icon"): string {
    global $icons;
    return '<svg class="' . $class . '" viewBox="0 0 24 24" aria-hidden="true"><path d="' . $icons[$name] . '"/></svg>';
}

$T = array(
    "de" => array(
        "title" => "Scanner", "ready" => "Bereit zum Scannen", "readyHint" => "Barcode scannen oder Modus wählen",
        "done" => "Erledigt", "lookedUp" => "Neuer Barcode – Name gefunden", "unknown" => "Unbekannter Barcode",
        "error" => "Verbindungsfehler", "history" => "Letzte Scans", "historyEmpty" => "Noch keine Scans",
        "connected" => "Verbunden", "connecting" => "Verbinde …", "unavailable" => "Nicht erreichbar",
        "noSse" => "Dieser Browser unterstützt keine Live-Anzeige (Server-Sent Events).",
        "sound" => "Ton & Bildschirm wach halten", "fullscreen" => "Vollbild", "delete" => "Löschen", "editProduct" => "Produkt bearbeiten",
        "busy" => "Wird verarbeitet …", "bbFollows" => "MHD-Abfrage folgt …", "noAnswer" => "Keine Rückmeldung erhalten – bitte im Protokoll nachsehen",
        "steps" => array("received" => "Barcode empfangen", "lookup" => "Suche bei Open Food Facts …",
            "create" => "Lege Produkt in Grocy an …", "picture" => "Lade Produktbild …"),
        "store" => "Einlagern …", "quantity" => "Menge",
        "qtyTitle" => "Menge korrigieren", "qtyFor" => "Zuletzt eingekauft: %s (bisher %s)", "qtyLoading" => "Lade letzten Einkauf …",
        "qtyNone" => "In den letzten 30 Minuten wurde nichts eingekauft. Erst den Artikel im Modus „Kaufen“ scannen, dann hier die Menge korrigieren.",
        "cancel" => "Abbrechen", "ok" => "Übernehmen",
        "act" => array("purchase" => "Eingekauft", "consume" => "Verbraucht", "consumeAll" => "Alles verbraucht",
            "spoiled" => "Als verdorben gebucht", "open" => "Geöffnet", "stock" => "Bestand", "shopping" => "Auf die Einkaufsliste",
            "noStock" => "Nicht vorrätig", "weight" => "Gewicht eingeben", "quantity" => "Menge geändert", "mode" => "Modus",
            "inStock" => "Bestand", "noBestBefore" => "Kein Standard-MHD hinterlegt", "assign" => "Bitte in BarcodeBuddy zuordnen"),
        "modes" => array("Purchase" => "Kaufen", "Consume" => "Verbrauchen", "Open" => "Öffnen", "Inventory" => "Bestand",
            "Add to shoppinglist" => "Einkaufsliste", "Consume (spoiled)" => "Verdorben", "Consume (all)" => "Alles verbrauchen"),
    ),
    "en" => array(
        "title" => "Scanner", "ready" => "Ready to scan", "readyHint" => "Scan a barcode or pick a mode",
        "done" => "Done", "lookedUp" => "New barcode – name found", "unknown" => "Unknown barcode",
        "error" => "Connection error", "history" => "Recent scans", "historyEmpty" => "No scans yet",
        "connected" => "Connected", "connecting" => "Connecting …", "unavailable" => "Unavailable",
        "noSse" => "This browser does not support live updates (server-sent events).",
        "sound" => "Sound & keep screen on", "fullscreen" => "Fullscreen", "delete" => "Delete", "editProduct" => "Edit product",
        "busy" => "Processing …", "bbFollows" => "Best-before prompt follows …", "noAnswer" => "No response received – please check the log",
        "steps" => array("received" => "Barcode received", "lookup" => "Looking up Open Food Facts …",
            "create" => "Creating product in Grocy …", "picture" => "Loading product picture …"),
        "store" => "Store items …", "quantity" => "Quantity",
        "qtyTitle" => "Correct quantity", "qtyFor" => "Last purchase: %s (so far %s)", "qtyLoading" => "Loading last purchase …",
        "qtyNone" => "Nothing was purchased in the last 30 minutes. Scan the item in “Purchase” mode first, then correct the quantity here.",
        "cancel" => "Cancel", "ok" => "Apply",
        "act" => array("purchase" => "Purchased", "consume" => "Consumed", "consumeAll" => "Consumed all",
            "spoiled" => "Consumed as spoiled", "open" => "Opened", "stock" => "In stock", "shopping" => "Added to shopping list",
            "noStock" => "Not in stock", "weight" => "Enter weight", "quantity" => "Quantity changed", "mode" => "Mode",
            "inStock" => "In stock", "noBestBefore" => "No default best before date set", "assign" => "Please assign it in BarcodeBuddy"),
        "modes" => array("Purchase" => "Purchase", "Consume" => "Consume", "Open" => "Open", "Inventory" => "Inventory",
            "Add to shoppinglist" => "Shopping list", "Consume (spoiled)" => "Spoiled", "Consume (all)" => "Consume all"),
    ),
);
$t = $T[$lang];

// Modus-Kacheln: Name wie von BarcodeBuddy gemeldet, Barcode, Icon, Farbe
$modes = array(
    array("Purchase", $config["BARCODE_P"], "purchase", "green"),
    array("Consume", $config["BARCODE_C"], "consume", "orange"),
    array("Open", $config["BARCODE_O"], "open", "blue"),
    array("Inventory", $config["BARCODE_GS"], "stock", "purple"),
    array("Add to shoppinglist", $config["BARCODE_AS"], "list", "teal"),
    array("Consume (spoiled)", $config["BARCODE_CS"], "spoiled", "red"),
    array("Consume (all)", $config["BARCODE_CA"], "all", "brown"),
);

$jsConfig = array(
    "lang"          => $lang,
    "t"             => $t,
    "fullscreenCfg" => (bool)$config["WS_FULLSCREEN"],
    "icons"         => array("ok" => $icons["ok"], "lookup" => $icons["lookup"], "unknown" => $icons["unknown"],
        "error" => $icons["error"], "scan" => $icons["scan"], "soundOn" => $icons["soundOn"], "soundOff" => $icons["soundOff"]),
    "modeColors"    => array_column($modes, 3, 0),
);
$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
?>
<!DOCTYPE html>
<html lang="<?php echo $lang ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#1f2937">
    <link rel="apple-touch-icon" sizes="180x180" href="./incl/img/favicon/apple-icon-180x180.png">
    <link rel="icon" type="image/png" sizes="192x192" href="./incl/img/favicon/android-icon-192x192.png">
    <link rel="icon" type="image/png" sizes="32x32" href="./incl/img/favicon/favicon-32x32.png">
    <title>Barcode Buddy – <?php echo htmlspecialchars($t["title"]) ?></title>
    <style>
        :root {
            --bg: #f3f4f6; --surface: #ffffff; --surface-2: #f9fafb; --text: #111827; --muted: #6b7280; --border: #e5e7eb;
            --bar: #1f2937; --bar-text: #f9fafb;
            --green: #16a34a; --orange: #ea580c; --blue: #2563eb; --purple: #7c3aed; --teal: #0d9488; --red: #dc2626; --brown: #92400e;
            --ok-bg: #dcfce7; --ok-fg: #166534; --lookup-bg: #dbeafe; --lookup-fg: #1e40af;
            --unknown-bg: #fef3c7; --unknown-fg: #92400e; --error-bg: #fee2e2; --error-fg: #991b1b;
            --radius: 16px; --shadow: 0 1px 3px rgba(0,0,0,.08), 0 4px 16px rgba(0,0,0,.06);
            --mode: var(--green);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0b1120; --surface: #111827; --surface-2: #1f2937; --text: #f3f4f6; --muted: #9ca3af; --border: #374151;
                --bar: #030712; --ok-bg: #14532d; --ok-fg: #bbf7d0; --lookup-bg: #1e3a8a; --lookup-fg: #bfdbfe;
                --unknown-bg: #78350f; --unknown-fg: #fde68a; --error-bg: #7f1d1d; --error-fg: #fecaca;
                --shadow: 0 1px 3px rgba(0,0,0,.4);
            }
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html { height: 100%; }
        body { margin: 0; min-height: 100%; }
        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: var(--bg); color: var(--text); display: flex; flex-direction: column;
            user-select: none; -webkit-user-select: none; overscroll-behavior: none;
        }
        button { font: inherit; color: inherit; cursor: pointer; touch-action: manipulation; }
        .icon { width: 1.6em; height: 1.6em; fill: currentColor; flex: none; }

        /* Kopfzeile */
        .topbar {
            display: flex; align-items: center; gap: 10px; padding: 10px 14px; padding-top: max(10px, env(safe-area-inset-top));
            background: var(--bar); color: var(--bar-text);
        }
        .icon-btn {
            display: inline-flex; align-items: center; justify-content: center; width: 52px; height: 52px;
            border: 0; border-radius: 14px; background: rgba(255,255,255,.08); color: var(--bar-text);
        }
        .icon-btn:active { background: rgba(255,255,255,.2); }
        .icon-btn.on { background: var(--green); }
        .mode-chip {
            display: inline-flex; align-items: center; gap: 10px; padding: 10px 18px; border-radius: 999px;
            background: var(--mode); color: #fff; font-weight: 700; font-size: 1.35rem; transition: background .3s;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0; flex: 0 1 auto;
        }
        .mode-chip .icon { width: 1.4em; height: 1.4em; }
        .spacer { flex: 1; }
        .conn { display: inline-flex; align-items: center; gap: 8px; font-size: .95rem; color: #d1d5db; white-space: nowrap; }
        .conn i { width: 10px; height: 10px; border-radius: 50%; background: #f59e0b; display: inline-block; }
        .conn.ok i { background: #22c55e; } .conn.bad i { background: #ef4444; }

        /* Einlagerungs-Balken (wird von haStorage.js gefuellt) */
        #ha-banner { border-radius: 0; }

        /* Hauptbereich */
        /* Hochformat/Handy: Seite darf scrollen, Ergebnis bekommt den freien Platz */
        .main { flex: 1 0 auto; display: grid; grid-template-columns: 1fr; grid-template-rows: 1fr auto; gap: 14px; padding: 14px; }
        .card { background: var(--surface); border-radius: var(--radius); box-shadow: var(--shadow); }
        .result {
            display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;
            padding: 24px; min-height: 260px; transition: background-color .35s, color .35s;
        }
        .result .big { width: 96px; height: 96px; fill: currentColor; opacity: .9; }
        .result-title { font-size: 1.5rem; font-weight: 700; margin-top: 12px; }
        .result-text { font-size: clamp(1.4rem, 3.4vw, 2.6rem); font-weight: 800; margin-top: 10px; line-height: 1.2; word-break: break-word; }
        .result-hint { color: var(--muted); margin-top: 10px; font-size: 1.05rem; }
        .result[data-state="idle"] .big { color: var(--muted); animation: pulse 2.4s ease-in-out infinite; }
        .result[data-state="ok"] { background: var(--ok-bg); color: var(--ok-fg); }
        .result[data-state="lookup"] { background: var(--lookup-bg); color: var(--lookup-fg); }
        .result[data-state="unknown"] { background: var(--unknown-bg); color: var(--unknown-fg); }
        .result[data-state="error"] { background: var(--error-bg); color: var(--error-fg); }
        .result[data-state="busy"] { background: var(--surface-2); }
        .result[data-state="busy"] .big { display: none; }
        .spinner { display: none; width: 96px; height: 96px; border-radius: 50%; border: 9px solid rgba(37,99,235,.18); border-top-color: var(--blue); animation: spin .9s linear infinite; }
        .result[data-state="busy"] .spinner { display: block; }
        .result[data-state="busy"] .result-hint { color: var(--blue); font-weight: 600; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .result-action { margin-top: 16px; min-height: 52px; padding: 0 22px; border: 0; border-radius: 999px; background: rgba(0,0,0,.08); color: inherit; font-weight: 700; font-size: 1.05rem; }
        .result-action:active { transform: scale(.96); }
        .result.pop { animation: pop .35s ease-out; }
        .result-text.pre { white-space: pre-wrap; font-size: 1.1rem; font-weight: 600; }
        @keyframes pulse { 0%, 100% { transform: scale(1); opacity: .5; } 50% { transform: scale(1.06); opacity: .9; } }
        @keyframes pop { from { transform: scale(.97); } to { transform: scale(1); } }

        .history { display: flex; flex-direction: column; min-height: 0; padding: 16px; }
        .history h2 { margin: 0 0 10px; font-size: 1rem; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); }
        .history ol { list-style: none; margin: 0; padding: 0; overflow-y: auto; flex: 1; max-height: 32vh; }
        .history li { display: flex; gap: 12px; align-items: flex-start; padding: 10px 4px; border-bottom: 1px solid var(--border); font-size: 1.05rem; }
        .history li:last-child { border-bottom: 0; }
        .history time { color: var(--muted); font-variant-numeric: tabular-nums; min-width: 3.2em; }
        .history .dot { width: 12px; height: 12px; border-radius: 50%; margin-top: 6px; flex: none; }
        .dot.ok { background: var(--green); } .dot.lookup { background: var(--blue); } .dot.unknown { background: #f59e0b; } .dot.error { background: var(--red); } .dot.mode { background: var(--muted); }
        .history .empty { color: var(--muted); padding: 10px 4px; border: 0; }

        /* Modus-Kacheln */
        .modes {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px;
            padding: 0 14px 14px; padding-bottom: max(14px, env(safe-area-inset-bottom));
        }
        .tile {
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px;
            min-height: 88px; padding: 10px 6px; border: 2px solid transparent; border-radius: var(--radius);
            background: var(--surface); box-shadow: var(--shadow); font-weight: 650; font-size: 1.02rem;
            transition: transform .1s, border-color .2s, background .2s;
        }
        .tile .icon { width: 2em; height: 2em; color: var(--c, var(--text)); }
        .tile:active { transform: scale(.96); }
        .tile.active { border-color: var(--c); background: color-mix(in srgb, var(--c) 14%, var(--surface)); }
        .tile.store { --c: var(--green); background: var(--green); color: #fff; grid-column: 1 / -1; flex-direction: row; font-size: 1.2rem; gap: 12px; }
        .tile.store .icon { color: #fff; }
        .c-green { --c: var(--green); } .c-orange { --c: var(--orange); } .c-blue { --c: var(--blue); } .c-purple { --c: var(--purple); }
        .c-teal { --c: var(--teal); } .c-red { --c: var(--red); } .c-brown { --c: var(--brown); } .c-gray { --c: var(--muted); }

        /* Tablet quer: alles auf einen Blick, Verlauf scrollt in seiner Karte */
        @media (min-width: 900px) and (orientation: landscape) {
            body { height: 100%; }
            .main { flex: 1 1 auto; min-height: 0; grid-template-columns: 3fr 2fr; grid-template-rows: 1fr; }
            .history ol { max-height: none; }
            .modes { grid-template-columns: repeat(10, 1fr); }
            .tile.store { grid-column: span 2; }
        }
        /* Handy */
        @media (max-width: 560px) {
            .topbar { gap: 8px; padding-left: 10px; padding-right: 10px; }
            .icon-btn { width: 46px; height: 46px; }
            .mode-chip { font-size: 1.1rem; padding: 8px 14px; }
            #conn-text { display: none; }
            .main, .modes { padding-left: 10px; padding-right: 10px; }
            .modes { gap: 8px; }
            .tile { min-height: 78px; font-size: .8rem; padding: 8px 2px; border-radius: 14px; }
            .tile .icon { width: 1.8em; height: 1.8em; }
            .tile.store { font-size: 1.1rem; min-height: 64px; }
            .result { min-height: 220px; }
            .result .big, .spinner { width: 72px; height: 72px; }
        }

        /* Mengen-Dialog */
        .sheet { position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 15; background: rgba(0,0,0,.55); display: none; align-items: center; justify-content: center; padding: 16px; }
        .sheet.open { display: flex; }
        .sheet-box { background: var(--surface); color: var(--text); border-radius: 20px; padding: 22px; width: 100%; max-width: 420px; text-align: center; }
        .sheet-box h2 { margin: 0; font-size: 1.4rem; }
        .sheet-box p { color: var(--muted); margin: 6px 0 14px; }
        .qty-display { font-size: 2.6rem; font-weight: 800; padding: 8px; border-radius: 12px; background: var(--surface-2); margin-bottom: 12px; min-height: 1.6em; }
        .keypad { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .keypad button, .sheet-actions button { min-height: 64px; border: 0; border-radius: 14px; background: var(--surface-2); font-size: 1.5rem; font-weight: 700; }
        .keypad button:active, .sheet-actions button:active { transform: scale(.96); }
        .sheet-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 12px; }
        .sheet-actions button { font-size: 1.15rem; }
        .sheet-actions .primary { background: var(--green); color: #fff; }
        .sheet-actions .primary:disabled { opacity: .45; }
        .qty-error { color: var(--red); min-height: 1.3em; margin-top: 10px; font-weight: 600; }
        .keypad .icon { width: 1.3em; height: 1.3em; vertical-align: middle; }
    </style>
</head>
<body>
<header class="topbar">
    <div class="mode-chip" id="mode-chip"><?php echo haIcon("scan") ?><span id="mode">…</span></div>
    <div class="spacer"></div>
    <span class="conn" id="conn"><i></i><span id="conn-text"><?php echo htmlspecialchars($t["connecting"]) ?></span></span>
    <button class="icon-btn" id="btn-sound" title="<?php echo htmlspecialchars($t["sound"]) ?>" aria-label="<?php echo htmlspecialchars($t["sound"]) ?>" aria-pressed="false"><svg class="icon" viewBox="0 0 24 24"><path id="sound-path" d="<?php echo $icons["soundOff"] ?>"/></svg></button>
    <button class="icon-btn" id="btn-full" title="<?php echo htmlspecialchars($t["fullscreen"]) ?>" aria-label="<?php echo htmlspecialchars($t["fullscreen"]) ?>"><?php echo haIcon("full") ?></button>
</header>
<div id="ha-banner"></div>

<main class="main">
    <section class="card result" id="result" data-state="idle" aria-live="polite">
        <svg class="big" viewBox="0 0 24 24" aria-hidden="true"><path id="result-icon" d="<?php echo $icons["scan"] ?>"/></svg>
        <div class="spinner" aria-hidden="true"></div>
        <div class="result-title" id="result-title"><?php echo htmlspecialchars($t["ready"]) ?></div>
        <div class="result-text" id="result-text"></div>
        <div class="result-hint" id="result-hint"><?php echo htmlspecialchars($t["readyHint"]) ?></div>
        <button class="result-action" id="result-edit" hidden><?php echo htmlspecialchars($t["editProduct"]) ?></button>
    </section>
    <section class="card history">
        <h2><?php echo htmlspecialchars($t["history"]) ?></h2>
        <ol id="history"><li class="empty"><?php echo htmlspecialchars($t["historyEmpty"]) ?></li></ol>
    </section>
</main>

<nav class="modes" aria-label="Modus">
    <button class="tile store" id="ha-storage-menu" onclick="return haStorageOpen()"><?php echo haIcon("store") ?><span><?php echo htmlspecialchars($t["store"]) ?></span></button>
    <?php foreach ($modes as $mode) {
        if ((string)$mode[1] === "")
            continue; ?>
        <button class="tile c-<?php echo $mode[3] ?>" data-mode="<?php echo htmlspecialchars($mode[0]) ?>" data-barcode="<?php echo htmlspecialchars((string)$mode[1]) ?>"><?php echo haIcon($mode[2]) ?><span><?php echo htmlspecialchars($t["modes"][$mode[0]]) ?></span></button>
    <?php } ?>
    <button class="tile c-gray" id="btn-qty"><?php echo haIcon("quantity") ?><span><?php echo htmlspecialchars($t["quantity"]) ?></span></button>
</nav>

<div class="sheet" id="qty-sheet" role="dialog" aria-modal="true" aria-labelledby="qty-title">
    <div class="sheet-box">
        <h2 id="qty-title"><?php echo htmlspecialchars($t["qtyTitle"]) ?></h2>
        <p id="qty-hint"></p>
        <div class="qty-display" id="qty-display">1</div>
        <div class="keypad" id="keypad">
            <button>1</button><button>2</button><button>3</button>
            <button>4</button><button>5</button><button>6</button>
            <button>7</button><button>8</button><button>9</button>
            <button data-key=".">,</button><button>0</button><button data-key="back" aria-label="<?php echo htmlspecialchars($t["delete"]) ?>"><?php echo haIcon("backspace") ?></button>
        </div>
        <div class="sheet-actions">
            <button id="qty-cancel"><?php echo htmlspecialchars($t["cancel"]) ?></button>
            <button class="primary" id="qty-ok"><?php echo htmlspecialchars($t["ok"]) ?></button>
        </div>
        <div class="qty-error" id="qty-error" role="alert"></div>
    </div>
</div>

<audio id="beep_success" muted src="incl/websocket/beep.ogg" preload="auto"></audio>
<audio id="beep_nosuccess" muted src="incl/websocket/buzzer.ogg" preload="auto"></audio>

<script src="./incl/js/nosleep.min.js"></script>
<script src="./incl/js/he.js"></script>
<script>
    (function () {
        "use strict";
        var BB = <?php echo json_encode($jsConfig, $jsonFlags) ?>;
        var t = BB.t;
        var $ = function (id) { return document.getElementById(id); };

        // ------------------------------------------------------------ Modus & Barcodes senden
        function sendBarcode(barcode) {
            var xhttp = new XMLHttpRequest();
            xhttp.open("GET", "./api/action/scan?add=" + encodeURIComponent(barcode), true);
            xhttp.send();
        }
        window.sendBarcode = sendBarcode;
        window.closeNav = function () {};

        var currentMode = null;
        function setMode(name) {
            // Wechsel in den Kauf-Modus: Lagerort waehlen lassen, damit das MHD abgefragt wird
            // (nicht beim ersten Laden der Seite, nur bei einem echten Wechsel)
            if (name === "Purchase" && currentMode !== null && currentMode !== "Purchase"
                && typeof window.haStorageEnsureSession === "function")
                window.haStorageEnsureSession();
            currentMode = name;
            $("mode").textContent = (t.modes && t.modes[name]) || name;
            var color = BB.modeColors[name] || "green";
            document.documentElement.style.setProperty("--mode", "var(--" + color + ")");
            Array.prototype.forEach.call(document.querySelectorAll(".tile[data-mode]"), function (tile) {
                tile.classList.toggle("active", tile.getAttribute("data-mode") === name);
            });
        }

        Array.prototype.forEach.call(document.querySelectorAll(".tile[data-barcode]"), function (tile) {
            tile.addEventListener("click", function () {
                sendBarcode(tile.getAttribute("data-barcode"));
                if (tile.getAttribute("data-mode") === "Purchase" && typeof window.haStorageEnsureSession === "function")
                    window.haStorageEnsureSession();
            });
        });

        // ------------------------------------------------------------ Mengen-Tastatur
        // Korrigiert die Menge des zuletzt gebuchten Einkaufs in Grocy (z.B. 1 gescannt, 6 gekauft).
        var qty = "1", qtyFresh = true, qtyLast = null, qtyBusy = false;
        function fmtAmount(value) { return String(value).replace(".", BB.lang === "de" ? "," : "."); }
        function renderQty() { $("qty-display").textContent = fmtAmount(qty || "0"); }
        function closeQty() { $("qty-sheet").classList.remove("open"); }
        $("btn-qty").addEventListener("click", function () {
            qty = "1"; qtyFresh = true; qtyLast = null; renderQty();
            $("qty-error").textContent = "";
            $("qty-hint").textContent = t.qtyLoading;
            $("qty-ok").disabled = true;
            $("qty-sheet").classList.add("open");
            fetch("./ha_storage.php?action=state", {credentials: "same-origin"}).then(function (r) { return r.json(); }).then(function (data) {
                qtyLast = data.last_purchase || null;
                if (qtyLast) {
                    $("qty-hint").textContent = t.qtyFor.replace("%s", qtyLast.name).replace("%s", fmtAmount(qtyLast.amount));
                    qty = String(qtyLast.amount); renderQty();
                    $("qty-ok").disabled = false;
                } else {
                    $("qty-hint").textContent = t.qtyNone;
                }
            }).catch(function () { $("qty-hint").textContent = t.qtyNone; });
        });
        $("keypad").addEventListener("click", function (event) {
            var button = event.target.closest("button");
            if (!button) return;
            var key = button.getAttribute("data-key") || button.textContent.trim();
            if (key === "back") qty = qty.slice(0, -1);
            else if (key === ".") { if (qtyFresh) qty = "0"; if (qty.indexOf(".") < 0) qty += "."; }
            else qty = (qtyFresh || qty === "0") ? key : qty + key;
            qtyFresh = false;
            if (qty.length > 7) qty = qty.slice(0, 7);
            renderQty();
        });
        $("qty-cancel").addEventListener("click", closeQty);
        $("qty-ok").addEventListener("click", function () {
            var value = parseFloat(qty);
            if (!qtyLast || !(value > 0) || qtyBusy) return;
            qtyBusy = true;
            $("qty-error").textContent = "";
            fetch("./ha_storage.php?action=quantity", {
                method: "POST", credentials: "same-origin",
                headers: {"Content-Type": "application/json"}, body: JSON.stringify({amount: value})
            }).then(function (r) {
                return r.json().then(function (data) { if (!r.ok) throw new Error(data.error || ("HTTP " + r.status)); return data; });
            }).then(function (data) {
                qtyBusy = false;
                closeQty();
                showResult("ok", A.quantity, data.name, "beep_success", fmtAmount(data.before) + " → " + fmtAmount(data.amount));
                if (typeof window.haStorageAfterScan === "function") window.haStorageAfterScan();
            }).catch(function (e) {
                qtyBusy = false;
                $("qty-error").textContent = e.message;
            });
        });

        // ------------------------------------------------------------ Kopfzeile

        var noSleep = typeof NoSleep !== "undefined" ? new NoSleep() : null;
        var soundOn = false;
        $("btn-sound").addEventListener("click", function () {
            soundOn = !soundOn;
            if (noSleep) { soundOn ? noSleep.enable() : noSleep.disable(); }
            $("beep_success").muted = !soundOn;
            $("beep_nosuccess").muted = !soundOn;
            $("sound-path").setAttribute("d", soundOn ? BB.icons.soundOn : BB.icons.soundOff);
            $("btn-sound").classList.toggle("on", soundOn);
            $("btn-sound").setAttribute("aria-pressed", soundOn ? "true" : "false");
            if (BB.fullscreenCfg) toggleFullscreen(soundOn);
        });

        function toggleFullscreen(force) {
            var active = !!document.fullscreenElement;
            var want = typeof force === "boolean" ? force : !active;
            if (want && !active && document.documentElement.requestFullscreen) document.documentElement.requestFullscreen().catch(function () {});
            if (!want && active && document.exitFullscreen) document.exitFullscreen().catch(function () {});
        }
        if (!document.fullscreenEnabled) $("btn-full").style.display = "none";
        $("btn-full").addEventListener("click", function () { toggleFullscreen(); });

        // ------------------------------------------------------------ Meldungen verstaendlich aufbereiten
        // BarcodeBuddy meldet englische Saetze ("Adding 1 Pack of Milk. In Stock: 3"). Fuer die Anzeige
        // werden Aktion, Produktname und Details getrennt; Unbekanntes wird unveraendert gezeigt.
        var A = t.act;
        var PATTERNS = [
            [/^Adding\s+(\S+) (.*?) of (.+)$/, "purchase"],
            [/^Consuming all\s*(\S+) (.*?) of (.+)$/, "consumeAll"],
            [/^Consuming (\S+) spoiled (.*?) of (.+)$/, "spoiled"],
            [/^Consuming (\S+) (.*?) of (.+)$/, "consume"],
            [/^Opening (\S+) (.*?) of (.+)$/, "open"],
            [/^Currently in stock: (\S+) (.*?) of (.+)$/, "stock"],
            [/^Added to shopping list: (\S+) (.*?) of (.+)$/, "shopping"]
        ];

        function describe(raw) {
            var text = raw.replace(/\\n/g, "\n").trim();
            var stock = null;
            text = text.replace(/\.? ?In Stock: ([\d.,-]+)/, function (m, amount) { stock = amount; return ""; });
            var warning = /- WARNING: No default best before date set!?/.test(text);
            text = text.replace(/\s*- WARNING: No default best before date set!?/, "").trim();
            var lines = text.split("\n");
            var first = lines.shift();
            var m, i, details = [];
            if ((m = first.match(/^Set state to (.+)$/))) return {mode: m[1]};
            if ((m = first.match(/^Set quantity to (\S+) for barcode (.+)$/))) return {title: A.quantity, name: m[2], detail: m[1]};
            if ((m = first.match(/^(?:Product found \. )?None in stock, not (?:consuming|opening): (.+)$/))) return {title: A.noStock, name: m[1], detail: "", state: "unknown"};
            if ((m = first.match(/^Action required: Enter weight for (.+)$/))) return {title: A.weight, name: m[1], detail: "", state: "lookup"};
            for (i = 0; i < PATTERNS.length; i++) {
                if ((m = first.match(PATTERNS[i][0]))) {
                    details.push((m[1] + " " + m[2]).trim());
                    if (stock !== null) details.push(A.inStock + ": " + stock);
                    lines.forEach(function (line) {
                        var loc = line.match(/^Location (.+?): (.+)$/);
                        details.push(loc ? loc[1] + ": " + loc[2] : line);
                    });
                    if (warning) details.push(A.noBestBefore);
                    return {title: A[PATTERNS[i][1]], name: m[3].trim(), detail: details.join(" · ")};
                }
            }
            return {title: t.done, name: raw.replace(/\\n/g, "\n"), detail: ""};
        }

        // ------------------------------------------------------------ Ergebnis & Verlauf
        var resetTimer = null;
        function showIdle() {
            $("result-edit").hidden = true;
            var result = $("result");
            result.setAttribute("data-state", "idle");
            $("result-icon").setAttribute("d", BB.icons.scan);
            $("result-title").textContent = t.ready;
            $("result-text").textContent = "";
            $("result-text").classList.remove("pre");
            $("result-hint").textContent = t.readyHint;
        }

        function addHistory(state, text) {
            var list = $("history");
            var empty = list.querySelector(".empty");
            if (empty) empty.remove();
            var now = new Date();
            var item = document.createElement("li");
            var time = document.createElement("time");
            time.textContent = ("0" + now.getHours()).slice(-2) + ":" + ("0" + now.getMinutes()).slice(-2);
            var dot = document.createElement("span");
            dot.className = "dot " + state;
            var label = document.createElement("span");
            label.textContent = text;
            item.appendChild(time); item.appendChild(dot); item.appendChild(label);
            list.insertBefore(item, list.firstChild);
            while (list.children.length > 30) list.removeChild(list.lastChild);
        }

        function showResult(state, title, text, sound, detail) {
            $("result-edit").hidden = true;
            var result = $("result");
            result.setAttribute("data-state", state);
            result.classList.remove("pop"); void result.offsetWidth; result.classList.add("pop");
            $("result-icon").setAttribute("d", BB.icons[state] || BB.icons.ok);
            $("result-title").textContent = title;
            $("result-text").textContent = text;
            $("result-hint").textContent = detail || "";
            if (sound) { var audio = $(sound); audio.currentTime = 0; audio.play().catch(function () {}); }
            addHistory(state, title + " · " + text);
            clearTimeout(resetTimer);
            resetTimer = setTimeout(showIdle, 4000);
        }

        /** Barcode wird im Hintergrund verarbeitet: Spinner und aktueller Schritt bis zum Ergebnis */
        function showBusy(step, code) {
            var result = $("result");
            result.setAttribute("data-state", "busy");
            $("result-title").textContent = t.busy;
            $("result-text").textContent = code;
            $("result-text").classList.remove("pre");
            $("result-hint").textContent = (t.steps && t.steps[step]) || "";
            clearTimeout(resetTimer);
            resetTimer = setTimeout(function () {
                showIdle();
                $("result-hint").textContent = t.noAnswer;
            }, 45000);
        }

        // Nach einem Einkauf: Produkt des letzten Einkaufs bearbeiten (Lagerort, Mengeneinheit)
        $("result-edit").addEventListener("click", function () {
            clearTimeout(resetTimer);
            if (typeof window.haStorageEditProduct === "function")
                window.haStorageEditProduct(0, function () { showIdle(); });
        });

        /** Nach einem Ergebnis: im Einlagerungsmodus die MHD-Abfrage sofort holen */
        function afterScan(isPurchase) {
            if (isPurchase) {
                $("result-edit").hidden = false;
                clearTimeout(resetTimer);
                resetTimer = setTimeout(showIdle, 10000);
            }
            if (typeof window.haStorageAfterScan === "function") window.haStorageAfterScan();
            if (isPurchase && typeof window.haStorageActive === "function" && window.haStorageActive()) {
                var hint = $("result-hint");
                hint.textContent = (hint.textContent ? hint.textContent + " · " : "") + t.bbFollows;
            }
        }

        function setConnection(cls, text) {
            $("conn").className = "conn " + cls;
            $("conn-text").textContent = text;
        }

        // ------------------------------------------------------------ Live-Anbindung (wie Original)
        if (typeof EventSource === "undefined") {
            setConnection("bad", t.unavailable);
            showResult("error", t.error, t.noSse, null);
            return;
        }

        (function syncCache() {
            var xhttp = new XMLHttpRequest();
            xhttp.open("GET", "./cron.php", true);
            xhttp.send();
        })();

        var source = new EventSource("incl/sse/sse_data.php");
        var isFirstStart = true, connectFailCounter = 0, lastFail = 0;

        source.addEventListener("error", function (event) {
            if (event.target.readyState === EventSource.CONNECTING) {
                setConnection("", t.connecting);
                if (Date.now() - lastFail < 10000) connectFailCounter++;
                if (connectFailCounter === 10) {
                    source.close();
                    setConnection("bad", t.unavailable);
                    showResult("error", t.error, t.unavailable, null);
                    clearTimeout(resetTimer);
                }
                lastFail = Date.now();
            }
        }, false);

        source.onopen = function () {
            setConnection("ok", t.connected);
            if (isFirstStart) {
                isFirstStart = false;
                var http = new XMLHttpRequest();
                http.open("GET", "incl/sse/sse_data.php?getState");
                http.send();
            }
        };

        source.onmessage = function (event) {
            var resultJson = JSON.parse(event.data);
            var code = resultJson.data.substring(0, 1);
            var text = resultJson.data.substring(1);
            switch (code) {
                case "0":
                    var info = describe(he.decode(text));
                    if (info.mode) {
                        addHistory("mode", A.mode + ": " + ((t.modes && t.modes[info.mode]) || info.mode));
                        break;
                    }
                    showResult(info.state || "ok", info.title, info.name, info.state === "unknown" ? "beep_nosuccess" : "beep_success", info.detail);
                    afterScan(info.title === A.purchase);
                    break;
                case "1":
                    showResult("lookup", t.lookedUp, he.decode(text), "beep_success", A.assign);
                    afterScan(false);
                    break;
                case "2":
                    showResult("unknown", t.unknown, text, "beep_nosuccess", A.assign);
                    afterScan(false);
                    break;
                case "5":
                    // Home-Assistant-Add-on: Verarbeitung laeuft ("Schritt|Barcode")
                    var progress = text.split("|");
                    showBusy(progress[0], progress[1] || "");
                    if (typeof window.haStorageProgress === "function") window.haStorageProgress(progress[0]);
                    break;
                case "4":
                    setMode(text);
                    break;
                case "E":
                    setConnection("bad", t.unavailable);
                    showResult("error", t.error, text, null);
                    $("result-text").classList.add("pre");
                    clearTimeout(resetTimer);
                    break;
            }
        };
    })();
</script>
<script src="./incl/js/haStorage.js"></script>
</body>
</html>
