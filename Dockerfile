FROM f0rc3/barcodebuddy:latest

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
RUN chmod +x /etc/cont-init.d/00-ha-options.sh
