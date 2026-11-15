#!/usr/bin/with-contenv sh
# Liest die Home-Assistant-Add-on-Optionen und setzt daraus Umgebungsvariablen
# fuer BarcodeBuddy (ATTACH_BARCODESCANNER, SCANNER_DEVICE).

OPTIONS=/data/options.json
[ -f "$OPTIONS" ] || exit 0

opt() {
  php -r '$o = json_decode(file_get_contents($argv[1]), true);
          $v = $o[$argv[2]] ?? "";
          echo is_bool($v) ? ($v ? "true" : "false") : $v;' "$OPTIONS" "$1"
}

# s6-overlay v2 und v3 nutzen unterschiedliche Pfade
for ENVDIR in /var/run/s6/container_environment /run/s6/container_environment; do
  [ -d "$ENVDIR" ] && break
done

setenv() {
  export "$1=$2"
  [ -d "$ENVDIR" ] && printf '%s' "$2" > "$ENVDIR/$1"
}

ATTACH="$(opt ATTACH_BARCODESCANNER)"
DEVICE="$(opt SCANNER_DEVICE)"

if [ "$ATTACH" = "true" ]; then
  if [ -z "$DEVICE" ]; then
    echo "[ha-options] ATTACH_BARCODESCANNER ist aktiv, aber kein SCANNER_DEVICE gewaehlt." >&2
    setenv ATTACH_BARCODESCANNER false
  elif [ ! -e "$DEVICE" ]; then
    echo "[ha-options] Gewaehltes Geraet $DEVICE existiert im Container nicht." >&2
    setenv ATTACH_BARCODESCANNER false
  else
    echo "[ha-options] Scanner/Tastatur wird verwendet: $DEVICE"
    setenv ATTACH_BARCODESCANNER true
    setenv SCANNER_DEVICE "$DEVICE"
  fi
else
  setenv ATTACH_BARCODESCANNER false
fi
