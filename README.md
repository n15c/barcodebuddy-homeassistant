# BarcodeBuddy Homeassistant Docker Image


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


This is the docker repo optimized for Home Assistant of [BarcodeBuddy](https://github.com/Forceu/barcodebuddy).

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

Findet Open Food Facts nichts oder schlägt das Anlegen fehl, landet der Barcode wie bisher in der Liste
der unbekannten Barcodes. Barcodes, die dort schon stehen, werden nicht automatisch angelegt.
Abschalten lässt sich die Funktion mit `auto_create_products: false`.

Hinweis: Die Energie wird pro Packung eingetragen, weil Grocy sie pro Bestandsmengeneinheit erwartet.
Wer als Mengeneinheit z.B. Gramm verwendet, sollte den Wert in Grocy anpassen. Die Daten von
Open Food Facts stehen unter der [ODbL-Lizenz](https://opendatacommons.org/licenses/odbl/1-0/).

### Einlagern mit Abfrage des Mindesthaltbarkeitsdatums

Auf der Screen-Seite (in BarcodeBuddy oben „Screen“) gibt es im Modus-Menü (Einkaufswagen-Taste)
den Punkt **„Einlagern …“**:

1. Lagerort wählen, z.B. „Kühlschrank“. BarcodeBuddy wechselt in den Kauf-Modus, oben erscheint ein grüner Balken.
2. Alle Produkte scannen. Jedes wird in Grocy **an diesem Lagerort** eingebucht. Neue Produkte, die automatisch
   aus Open Food Facts angelegt werden, bekommen ihn auch als Standard-Lagerort.
3. Nach jedem Scan fragt ein Dialog das **Mindesthaltbarkeitsdatum** ab:
   - Eingabe über die Zifferntastatur: `151226` oder `15.12.26` → 15.12.2026, `1226` oder `12/26` → 31.12.2026 (Monatsende)
   - Schnelltasten (+3 Tage, +1 Woche, … +1 Jahr), „Wie zuletzt“, Kalender
   - „Läuft nicht ab“ oder „Überspringen“ (behält das Standard-Datum des Produkts)

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

## License
The MIT License (MIT)

Based on: https://github.com/linuxserver/docker-grocy

## Donations

As with all Free software, the power is less in the finances and more in the collective efforts. I really appreciate every pull request and bug report offered up by BarcodeBuddy's users, so please keep that stuff coming. If however, you're not one for coding/design/documentation, and would like to contribute financially, you can do so with the link below. Every help is very much appreciated!

[![paypal](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=donate@bulling.mobi&lc=US&item_name=BarcodeBuddy&no_note=0&cn=&currency_code=EUR&bn=PP-DonationsBF:btn_donateCC_LG.gif:NonHosted) [![LiberaPay](https://img.shields.io/badge/Donate-LiberaPay-green.svg)](https://liberapay.com/MBulling/donate)
