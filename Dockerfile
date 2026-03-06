# =============================================================================
# CorePHP (PHP-JVM) — Base Docker Image
# PHP 8.3 CLI (Alpine) + RoadRunner + runkit7 + hardened php.ini
# =============================================================================
# Usage:
#   docker build -t php-jvm:latest .
#   FROM php-jvm:latest   ← in your project's Dockerfile
# =============================================================================

FROM php:8.3-cli-alpine AS base

LABEL maintainer="CorePHP"
LABEL description="PHP-JVM: Persistent, hardened PHP 8.3 runtime with RoadRunner"
LABEL version="1.0.0"

# ---------------------------------------------------------------------------
# System dependencies
# ---------------------------------------------------------------------------
RUN apk add --no-cache \
        libcurl \
        icu-libs \
        libzip \
        oniguruma \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        git \
        curl-dev \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
    # Install PHP extensions
    # Note: curl is already bundled in php:8.3-cli-alpine; do not reinstall
    && docker-php-ext-install -j$(nproc) \
        intl \
        zip \
        mbstring \
    # ---------------------------------------------------------------------------
    # Install runkit7 from GitHub source
    # (PECL registry does not carry runkit7; must build from source)
    # runkit.internal_override = 1 in php.ini is required to override built-ins
    # ---------------------------------------------------------------------------
    && git clone --depth=1 https://github.com/runkit7/runkit7.git /tmp/runkit7 \
    && cd /tmp/runkit7 \
    && phpize \
    && ./configure \
    && make -j$(nproc) \
    && make install \
    && docker-php-ext-enable runkit7 \
    && rm -rf /tmp/runkit7 \
    # Clean up build deps to minimize image size
    && apk del .build-deps \
    && rm -rf /tmp/pear

# ---------------------------------------------------------------------------
# RoadRunner binary
# ---------------------------------------------------------------------------
ARG RR_VERSION=2024.1.5
RUN mkdir -p /tmp/rr-extract \
    && curl -sSfL \
    "https://github.com/roadrunner-server/roadrunner/releases/download/v${RR_VERSION}/roadrunner-${RR_VERSION}-linux-amd64.tar.gz" \
    -o /tmp/rr.tar.gz \
    && tar -xzf /tmp/rr.tar.gz -C /tmp/rr-extract --strip-components=1 \
    && mv /tmp/rr-extract/rr /usr/local/bin/rr \
    && chmod +x /usr/local/bin/rr \
    && rm -rf /tmp/rr.tar.gz /tmp/rr-extract

# ---------------------------------------------------------------------------
# Composer
# ---------------------------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# ---------------------------------------------------------------------------
# PHP configuration
# ---------------------------------------------------------------------------
COPY config/php.ini /usr/local/etc/php/php.ini

# ---------------------------------------------------------------------------
# std library — install globally
# auto_prepend_file is disabled here because bootstrap.php depends on
# Psr\Log which is not yet installed at this build stage.
# ---------------------------------------------------------------------------
COPY opt/php-jvm/ /opt/php-jvm/
RUN cd /opt/php-jvm/std \
    && php -d auto_prepend_file="" /usr/local/bin/composer install \
        --no-dev --optimize-autoloader --no-interaction

# ---------------------------------------------------------------------------
# Application
# ---------------------------------------------------------------------------
WORKDIR /app
COPY composer.json composer.lock* ./
RUN php -d auto_prepend_file="" /usr/local/bin/composer install \
    --no-dev --optimize-autoloader --no-interaction 2>/dev/null || true

COPY . .

# Log directory
RUN mkdir -p /var/log/php && touch /var/log/php/error.log

# ---------------------------------------------------------------------------
# Expose & Healthcheck
# ---------------------------------------------------------------------------
EXPOSE 8080

HEALTHCHECK --interval=10s --timeout=5s --start-period=10s --retries=3 \
    CMD rr http:status || exit 1

# ---------------------------------------------------------------------------
# Default command — start RoadRunner
# ---------------------------------------------------------------------------
CMD ["rr", "serve", "-c", "/app/.rr.yaml"]
