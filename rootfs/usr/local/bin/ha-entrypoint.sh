#!/bin/sh
# Liest die Home-Assistant-Add-on-Optionen und exportiert sie als
# Umgebungsvariablen, danach wird der originale Start (/app/supervisor) ausgefuehrt.

OPTIONS=/data/options.json

# Einfacher Leser fuer flaches JSON (ohne jq/php): gibt den Wert zu einem Schluessel aus
opt() {
  sed -n 's/.*"'"$1"'"[[:space:]]*:[[:space:]]*"\{0,1\}\([^",}]*\)"\{0,1\}.*/\1/p' "$OPTIONS" | head -n1
}

if [ -f "$OPTIONS" ]; then
  ATTACH="$(opt ATTACH_BARCODESCANNER)"
  DEVICE="$(opt SCANNER_DEVICE)"

  if [ "$ATTACH" = "true" ]; then
    if [ -z "$DEVICE" ]; then
      echo "[ha-entrypoint] ATTACH_BARCODESCANNER aktiv, aber kein SCANNER_DEVICE gewaehlt - Scanner wird nicht angebunden." >&2
    elif [ ! -e "$DEVICE" ]; then
      echo "[ha-entrypoint] Geraet $DEVICE existiert im Container nicht - Scanner wird nicht angebunden." >&2
    else
      # Symlinks (z.B. /dev/input/by-id/...) zu /dev/input/eventX aufloesen
      DEVICE="$(readlink -f "$DEVICE")"
      echo "[ha-entrypoint] Scanner/Tastatur: $DEVICE"
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
        cat > "$GRAB" <<'SHIM'
#!/bin/sh
REAL="$(dirname "$0")/grabInput.real.sh"
if [ -z "$1" ] && [ -n "$SCANNER_DEVICE" ]; then
  exec "$REAL" "$SCANNER_DEVICE"
fi
exec "$REAL" "$@"
SHIM
        chmod +x "$GRAB" "$REAL"
        echo "[ha-entrypoint] grabInput.sh in $(dirname "$GRAB") umgeleitet auf $DEVICE"
      fi
    fi
  fi
fi

exec /app/supervisor "$@"
