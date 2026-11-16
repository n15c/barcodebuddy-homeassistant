# Barcode Buddy als Home-Assistant-Add-on.
# Basis: offizielles Home-Assistant-Image (Alpine, s6-overlay, bashio), siehe build.yaml.
ARG BUILD_FROM
FROM ${BUILD_FROM}

ARG BUILD_ARCH
ARG BUILD_VERSION

# PHP 8.4 gibt es fuer alle Architekturen (armv7/i386 nur bis Alpine 3.22)
RUN apk add --no-cache \
        evtest \
        nginx \
        redis \
        php84 \
        php84-ctype \
        php84-curl \
        php84-fileinfo \
        php84-fpm \
        php84-gettext \
        php84-mbstring \
        php84-openssl \
        php84-pdo \
        php84-pdo_sqlite \
        php84-pecl-redis \
        php84-session \
        php84-simplexml \
        php84-sockets \
        php84-sqlite3 \
        php84-xml \
        php84-xmlwriter \
    && ln -sf /usr/bin/php84 /usr/bin/php \
    && ln -sf /usr/bin/php84 /usr/bin/php8 \
    && rm -f /etc/nginx/http.d/default.conf /etc/php84/php-fpm.d/www.conf \
    && addgroup -S barcodebuddy \
    && adduser -S -D -H -h /config -s /sbin/nologin -G barcodebuddy barcodebuddy

# Barcode Buddy (Ordner barcodebuddy/, AGPL-3.0) und Add-on-Dateien
COPY barcodebuddy/ /app/bbuddy/
COPY rootfs /

# Daten liegen im Add-on-Konfigurationsordner (/config = /addon_configs/<id>_bbuddy-grocy)
RUN rm -rf /app/bbuddy/data \
    && ln -s /config/data /app/bbuddy/data \
    && chown -R barcodebuddy:barcodebuddy /app/bbuddy \
    && chmod +x /app/bbuddy/example/grabInput.sh /etc/s6-overlay/s6-rc.d/*/run \
    && php -l /app/bbuddy/index.php > /dev/null \
    && nginx -t -c /etc/nginx/nginx.conf \
    && php-fpm84 -t -y /etc/php84/php-fpm.conf

LABEL \
    io.hass.name="Barcode Buddy for Grocy" \
    io.hass.description="Barcode system for Grocy" \
    io.hass.arch="${BUILD_ARCH}" \
    io.hass.type="addon" \
    io.hass.version="${BUILD_VERSION}"
