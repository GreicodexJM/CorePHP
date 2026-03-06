#!/usr/bin/env sh
# =============================================================================
# CorePHP (PHP-JVM) — CI Test Script
# Runs PHPUnit test suite for the std library
# Execute inside the Docker container: docker compose exec app sh ci/test.sh
# =============================================================================

set -e

WORKDIR="/app"
STD_DIR="${WORKDIR}/opt/php-jvm/std"
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo ""
echo "=============================================="
echo "  CorePHP CI — Test Suite"
echo "=============================================="

# ---------------------------------------------------------------------------
# Locate PHPUnit
# ---------------------------------------------------------------------------
if [ -f "${WORKDIR}/vendor/bin/phpunit" ]; then
    PHPUNIT="${WORKDIR}/vendor/bin/phpunit"
elif [ -f "${STD_DIR}/vendor/bin/phpunit" ]; then
    PHPUNIT="${STD_DIR}/vendor/bin/phpunit"
else
    echo "${RED}✗ phpunit not found. Run composer install first.${NC}"
    exit 1
fi

# ---------------------------------------------------------------------------
# Run std library unit tests
# ---------------------------------------------------------------------------
echo ""
echo "→ [1/1] Running PHPUnit (std library)..."
echo ""

${PHPUNIT} \
    --configuration="${STD_DIR}/phpunit.xml" \
    --colors=always \
    --testdox \
    && echo "" \
    && echo "${GREEN}=============================================="  \
    && echo "  All tests passed ✓" \
    && echo "==============================================${NC}" \
    && echo "" \
    || { \
        echo ""; \
        echo "${RED}=============================================="  ; \
        echo "  Tests FAILED ✗"; \
        echo "==============================================${NC}"; \
        echo ""; \
        exit 1; \
    }
