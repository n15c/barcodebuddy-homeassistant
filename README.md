# Barcode Buddy für Home Assistant


![Supports aarch64 Architecture][aarch64-shield]
![Supports amd64 Architecture][amd64-shield]
![Supports armhf Architecture][armhf-shield]
![Supports armv7 Architecture][armv7-shield]
![Supports i386 Architecture][i386-shield]

[aarch64-shield]: https://img.shields.io/badge/aarch64-yes-green.svg
[amd64-shield]: https://img.shields.io/badge/amd64-yes-green.svg
[armhf-shield]: https://img.shields.io/badge/armhf-yes-green.svg
[armv7-shield]: https://img.shields.io/badge/armv7-yes-green.svg
[i386-shield]: https://img.shields.io/badge/i386-yes-green.svg


Home-Assistant-Add-on für [Barcode Buddy](https://github.com/Forceu/barcodebuddy), erweitert um automatische
Produktanlage aus Open Food Facts, Einlagern mit MHD-Abfrage und eine Touchscreen-Oberfläche.

Der Quellcode von Barcode Buddy liegt in diesem Repository (Ordner [`barcodebuddy/`](barcodebuddy/)); alle
Änderungen sind dort direkt eingebaut. Das Add-on baut sein Image selbst auf dem offiziellen
Home-Assistant-Basisimage.

### Install Home Assistant

![](images/add-repo-url.png?raw=true)
1. Click context menu in addon section
2. Add custom repo url and point to this repo https://github.com/n15c/barcodebuddy-homeassistant

### Home Assistant Ingress

Das Add-on unterstützt Ingress: Die Oberfläche öffnet sich direkt in Home Assistant
(Seitenleiste „Barcode Buddy“ bzw. „Benutzeroberfläche öffnen“), ohne eigenen Port und ohne extra Login –
die Anmeldung übernimmt Home Assistant.

Der direkte Port (Standard `8621`) bleibt bestehen, z.B. für die API und Scanner-Apps.
Wer ihn nicht braucht, kann ihn in der Add-on-Konfiguration unter *Netzwerk* leeren.
Für den direkten Port gelten weiterhin die BarcodeBuddy-Anmeldung und die Option `disable_auth`.

### Unbekannte Produkte automatisch anlegen

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
erste Eintrag. Beides lässt sich später pro Produkt in Grocy ändern; das BarcodeBuddy-Protokoll nennt
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

### Screen-Seite für Touchscreens

Die Screen-Seite (in BarcodeBuddy oben „Screen“) ist für Tablets und Smartphones neu gestaltet:

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

### Einlagern mit Abfrage des Mindesthaltbarkeitsdatums

Auf der Screen-Seite gibt es die grüne Taste **„Einlagern …“**:

1. Lagerort wählen, z.B. „Kühlschrank“. BarcodeBuddy wechselt in den Kauf-Modus, oben erscheint ein grüner Balken.
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
der BarcodeBuddy-Oberfläche kommen; die Abfrage erscheint auf jeder geöffneten Screen-Seite.

### Barcodescanner / Tastatur anbinden

In der Add-on-Konfiguration:

1. `ATTACH_BARCODESCANNER` aktivieren
2. Unter `SCANNER_DEVICE` die gewünschte Tastatur bzw. den Scanner aus der Liste wählen
3. Add-on neu starten

Home Assistant gibt dem Add-on nur auf das gewählte Eingabegerät Zugriff; es wird an BarcodeBuddy übergeben.
Erweiterter Hardware-Zugriff (`full_access`) ist dafür nicht nötig.

### Datenablage und Update von Version 0.4.x

Ab Version 0.5.0 liegen die BarcodeBuddy-Daten im eigenen Add-on-Ordner
(`/addon_configs/<id>_bbuddy-grocy/data`) statt in der Home-Assistant-Konfiguration.
Beim ersten Start nach dem Update werden `barcodebuddy.db`, `users.db` und `config.php`
automatisch aus `<HA-Konfiguration>/data` übernommen. Danach können die alten Ordner
`data` und `keys` in der Home-Assistant-Konfiguration gelöscht werden (vorher prüfen, dass
sie nicht von etwas anderem genutzt werden).

### Sicherheit

- Die Home-Assistant-Konfiguration ist nur noch lesend eingebunden (für die Datenübernahme).
- Ingress ist nur für Administratoren sichtbar und nur vom Home-Assistant-Supervisor erreichbar.
- Der direkte Port `8621` ist unverschlüsselt (HTTP). Wird er nicht gebraucht, unter *Netzwerk* leeren.
  `disable_auth` nur aktivieren, wenn niemand Unbefugtes das Netzwerk erreicht.

## Contributors
<a href="https://github.com/forceu/barcodebuddy-homeassistant/graphs/contributors">
  <img src="https://contributors-img.web.app/image?repo=forceu/barcodebuddy-homeassistant" />
</a>

## Aufbau

| Pfad | Inhalt |
|---|---|
| `barcodebuddy/` | Barcode Buddy 1.9.0.1 mit den Erweiterungen dieses Add-ons (als `git subtree` übernommen) |
| `rootfs/` | Konfiguration im Container: nginx (direkter Port und Ingress), PHP-FPM, Redis, s6-Dienste |
| `Dockerfile`, `build.yaml` | Image auf Basis von `ghcr.io/home-assistant/<arch>-base` (Alpine, s6-overlay, bashio), PHP 8.4 |
| `config.yaml`, `translations/` | Add-on-Konfiguration und Optionstexte |
| `.github/workflows/build.yaml` | Baut das Image bei jedem Pull Request für alle Architekturen und testet es auf amd64 |

Im Container laufen als s6-Dienste: `nginx`, `php-fpm`, `redis`, `websocket` (Live-Anzeige der Screen-Seite),
`cron` (Hintergrundjob alle 2 Minuten) und `grabber` (Barcodescanner am Host). Vorher bereitet `init-bbuddy`
Datenordner, Optionen, Zeitzone und die Datenübernahme aus alten Versionen vor.

Die Erweiterungen in `barcodebuddy/`:

- `incl/haAutoCreate.inc.php`: Produkte aus Open Food Facts anlegen, Bilder nachladen, Fortschrittsmeldungen
- `incl/haStorage.inc.php`, `ha_storage.php`, `incl/js/haStorage.js`: Einlagern, MHD-Abfrage, Mengenkorrektur
- `screen.php`: neu gestaltete Screen-Seite
- Einhängepunkte in `incl/processing.inc.php`, `incl/api.inc.php` und `cron.php`; Add-on-Optionen in
  `incl/configProcessing.inc.php`; Scanner-Skript `example/grabInput.sh`

### Neue Barcode-Buddy-Version übernehmen

```sh
git subtree pull --prefix=barcodebuddy https://github.com/Forceu/barcodebuddy.git <tag> --squash
```

Konflikte mit den eigenen Änderungen zeigt Git dabei wie bei einem normalen Merge an.

## License

- `barcodebuddy/`: Barcode Buddy von Marc Ole Bulling, [AGPL-3.0](barcodebuddy/LICENSE.md). Die Änderungen
  dieses Add-ons in diesem Ordner stehen ebenfalls unter der AGPL-3.0; der Quellcode ist in der Fußzeile der
  Weboberfläche verlinkt.
- Alle übrigen Dateien: MIT (siehe [LICENSE](LICENSE)).

## Donations

As with all Free software, the power is less in the finances and more in the collective efforts. I really appreciate every pull request and bug report offered up by BarcodeBuddy's users, so please keep that stuff coming. If however, you're not one for coding/design/documentation, and would like to contribute financially, you can do so with the link below. Every help is very much appreciated!

[![paypal](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=donate@bulling.mobi&lc=US&item_name=BarcodeBuddy&no_note=0&cn=&currency_code=EUR&bn=PP-DonationsBF:btn_donateCC_LG.gif:NonHosted) [![LiberaPay](https://img.shields.io/badge/Donate-LiberaPay-green.svg)](https://liberapay.com/MBulling/donate)
