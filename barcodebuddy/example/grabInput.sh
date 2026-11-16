#!/bin/bash
# Liest Barcodes von einem Scanner (oder einer Tastatur) und uebergibt sie an Barcode Buddy.
#
# Aufruf: grabInput.sh [/dev/input/eventX]
#  - Ohne Argument wird $SCANNER_DEVICE verwendet bzw. das einzige Geraet in /dev/input/
#  - Ein Scan gilt auch ohne KEY_ENTER als abgeschlossen, wenn SCAN_TIMEOUT Sekunden lang
#    keine Taste mehr kommt (fuer Scanner ohne Enter-Suffix)
#  - Barcode Buddy wird als $WWW_USER aufgerufen (s6-setuidgid, sonst sudo)
#
# Im Home-Assistant-Add-on startet der Dienst "grabber" dieses Skript.

SCRIPT_LOCATION="${SCRIPT_LOCATION:="/app/bbuddy/index.php"}"
SERVER_ADDRESS="${SERVER_ADDRESS:="https://your.bbuddy.url/api/"}"
USE_CURL="${USE_CURL:="false"}"
WWW_USER="${WWW_USER:="barcodebuddy"}"
IS_DOCKER="${IS_DOCKER:="true"}"
API_KEY="${API_KEY:="YOUR_API_KEY"}"
SPECIAL_BARCODE="${SPECIAL_BARCODE:="YOUR_CUSTOM_BARCODE"}"
SCAN_TIMEOUT="${SCAN_TIMEOUT:="0.5"}"
PHP_BIN="${PHP_BIN:="$(command -v php || echo /usr/bin/php)"}"

specialAction() {
    echo "Custom barcode scanned"
}

declare -A CODE_MAP_CHAR=( ["(KEY_0)"]="0" \
    ["(KEY_1)"]="1" \
    ["(KEY_2)"]="2" \
    ["(KEY_3)"]="3" \
    ["(KEY_4)"]="4" \
    ["(KEY_5)"]="5" \
    ["(KEY_6)"]="6" \
    ["(KEY_7)"]="7" \
    ["(KEY_8)"]="8" \
    ["(KEY_9)"]="9" \
    ["(KEY_KP0)"]="0" \
    ["(KEY_KP1)"]="1" \
    ["(KEY_KP2)"]="2" \
    ["(KEY_KP3)"]="3" \
    ["(KEY_KP4)"]="4" \
    ["(KEY_KP5)"]="5" \
    ["(KEY_KP6)"]="6" \
    ["(KEY_KP7)"]="7" \
    ["(KEY_KP8)"]="8" \
    ["(KEY_KP9)"]="9" \
    ["(KEY_NUMERIC_0)"]="0" \
    ["(KEY_NUMERIC_1)"]="1" \
    ["(KEY_NUMERIC_2)"]="2" \
    ["(KEY_NUMERIC_3)"]="3" \
    ["(KEY_NUMERIC_4)"]="4" \
    ["(KEY_NUMERIC_5)"]="5" \
    ["(KEY_NUMERIC_6)"]="6" \
    ["(KEY_NUMERIC_7)"]="7" \
    ["(KEY_NUMERIC_8)"]="8" \
    ["(KEY_NUMERIC_9)"]="9" \
    ["(KEY_A)"]="A" \
    ["(KEY_B)"]="B" \
    ["(KEY_C)"]="C" \
    ["(KEY_D)"]="D" \
    ["(KEY_E)"]="E" \
    ["(KEY_F)"]="F" \
    ["(KEY_G)"]="G" \
    ["(KEY_H)"]="H" \
    ["(KEY_I)"]="I" \
    ["(KEY_J)"]="J" \
    ["(KEY_K)"]="K" \
    ["(KEY_L)"]="L" \
    ["(KEY_M)"]="M" \
    ["(KEY_N)"]="N" \
    ["(KEY_O)"]="O" \
    ["(KEY_P)"]="P" \
    ["(KEY_Q)"]="Q" \
    ["(KEY_R)"]="R" \
    ["(KEY_S)"]="S" \
    ["(KEY_T)"]="T" \
    ["(KEY_U)"]="U" \
    ["(KEY_V)"]="V" \
    ["(KEY_W)"]="W" \
    ["(KEY_X)"]="X" \
    ["(KEY_Y)"]="Y" \
    ["(KEY_Z)"]="Z" \
    ["(KEY_DOT)"]="." \
    ["(KEY_KPDOT)"]="." \
    ["(KEY_MINUS)"]="-" \
    ["(KEY_KPMINUS)"]="-" \
    ["(KEY_SLASH)"]="-" \
    ["(KEY_SEMICOLON)"]=":" \
    ["(KEY_ENTER)"]="KEY_ENTER" \
    ["(KEY_KPENTER)"]="KEY_ENTER" )

declare NON_ALLOWED_CHAR="NONE";

if [[ $EUID -ne 0 ]]; then
   echo "This script must be run as root"
   exit 1
fi

if [[ $IS_DOCKER == true ]]; then
   if [[ $(printenv ATTACH_BARCODESCANNER) != "true" ]]; then
        echo "[ScannerConnection] Not starting service, as ATTACH_BARCODESCANNER has not been passed"
        exit 0
   fi
fi

deviceToUse=""
if [ $# -ge 1 ] && [ -n "$1" ]; then
    deviceToUse="$1"
elif [ -n "$SCANNER_DEVICE" ]; then
    deviceToUse="$SCANNER_DEVICE"
else
    nInputEvents=$(ls 2>/dev/null -Ubad1 -- /dev/input/event* | wc -l)
    if [ "$nInputEvents" = 1 ]; then
        deviceToUse=$(ls /dev/input/event* | head -n 1)
    else
        echo "No argument provided and more than one device in /dev/input/"
        echo "Usage: grabInput.sh /dev/input/eventX"
        exit 1
    fi
fi

returnAllowedCharacter() {
  for key in "${!CODE_MAP_CHAR[@]}"; do
        if [[ $1 =~ "$key" && $1 =~ "time" && $1 =~ "value 1" ]]; then
            echo "${CODE_MAP_CHAR[$key]}"
            return
        fi
    done
  echo "$NON_ALLOWED_CHAR"
}

submitScan() {
  local code="$1"
  echo "[ScannerConnection] Received: $code"
  if [[ "$code" == "$SPECIAL_BARCODE" ]]; then
    specialAction
  else
    if [[ $USE_CURL == false ]]; then
        if command -v s6-setuidgid >/dev/null 2>&1; then
            s6-setuidgid "$WWW_USER" "$PHP_BIN" "$SCRIPT_LOCATION" "$code" >/dev/null &
        else
            sudo -H -u "$WWW_USER" "$PHP_BIN" "$SCRIPT_LOCATION" "$code" >/dev/null &
        fi
    else
        curl "${SERVER_ADDRESS}action/scan?apikey=${API_KEY}&add=${code}"
    fi
  fi
}

enteredText=""
echo "[ScannerConnection] Waiting for scanner input ($deviceToUse)"
evtest --grab "$deviceToUse" | while true; do
  if IFS= read -r -t "$SCAN_TIMEOUT" line; then
    key="$(returnAllowedCharacter "{$line}")"
    if [[ "$key" != "$NON_ALLOWED_CHAR" ]]; then
      if [[ "$key" != "KEY_ENTER" ]]; then
        enteredText+="$key"
      else
        [[ -n "$enteredText" ]] && submitScan "$enteredText"
        enteredText=""
      fi
    fi
  else
    rc=$?
    if [ $rc -gt 128 ]; then
      # Timeout: Scan ohne Enter-Suffix abschliessen
      if [[ -n "$enteredText" ]]; then
        submitScan "$enteredText"
        enteredText=""
      fi
    else
      break   # Ende der evtest-Ausgabe
    fi
  fi
done
