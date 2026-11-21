# Changelog

## 1.2.2

- Produktnamen ohne HTML-Tags: Namen aus Open Food Facts und anderen Suchquellen, aus Grocy und in der Liste
  der unbekannten Barcodes, auf der Screen-Seite und im Protokoll werden ohne `<b>`, `&lt;i&gt;` usw. angezeigt

## 1.2.1

- Einstellungen: lange Checkbox-Texte überlappen nicht mehr, Untertitel im Dunkelmodus lesbar
- Fehlende Übersetzungen ergänzt (Texte nach Skript-Blöcken und mit geschützten Leerzeichen)

## 1.2.0

- Weboberfläche auf Deutsch (Option „Sprache“ = `de`)
- Produkt bearbeiten auf der Screen-Seite: Lagerort und Mengeneinheit direkt nach dem Einkauf ändern,
  inklusive Umrechnung, wenn schon Bestand gebucht ist
- Fertige Images aus der GitHub Container Registry: Updates werden heruntergeladen statt auf dem Gerät gebaut
- Kein Zugriff mehr auf die Home-Assistant-Konfiguration; die Datenübernahme aus Versionen vor 0.5.0 entfällt
- Automatische Prüfung auf neue Barcode-Buddy-Versionen und automatische Tests im Repository

## 1.1.0

- Neues, modernes Erscheinungsbild der Weboberfläche: dunkle Seitenleiste mit Icons, Karten, Tabellen und
  Knöpfe neu gestaltet, Hell-/Dunkelmodus nach Systemeinstellung
- Kauf-Modus auf der Screen-Seite fragt nach dem Lagerort, damit immer das MHD abgefragt wird; neue Auswahl
  „Standard-Lagerort des Produkts“
- Barcode Buddy Federation entfernt
- Aufgeräumt: ungenutzte Beispiele und Bilder entfernt, neue Dokumentation (DOCS.md) und dieser Changelog

## 1.0.0

- Barcode Buddy liegt im Repository, alle Erweiterungen sind direkt im Code
- Image auf dem offiziellen Home-Assistant-Basisimage mit s6-Diensten, PHP 8.4
- Add-on-Optionen wirken auch für Scanner und Hintergrundjob
- Automatischer Build-Test für alle Architekturen

## 0.10.0

- Screen-Seite zeigt „Wird verarbeitet …“ mit Fortschritt bis zum Ergebnis
- „Menge“ korrigiert den zuletzt gebuchten Einkauf in Grocy
- Zurück-Pfeil entfernt, alle Icons eingebettet

## 0.9.0

- MHD-Eingabe über eigene Zifferntastatur, viele Datumsformate, nur Monat = erster Tag des Monats

## 0.8.x

- Neu gestaltete Screen-Seite für Touchscreens
- Produktbilder werden im Hintergrund nachgeladen, wenn der Bildserver nicht erreichbar ist

## 0.7.0

- Einlagerungsmodus mit Abfrage des Mindesthaltbarkeitsdatums

## 0.6.0

- Unbekannte Produkte werden automatisch aus Open Food Facts in Grocy angelegt

## 0.5.0

- Sicherheits-Härtung: kein `full_access` mehr, Daten im eigenen Add-on-Ordner, festgelegtes Basis-Image

## 0.4.x

- Home Assistant Ingress, Übersetzungen der Optionen
