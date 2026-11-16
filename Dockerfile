# Basis-Image ist auf Version und Digest festgelegt; Updates bewusst hier und in build.yaml anheben.
FROM f0rc3/barcodebuddy:v1.9.0.1@sha256:f2496ffce1c2b8216be6a8046255fd546b495ef8c98458b47099710341830d5a

# Build arguments
ARG BUILD_ARCH
ARG BUILD_DATE
ARG BUILD_REF
ARG BUILD_VERSION

# Labels
LABEL \
    io.hass.name="Barcode Buddy for Grocy" \
    io.hass.description="Barcode system for Grocy" \
    io.hass.arch="${BUILD_ARCH}" \
    io.hass.type="addon" \
    io.hass.version=${BUILD_VERSION} 

# Home-Assistant-Optionen -> Umgebungsvariablen (ATTACH_BARCODESCANNER, SCANNER_DEVICE)
COPY rootfs /
RUN chmod +x /usr/local/bin/ha-entrypoint.sh /usr/local/share/ha-grabInput.sh

# Erweiterungen: unbekannte Produkte automatisch aus Open Food Facts in Grocy anlegen,
# Einlagerungsmodus mit MHD-Abfrage, neu gestaltete Screen-Seite (ersetzt die Original-screen.php).
# Vorher pruefen, dass die Original-Seite noch das Live-Protokoll nutzt, auf dem die neue aufbaut.
RUN grep -q 'incl/sse/sse_data.php?getState' /app/bbuddy/screen.php \
 && grep -q 'resultJson.data.substring(0, 1)' /app/bbuddy/screen.php \
 && grep -q "case '4':" /app/bbuddy/screen.php \
 && [ -f /app/bbuddy/incl/js/nosleep.min.js ] && [ -f /app/bbuddy/incl/js/he.js ] \
 || { echo "BarcodeBuddy-screen.php hat sich geaendert - bbuddy/screen.php anpassen" >&2; exit 1; }
COPY bbuddy/ /app/bbuddy/
COPY build/patch-bbuddy.php /tmp/patch-bbuddy.php
RUN [ -x /usr/bin/php ] || ln -s /usr/bin/php8 /usr/bin/php; \
    php /tmp/patch-bbuddy.php \
 && php -l /app/bbuddy/incl/processing.inc.php \
 && php -l /app/bbuddy/incl/api.inc.php \
 && php -l /app/bbuddy/incl/haAutoCreate.inc.php \
 && php -l /app/bbuddy/incl/haStorage.inc.php \
 && php -l /app/bbuddy/ha_storage.php \
 && php -l /app/bbuddy/screen.php \
 && rm /tmp/patch-bbuddy.php
ENTRYPOINT ["/usr/local/bin/ha-entrypoint.sh"]
CMD []
