#!/usr/bin/env sh
# =============================================================================
# CorePHP CI — corephp audit gate
#
# Dogfoods the project's own compile-time audit on the production code paths.
# Full report is advisory (all severities); the build GATES on HIGH findings.
#
# Excluded on purpose: demo/ and benchmarks/ (intentionally-bad example code)
# and the audit's own tests/fixtures.
# =============================================================================
set -e

ROOT="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
cd "$ROOT"

# Paths that must stay exemplary.
PATHS="opt/corephp-vm/std/src opt/corephp-vm/bootstrap.php worker.php tools/audit/src"

# Ensure the audit tool's dependencies are present (lock-free: no committed lock).
if [ ! -d tools/audit/vendor ]; then
    ( cd tools/audit && composer update --no-interaction --no-progress -q )
fi

AUDIT="php tools/audit/bin/corephp-audit"

echo "=============================================="
echo "  corephp audit — advisory (all severities)"
echo "=============================================="
# shellcheck disable=SC2086
$AUDIT $PATHS || true

echo ""
echo "=============================================="
echo "  corephp audit — GATE (fail on HIGH)"
echo "=============================================="
# shellcheck disable=SC2086
$AUDIT --min-severity=high $PATHS
echo "✓ No HIGH-severity SAFE/SECURE/STABLE findings in production code."
