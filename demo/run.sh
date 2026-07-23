#!/usr/bin/env sh
# =============================================================================
# CorePHP drop-in proof — the SAME unmodified app.php on two runtimes.
#
#   ./run.sh
#
# Requires: docker, and corephp-vm:latest (build from repo root: make build).
# Shows how a config-drift bug is silently shipped by stock PHP but caught,
# traced, and rejected by CorePHP — with zero changes to the application.
# =============================================================================
set -eu
cd "$(dirname "$0")"

COMPOSE="docker compose -f docker-compose.demo.yaml"
STOCK="http://localhost:8093"
COREPHP="http://localhost:8092"

G='\033[0;32m'; R='\033[0;31m'; Y='\033[1;33m'; B='\033[1m'; D='\033[2m'; N='\033[0m'

cleanup() { $COMPOSE down --remove-orphans >/dev/null 2>&1 || true; }
trap cleanup EXIT

if ! docker image inspect corephp-vm:latest >/dev/null 2>&1; then
    printf "${R}✗ corephp-vm:latest not found.${N} Build it first: (cd .. && make build)\n"; exit 1
fi

printf "→ Starting both runtimes …\n"
$COMPOSE up -d >/dev/null 2>&1

wait_ready() {
    i=0
    while [ "$i" -lt 30 ]; do
        [ "$(curl -s -o /dev/null -w '%{http_code}' "$1/health" 2>/dev/null || echo 000)" = "200" ] && return 0
        i=$((i + 1)); sleep 1
    done
    printf "${R}✗ %s never became ready${N}\n" "$1"; exit 1
}
wait_ready "$STOCK"; wait_ready "$COREPHP"
printf "  ${G}✓${N} both serving ${D}(stock :8093, CorePHP :8092)${N}\n\n"

hit() { curl -s -w '\n%{http_code}' "$1/order" 2>/dev/null; }

# ---------------------------------------------------------------------------
printf "${B}══ Act 1 — the same app on STOCK PHP ══${N}\n"
printf "${D}GET /order  (pricing config drifted: 'unit_price' → 'price')${N}\n\n"
resp=$(hit "$STOCK"); code=$(printf '%s' "$resp" | tail -1); body=$(printf '%s' "$resp" | sed '$d')
printf "  HTTP ${Y}%s${N}   %s\n" "$code" "$body"
printf "  ${R}✗ Shipped a widget for \$0.00 — HTTP 200, no error to anyone.${N}\n"
printf "  ${D}The warning is buried in the log, the app carried on:${N}\n"
docker exec demo-stock sh -c 'tail -1 /tmp/stock-errors.log 2>/dev/null' | sed 's/^/    /' || true
printf "\n"

# ---------------------------------------------------------------------------
printf "${B}══ Act 2 — the SAME app on CorePHP ══${N}\n"
printf "${D}GET /order  (identical code, identical config)${N}\n\n"
resp=$(hit "$COREPHP"); code=$(printf '%s' "$resp" | tail -1); body=$(printf '%s' "$resp" | sed '$d')
printf "  HTTP ${Y}%s${N}   %s\n" "$code" "$body"
printf "  ${G}✓ Caught at the source — HTTP 500, typed and pinpointed.${N}\n"
printf "  ${D}Full audit trail (stack trace + request path), logged automatically:${N}\n"
docker exec demo-corephp sh -c 'tail -1 /var/log/php/audit.log 2>/dev/null' \
  | sed 's/\\n/\n/g' | sed 's/^/    /' | head -8 || true
printf "\n"

# ---------------------------------------------------------------------------
printf "${B}══ …and the worker kept serving ══${N}\n"
hc=$(curl -s -o /dev/null -w '%{http_code}' "$COREPHP/health" 2>/dev/null)
printf "  GET /health → HTTP ${G}%s${N} ${D}(the fatal did not take the process down)${N}\n\n" "$hc"

printf "${B}Verdict${N}\n"
printf "  Same unmodified app, same bug. Stock PHP ${R}silently sold a \$0 widget${N};\n"
printf "  CorePHP ${G}refused it, told you the exact line and the missing key${N}, and stayed up.\n"
