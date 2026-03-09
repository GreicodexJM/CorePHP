#!/usr/bin/env sh
# =============================================================================
# CorePHP (PHP-JVM) — CI Lint Script
# Runs PHP-CS-Fixer (dry-run) + PHPStan Level 9
# Execute inside the Docker container: docker compose exec app sh ci/lint.sh
# =============================================================================

set -e

WORKDIR="/app"
STD_DIR="${WORKDIR}/opt/corephp-vm/std"
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m'

echo ""
echo "=============================================="
echo "  CorePHP CI — Lint"
echo "=============================================="

# ---------------------------------------------------------------------------
# Step 1: PHP-CS-Fixer (dry-run — report violations, do not fix)
# ---------------------------------------------------------------------------
echo ""
echo "→ [1/2] Running PHP-CS-Fixer (dry-run)..."

if [ -f "${WORKDIR}/vendor/bin/php-cs-fixer" ]; then
    PHP_CS_FIXER="${WORKDIR}/vendor/bin/php-cs-fixer"
elif [ -f "${STD_DIR}/vendor/bin/php-cs-fixer" ]; then
    PHP_CS_FIXER="${STD_DIR}/vendor/bin/php-cs-fixer"
else
    echo "${RED}✗ php-cs-fixer not found. Run composer install first.${NC}"
    exit 1
fi

${PHP_CS_FIXER} fix \
    --config="${WORKDIR}/.php-cs-fixer.dist.php" \
    --dry-run \
    --diff \
    --no-interaction \
    --ansi \
    && echo "${GREEN}✓ PHP-CS-Fixer: no violations found${NC}" \
    || { echo "${RED}✗ PHP-CS-Fixer: violations found (run make lint-fix to auto-fix)${NC}"; exit 1; }

# ---------------------------------------------------------------------------
# Step 2: PHPStan Level 9
# ---------------------------------------------------------------------------
echo ""
echo "→ [2/2] Running PHPStan Level 9..."

if [ -f "${WORKDIR}/vendor/bin/phpstan" ]; then
    PHPSTAN="${WORKDIR}/vendor/bin/phpstan"
elif [ -f "${STD_DIR}/vendor/bin/phpstan" ]; then
    PHPSTAN="${STD_DIR}/vendor/bin/phpstan"
else
    echo "${RED}✗ phpstan not found. Run composer install first.${NC}"
    exit 1
fi

${PHPSTAN} analyse \
    --configuration="${WORKDIR}/phpstan.neon" \
    --no-progress \
    --no-interaction \
    --ansi \
    && echo "${GREEN}✓ PHPStan Level 9: no errors found${NC}" \
    || { echo "${RED}✗ PHPStan: errors found${NC}"; exit 1; }

echo ""
echo "${GREEN}=============================================="
echo "  All lint checks passed ✓"
echo "==============================================${NC}"
echo ""
