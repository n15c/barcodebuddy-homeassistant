#!/bin/sh
# Liest die Home-Assistant-Add-on-Optionen und exportiert sie als
# Umgebungsvariablen, danach wird der originale Start (/app/supervisor) ausgefuehrt.

OPTIONS=/data/options.json

# Einfacher Leser fuer flaches JSON (ohne jq/php): gibt den Wert zu einem Schluessel aus
opt() {
  sed -n 's/.*"'"$1"'"[[:space:]]*:[[:space:]]*"\{0,1\}\([^",}]*\)"\{0,1\}.*/\1/p' "$OPTIONS" | head -n1
}

if [ -f "$OPTIONS" ]; then
  # Allgemeine Add-on-Optionen an BarcodeBuddy weiterreichen. BBUDDY_*-Variablen
  # schreibt der Supervisor als fastcgi_param in die Konfiguration des direkten Ports.
  # (Beim Ingress-Zugang ist die Anmeldung immer aus, das uebernimmt Home Assistant.)
  [ "$(opt disable_auth)" = "true" ] && export BBUDDY_DISABLE_AUTHENTICATION=true
  [ "$(opt debug)" = "true" ] && export BBUDDY_IS_DEBUG=true
  [ "$(opt curl_allow_insecure_ssl_ca)" = "true" ] && export IGNORE_SSL_CA=true
  [ "$(opt curl_allow_insecure_ssl_host)" = "true" ] && export IGNORE_SSL_HOST=true

  ATTACH="$(opt ATTACH_BARCODESCANNER)"
  DEVICE="$(opt SCANNER_DEVICE)"
  DEBUG="$(opt SCANNER_DEBUG)"

  # Diagnose: 30 Sekunden lang alle Eingabegeraete mitlesen (ohne Grab) und Tastendruecke
  # mit dem jeweiligen Geraet ins Log schreiben. Waehrenddessen einen Barcode scannen.
  if [ "$DEBUG" = "true" ]; then
    if command -v evtest >/dev/null 2>&1; then
      echo "[ha-entrypoint] SCANNER_DEBUG: 30 Sekunden lang Tastendruecke aller Geraete anzeigen - jetzt einen Barcode scannen!"
      for d in ${INPUT_DIR:-/dev/input}/event*; do
        [ -e "$d" ] || continue
        ( timeout 30 evtest "$d" 2>&1 | awk -v p="[ha-entrypoint][$(basename "$d")] " '/EV_KEY.*value 1/ { print p $0; fflush() }' ) &
      done
      wait
      echo "[ha-entrypoint] SCANNER_DEBUG beendet. Im Log oben steht, welches eventX die Zeichen sendet."
    else
      echo "[ha-entrypoint] SCANNER_DEBUG: evtest nicht im Image gefunden." >&2
    fi
  fi

  if [ "$ATTACH" = "true" ]; then
    if [ -z "$DEVICE" ]; then
      echo "[ha-entrypoint] ATTACH_BARCODESCANNER aktiv, aber kein SCANNER_DEVICE gewaehlt - Scanner wird nicht angebunden." >&2
    elif [ ! -e "$DEVICE" ]; then
      echo "[ha-entrypoint] Geraet $DEVICE existiert im Container nicht - Scanner wird nicht angebunden." >&2
    else
      # Symlinks (z.B. /dev/input/by-id/...) zu /dev/input/eventX aufloesen
      DEVICE="$(readlink -f "$DEVICE")"
      echo "[ha-entrypoint] Scanner/Tastatur: $DEVICE"

      # Diagnose: Name des gewaehlten Geraets und alle vorhandenen Eingabegeraete ins Log
      EV="$(basename "$DEVICE")"
      if [ -r /proc/bus/input/devices ]; then
        echo "[ha-entrypoint] Gewaehltes Geraet entspricht:"
        awk -v ev="$EV" 'BEGIN{RS="";FS="\n"} $0 ~ ("Handlers=.*" ev "( |$)") {for(i=1;i<=NF;i++) if($i ~ /^N:/) print "[ha-entrypoint]   " $i}' /proc/bus/input/devices
        echo "[ha-entrypoint] Alle Eingabegeraete:"
        awk 'BEGIN{RS="";FS="\n"} {n="";h=""; for(i=1;i<=NF;i++){ if($i ~ /^N:/) n=$i; if($i ~ /^H:/) h=$i } print "[ha-entrypoint]   " n " | " h}' /proc/bus/input/devices
      fi
      command -v evtest >/dev/null 2>&1 && echo "[ha-entrypoint] evtest vorhanden" || echo "[ha-entrypoint] evtest NICHT im Image gefunden"
      export ATTACH_BARCODESCANNER=true
      export SCANNER_DEVICE="$DEVICE"

      # grabInput.sh wird vom Supervisor ohne Argument gestartet und bricht ab, wenn
      # mehrere Geraete in /dev/input/ liegen. Shim: gewaehltes Geraet als Argument uebergeben.
      GRAB="$(find / -xdev -name grabInput.sh -not -name 'grabInput.real.sh' 2>/dev/null | head -n1)"
      if [ -z "$GRAB" ]; then
        echo "[ha-entrypoint] grabInput.sh nicht gefunden - Geraet kann nicht uebergeben werden." >&2
      else
        REAL="$(dirname "$GRAB")/grabInput.real.sh"
        [ -f "$REAL" ] || mv "$GRAB" "$REAL"
        cp /usr/local/share/ha-grabInput.sh "$GRAB"
        chmod +x "$GRAB" "$REAL"
        echo "[ha-entrypoint] grabInput.sh in $(dirname "$GRAB") durch angepasste Version ersetzt (Geraet: $DEVICE)"
      fi
    fi
  fi
fi

exec /app/supervisor "$@"
