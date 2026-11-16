# Barcode Buddy für Home Assistant

[![Add-on bauen und testen](https://github.com/n15c/barcodebuddy-homeassistant/actions/workflows/build.yaml/badge.svg)](https://github.com/n15c/barcodebuddy-homeassistant/actions/workflows/build.yaml)
![aarch64](https://img.shields.io/badge/aarch64-yes-green.svg)
![amd64](https://img.shields.io/badge/amd64-yes-green.svg)
![armv7](https://img.shields.io/badge/armv7-yes-green.svg)
![i386](https://img.shields.io/badge/i386-yes-green.svg)

Home-Assistant-Add-on für [Barcode Buddy](https://github.com/Forceu/barcodebuddy): Barcodes scannen und
[Grocy](https://grocy.info) automatisch pflegen.

## Funktionen

- **Unbekannte Produkte automatisch anlegen** – mit Name, Bild, Nährwerten, Zutaten und Allergenen aus
  [Open Food Facts](https://world.openfoodfacts.org)
- **Einlagern mit MHD-Abfrage** – Lagerort wählen, scannen, aufgedrucktes Datum auf der Bildschirmtastatur eintippen
- **Touchscreen-Seite** – große Kacheln für alle Modi, Live-Anzeige mit Fortschritt, Mengenkorrektur, Hell-/Dunkelmodus
- **Moderne Weboberfläche** direkt in Home Assistant (Ingress), ohne extra Login
- **Barcodescanner am Home-Assistant-Host** (USB-Scanner oder Tastatur)
- Läuft auf aarch64, amd64, armv7 und i386

Die ausführliche Anleitung steht in der [Dokumentation](DOCS.md) (in Home Assistant im Reiter *Dokumentation*
des Add-ons), Änderungen im [Changelog](CHANGELOG.md).

## Installation

[![Repository zu Home Assistant hinzufügen](https://my.home-assistant.io/badges/supervisor_add_addon_repository.svg)](https://my.home-assistant.io/redirect/supervisor_add_addon_repository/?repository_url=https%3A%2F%2Fgithub.com%2Fn15c%2Fbarcodebuddy-homeassistant)

Oder von Hand: *Einstellungen → Add-ons → Add-on-Store → ⋮ → Repositories* und
`https://github.com/n15c/barcodebuddy-homeassistant` hinzufügen. Danach „Barcode Buddy for Grocy“ installieren.

## Aufbau

| Pfad | Inhalt |
|---|---|
| `barcodebuddy/` | Barcode Buddy 1.9.0.1 mit den Erweiterungen dieses Add-ons (`git subtree`) |
| `rootfs/` | Container-Konfiguration: nginx (direkter Port und Ingress), PHP-FPM, Redis, s6-Dienste |
| `Dockerfile`, `build.yaml` | Image auf `ghcr.io/home-assistant/<arch>-base` (Alpine, s6-overlay, bashio), PHP 8.4 |
| `config.yaml`, `translations/` | Add-on-Konfiguration und Optionstexte |
| `DOCS.md`, `CHANGELOG.md` | Anleitung und Änderungen (werden in Home Assistant angezeigt) |
| `.github/workflows/build.yaml`, `test/` | Baut das Image für alle Architekturen und testet es auf amd64 |

Im Container laufen die s6-Dienste `nginx`, `php-fpm`, `redis`, `websocket` (Live-Anzeige der Screen-Seite),
`cron` (Hintergrundjob alle 2 Minuten) und `grabber` (Barcodescanner). Vorher bereitet `init-bbuddy`
Datenordner, Optionen, Zeitzone und die Datenübernahme aus alten Versionen vor.

Erweiterungen in `barcodebuddy/`:

| Datei | Zweck |
|---|---|
| `incl/haAutoCreate.inc.php` | Produkte aus Open Food Facts anlegen, Bilder nachladen, Fortschrittsmeldungen |
| `incl/haStorage.inc.php`, `ha_storage.php`, `incl/js/haStorage.js` | Einlagern, MHD-Abfrage, Mengenkorrektur |
| `screen.php` | Touchscreen-Seite |
| `incl/css/theme.css`, `incl/webui.inc.php` | Modernes Erscheinungsbild und Navigation |
| `incl/configProcessing.inc.php` | Add-on-Optionen |
| `example/grabInput.sh` | Scanner-Skript |

Gegenüber dem Original entfernt: Barcode Buddy Federation.

## Entwicklung

- Pull Requests werden automatisch gebaut und getestet (GitHub Actions).
- Neue Barcode-Buddy-Version übernehmen:

  ```sh
  git subtree pull --prefix=barcodebuddy https://github.com/Forceu/barcodebuddy.git <tag> --squash
  ```

  Konflikte mit den eigenen Änderungen zeigt Git wie bei einem normalen Merge an.

## Lizenz

- `barcodebuddy/`: Barcode Buddy von Marc Ole Bulling, [AGPL-3.0](barcodebuddy/LICENSE.md). Die Änderungen
  dieses Add-ons in diesem Ordner stehen ebenfalls unter der AGPL-3.0; der Quellcode ist in der Fußzeile der
  Weboberfläche verlinkt.
- Alle übrigen Dateien: MIT (siehe [LICENSE](LICENSE)).
- Produktdaten von Open Food Facts: [ODbL](https://opendatacommons.org/licenses/odbl/1-0/).

Barcode Buddy wird von Marc Ole Bulling entwickelt –
[Unterstützung für das Original](https://github.com/Forceu/barcodebuddy#donations).
