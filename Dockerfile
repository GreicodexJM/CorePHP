# =============================================================================
# CorePHP — Base Docker Image
# PHP 8.4 CLI (Alpine) + RoadRunner + hardened php.ini + safe std library
# =============================================================================
# Usage:
#   docker build -t corephp-vm:latest .
#   FROM corephp-vm:latest   ← in your project's Dockerfile
# =============================================================================

FROM php:8.4-cli-alpine AS base

LABEL maintainer="CorePHP"
LABEL description="CorePHP: Persistent, hardened PHP 8.4 runtime with RoadRunner"
LABEL version="2.0.0"

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
        curl-dev \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        linux-headers \
    # Install PHP extensions
    # Note: curl is already bundled in php:8.4-cli-alpine; do not reinstall
    # Note: ext-sockets is REQUIRED by spiral/roadrunner-worker; without it the
    #       root `composer update` fails and /app/vendor never ships → every
    #       RoadRunner worker dies at startup. It needs linux-headers to compile.
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        intl \
        zip \
        mbstring \
        sockets \
    # Clean up build deps to minimize image size
    && apk del .build-deps \
    && rm -rf /tmp/pear

# ---------------------------------------------------------------------------
# RoadRunner binary
# ---------------------------------------------------------------------------
ARG RR_VERSION=2024.1.5
# TARGETARCH is injected automatically by Docker BuildKit for multi-platform builds.
# Declaring it here activates the injection (values: amd64, arm64, etc.).
# RoadRunner release filenames match Docker's TARGETARCH values exactly.
ARG TARGETARCH
# TARGETARCH is only auto-populated by BuildKit (docker buildx / CI with
# --platform). Under the classic builder (`docker build` without BuildKit) it is
# empty, which produced a `...linux-.tar.gz` 404 and broke `make build` locally.
# Fall back to deriving the arch from `uname -m` so both builders work.
RUN ARCH="${TARGETARCH:-$(uname -m | sed -e 's/x86_64/amd64/' -e 's/aarch64/arm64/')}" \
    && echo "RoadRunner arch: ${ARCH}" \
    && mkdir -p /tmp/rr-extract \
    && curl -sSfL \
    "https://github.com/roadrunner-server/roadrunner/releases/download/v${RR_VERSION}/roadrunner-${RR_VERSION}-linux-${ARCH}.tar.gz" \
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
# Both auto_prepend_file and disable_functions are cleared here:
#   - auto_prepend_file: bootstrap.php requires Psr\Log which isn't installed yet
#   - disable_functions: Composer requires proc_open to resolve dependencies;
#     the hardened php.ini disables it, so we must override it for this step.
# ---------------------------------------------------------------------------
COPY opt/corephp-vm/ /opt/corephp-vm/
RUN cd /opt/corephp-vm/std \
    && php -d auto_prepend_file="" -d disable_functions="" /usr/local/bin/composer update \
        --no-dev --optimize-autoloader --no-interaction

# ---------------------------------------------------------------------------
# Application
# ---------------------------------------------------------------------------
WORKDIR /app
COPY composer.json composer.lock* ./
# Use `composer update` (not install): there is no committed root composer.lock,
# so `install` has nothing to install from and would leave /app/vendor empty —
# which crashed every RoadRunner worker at startup (worker.php requires
# /app/vendor/autoload.php). `update` resolves straight from composer.json, the
# same pattern used for the std library above.
# NOTE: the failure is intentionally NOT swallowed — a build that cannot install
# the RoadRunner runtime must fail loudly, not ship a broken image.
RUN php -d auto_prepend_file="" -d disable_functions="" /usr/local/bin/composer update \
    --no-dev --optimize-autoloader --no-interaction

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
