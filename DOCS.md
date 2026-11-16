# Barcode Buddy für Grocy

Barcodes scannen und Grocy automatisch pflegen: Einkäufe, Verbrauch, Einkaufsliste und Bestand. Unbekannte
Produkte werden aus Open Food Facts angelegt, beim Einkaufen wird das Mindesthaltbarkeitsdatum abgefragt.

## Erste Schritte

1. Add-on starten und über die Seitenleiste **Barcode Buddy** öffnen.
2. Die Adresse der Grocy-API (z.B. `http://<grocy-adresse>/api/`) und einen Grocy-API-Schlüssel eintragen
   (*Grocy → Benutzer → API-Schlüssel verwalten*).
3. Optional einen Barcodescanner anbinden (siehe unten) oder die **Screen**-Seite auf einem Tablet öffnen.

## Optionen

| Option | Bedeutung |
|---|---|
| Unbekannte Produkte automatisch anlegen | Produkte aus Open Food Facts in Grocy anlegen (Standard: an) |
| Lagerort / Mengeneinheit für neue Produkte | Namen wie in Grocy; leer = Grocy-Voreinstellung |
| Sprache | Sprache für Produktdaten und die gesamte Oberfläche (`de` = Deutsch, sonst Englisch) |
| Barcodescanner anbinden / Scanner-Gerät | Scanner bzw. Tastatur am Home-Assistant-Host verwenden |
| Scanner-Diagnose | 30 Sekunden lang Tastendrücke ins Log schreiben (nur zur Fehlersuche) |
| Anmeldung deaktivieren | Kein Login am direkten Port 8621 (über Home Assistant ist nie ein Login nötig) |
| Debug-Modus, SSL-Optionen | Fehlersuche bzw. selbstsignierte Grocy-Zertifikate |

## Home Assistant Ingress

Das Add-on unterstützt Ingress: Die Oberfläche öffnet sich direkt in Home Assistant
(Seitenleiste „Barcode Buddy“ bzw. „Benutzeroberfläche öffnen“), ohne eigenen Port und ohne extra Login –
die Anmeldung übernimmt Home Assistant.

Der direkte Port (Standard `8621`) bleibt bestehen, z.B. für die API und Scanner-Apps.
Wer ihn nicht braucht, kann ihn in der Add-on-Konfiguration unter *Netzwerk* leeren.
Für den direkten Port gelten weiterhin die Barcode Buddy-Anmeldung und die Option `disable_auth`.

## Unbekannte Produkte automatisch anlegen

Wird ein Barcode gescannt, den Grocy noch nicht kennt, schlägt das Add-on ihn bei
[Open Food Facts](https://world.openfoodfacts.org) nach und legt das Produkt direkt in Grocy an.
Anschließend wird der Scan ganz normal verbucht (z.B. als Einkauf).

Übernommen werden:

- Name (in der eingestellten Sprache; ist der Name in Grocy schon vergeben, wird die Marke bzw. der Barcode ergänzt)
- Produktbild
- Barcode
- Beschreibung mit Marke, Menge, Portionsgröße, Nutri-Score, NOVA-Gruppe, Zutaten, Allergenen, Spuren und Nährwerttabelle
- Energie (kcal pro Packung)
- Produktgruppe, wenn eine Grocy-Produktgruppe genauso heißt wie eine Open-Food-Facts-Kategorie
- Grocy-Voreinstellungen für neue Produkte (Mindesthaltbarkeit in Tagen, „Geöffnet als nicht vorrätig behandeln“)

**Mengeneinheit und Lagerort** kommen aus den Optionen `auto_create_quantity_unit` und
`auto_create_location` (Namen wie in Grocy, z.B. „Packung“ und „Vorratsschrank“). Sind sie leer,
gelten die Grocy-Voreinstellungen für neue Produkte (*Grocy → Einstellungen → Produkte*) oder der jeweils
erste Eintrag. Beides lässt sich später pro Produkt in Grocy ändern; das Barcode Buddy-Protokoll nennt
bei jedem angelegten Produkt die verwendeten Werte.

Ist der Bildserver von Open Food Facts beim Scan nicht erreichbar, wird das Produkt trotzdem angelegt und das
Bild im Hintergrund nachgeladen (nach 2 min, 10 min, 30 min, 2 h, 6 h und 24 h). Das Protokoll meldet, wenn
das Bild nachgeladen wurde oder endgültig nicht geladen werden konnte. Hat das Produkt inzwischen ein Bild,
wird es nicht überschrieben.

Findet Open Food Facts nichts oder schlägt das Anlegen fehl, landet der Barcode wie bisher in der Liste
der unbekannten Barcodes. Barcodes, die dort schon stehen, werden nicht automatisch angelegt.
Abschalten lässt sich die Funktion mit `auto_create_products: false`.

Hinweis: Die Energie wird pro Packung eingetragen, weil Grocy sie pro Bestandsmengeneinheit erwartet.
Wer als Mengeneinheit z.B. Gramm verwendet, sollte den Wert in Grocy anpassen. Die Daten von
Open Food Facts stehen unter der [ODbL-Lizenz](https://opendatacommons.org/licenses/odbl/1-0/).

## Screen-Seite für Touchscreens

Die Screen-Seite (in Barcode Buddy oben „Screen“) ist für Tablets und Smartphones neu gestaltet:

- Große Kacheln für alle Modi (Kaufen, Verbrauchen, Öffnen, Bestand, Einkaufsliste, Verdorben, Alles verbrauchen)
  statt eines versteckten Menüs; der aktive Modus ist farbig markiert und steht oben in der Leiste
- Ergebniskarte mit Aktion, großem Produktnamen und Details (z.B. „Eingekauft – Milch – 1 Packung · Bestand: 3“),
  farbig nach Ergebnis; nach 4 Sekunden wieder „Bereit zum Scannen“
- Verlauf der letzten Scans mit Uhrzeit
- „Wird verarbeitet …“ mit Spinner und aktuellem Schritt (Barcode empfangen, Suche bei Open Food Facts,
  Produkt anlegen, Bild laden), sobald ein Barcode eingeht – bis das Ergebnis bzw. die MHD-Abfrage erscheint
- **Menge korrigieren:** Zuletzt eingekauften Artikel auf die richtige Anzahl setzen (z.B. 1× gescannt, 6 gekauft).
  Der Bestandseintrag in Grocy wird geändert; die Standardmenge des Barcodes bleibt unverändert.
  Möglich bis 30 Minuten nach dem Einkauf.
- Ton und „Bildschirm wach halten“ per Taste, Vollbild-Taste, Verbindungsanzeige
- Funktioniert auch über unverschlüsseltes HTTP: alle Icons sind eingebettet, nichts wird von fremden Servern geladen
- Hell- und Dunkelmodus nach Systemeinstellung, Texte auf Deutsch oder Englisch (Option `product_language`)
- Hochformat, Querformat und Smartphone; im Querformat passt alles ohne Scrollen auf den Bildschirm

## Einlagern und Einkaufen mit Abfrage des Mindesthaltbarkeitsdatums

Auf der Screen-Seite startet die grüne Taste **„Einlagern …“** die Einlagerung. Dasselbe passiert, wenn in den
Modus **„Kaufen“** gewechselt wird (Kachel oder Kauf-Barcode) und noch keine Einlagerung läuft:

1. Lagerort wählen, z.B. „Kühlschrank“, oder **„Standard-Lagerort des Produkts“** (Lagerort bleibt wie in Grocy
   hinterlegt, das MHD wird trotzdem abgefragt). Barcode Buddy wechselt in den Kauf-Modus, oben erscheint ein grüner Balken.
2. Alle Produkte scannen. Jedes wird in Grocy **an diesem Lagerort** eingebucht. Neue Produkte, die automatisch
   aus Open Food Facts angelegt werden, bekommen ihn auch als Standard-Lagerort.
3. Nach jedem Scan fragt ein Dialog das **Mindesthaltbarkeitsdatum** ab. Das aufgedruckte Datum wird über die
   eigene Zifferntastatur im Dialog eingetippt (eine angeschlossene Tastatur geht auch), die Vorschau zeigt sofort,
   was erkannt wurde:

   | Aufdruck | Eingabe (Beispiele) | Ergebnis |
   |---|---|---|
   | Tag, Monat, Jahr | `15.12.26`, `15.12.2026`, `151226`, `15122026` | 15.12.2026 |
   | Nur Monat und Jahr | `02.26`, `02.2026`, `0226`, `022026` | 01.02.2026 (erster Tag des Monats) |
   | Tag und Monat ohne Jahr | `15.12`, `1512` | 15.12. des nächsten passenden Jahres |

   Dazu kommen Schnelltasten (+3 Tage … +1 Jahr, „Wie zuletzt“), „Läuft nicht ab“ und „Überspringen“
   (behält das Standard-Datum des Produkts). Liegt das Datum in der Vergangenheit, weist die Vorschau darauf hin.

   Werden mehrere Produkte schnell hintereinander gescannt, kommen die Abfragen nacheinander.
4. Mit „Beenden“ im grünen Balken ist wieder alles wie vorher. Nach zwei Stunden ohne Scan endet die Einlagerung
   von selbst, damit spätere Einkäufe nicht versehentlich im Kühlschrank landen.

Die Scans können vom am Home-Assistant-Host angeschlossenen Scanner, von Scanner-Apps über die API oder aus
der Barcode Buddy-Oberfläche kommen; die Abfrage erscheint auf jeder geöffneten Screen-Seite.

## Barcodescanner / Tastatur anbinden

In der Add-on-Konfiguration:

1. `ATTACH_BARCODESCANNER` aktivieren
2. Unter `SCANNER_DEVICE` die gewünschte Tastatur bzw. den Scanner aus der Liste wählen
3. Add-on neu starten

Home Assistant gibt dem Add-on nur auf das gewählte Eingabegerät Zugriff; es wird an Barcode Buddy übergeben.
Erweiterter Hardware-Zugriff (`full_access`) ist dafür nicht nötig.

## Datenablage

Die Barcode-Buddy-Daten (`barcodebuddy.db`, `users.db`, `config.php`) liegen im Add-on-Ordner
`/addon_configs/<id>_bbuddy-grocy/data` und sind in Home-Assistant-Backups des Add-ons enthalten.

**Update von Version 0.4.x oder älter:** Diese Versionen speicherten die Daten in `<HA-Konfiguration>/data`.
Die automatische Übernahme gab es bis Version 1.1.0. Wer direkt von 0.4.x aktualisiert, installiert zuerst
1.1.0 (oder kopiert die drei Dateien von Hand in den neuen Ordner).

## Sicherheit

- Das Add-on hat keinen Zugriff auf die Home-Assistant-Konfiguration, nur auf seinen eigenen Ordner.
- Ingress ist nur für Administratoren sichtbar und nur vom Home-Assistant-Supervisor erreichbar.
- Der direkte Port `8621` ist unverschlüsselt (HTTP). Wird er nicht gebraucht, unter *Netzwerk* leeren.
  `disable_auth` nur aktivieren, wenn niemand Unbefugtes das Netzwerk erreicht.
