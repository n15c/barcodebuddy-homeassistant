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
      echo "[ha-entrypoint] Scanner/Tastatur: $DEVICE"
      export ATTACH_BARCODESCANNER=true
      export SCANNER_DEVICE="$DEVICE"
    fi
  fi
fi

exec /app/supervisor "$@"
