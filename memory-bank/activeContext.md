# Active Context: CorePHP (PHP-JVM)

## Current Status
🟢 **All quality gates passing** — PHPStan Level 9 (`[OK] No errors`), PHPUnit (`OK 208 tests, 271 assertions`), CS-Fixer (`0 violations`)

## Last Session (2026-03-09 — Improvement & Cleanup)

### What Was Done
Systematic audit of the project — found and fixed 2 security bugs, 1 runtime bug, 11 PHPUnit deprecations, 1 PHPUnit warning, and performed maintenance cleanup.

**Security fixes:**
- `config/php.ini`: `auto_prepend_file` had stale path `/opt/php-jvm/` → `/opt/corephp-vm/` (bootstrap was never running in production)
- `config/php.ini`: `disable_functions` multiline format broken by PHP ini parser — only `unserialize,` was applied; consolidated to single line so all 34 functions are now blocked

**Runtime bug:**
- `FunctionOverrider.php`: json_decode/encode override bodies referenced `new \Psl\Json\Exception(...)` — not instantiable in PSL 4.x; simplified to use `JSON_THROW_ON_ERROR` which throws built-in `\JsonException`

**Test suite:**
- Converted all 11 `@covers`/`@uses`/`@group` docblock annotations → PHP 8 attributes across 8 test files (forward-compatible with PHPUnit 12)
- Removed `<coverage>` block from `phpunit.xml` (no driver = pointless warning)
- Result: `OK (208 tests, 271 assertions)` — zero warnings, zero deprecations

**Maintenance:**
- Removed empty `tests/Security/` ghost directory
- `docs/std/Safe.md` → converted to migration reference guide
- `make ci-check` target added (runs `ci-test` + `ci-lint` without compose)
- CS-Fixer auto-fix applied to 18 source files (trailing commas, alignment)
- Git commit: all changes committed to main branch

## Current Architecture (unchanged)
Three Safety Pillars:
1. **runkit7 overrides** — FunctionOverrider replaces silent-failure built-ins at engine level
2. **curl-based HttpClient** — all HTTP errors → `HttpException`
3. **azjezz/psl ^4.2** — typed JSON, File, Regex, Type coercion; locked due to phpstan-extension incompatibility with PSL 5.x

## Known Open Issue
- RoadRunner `WorkerAllocate: EOF` in `docker compose up` — `spiral/roadrunner-worker` missing from vendor. Run `make shell` then `composer install` to fix.

## Last Session Update (2026-03-09 — Docs cleanup)

### What Was Done
- `README.md`: updated PHP badge 8.3 → 8.4; replaced stale "Pillar 3 — `core\Security\Safe`" section with accurate `s_*()` shims section; fixed file tree (removed non-existent Safe.php and 6 exception files)
- `docs/index.md`: updated PHP badge 8.3 → 8.4; replaced `Safe::jsonDecode()` code example with `s_json()`/`s_int()`/`s_file()` equivalents; replaced broken `| core\Security\Safe\Safe |` table row with `| Global s_*() shims |`; removed duplicate functions row; fixed `corephp-logo.png` path (was `assets/images/corephp-logo.png` → `corephp-logo.png`)
- `docs/_config.yml`: tagline updated PHP 8.3 → 8.4
- `docs/corephp-logo.png`: compressed from 6.8 MB → 340 KB (ImageMagick resize 2816px → 800px + strip metadata)
- Greicodex logos added: `greicodex-logo.png` (120K) and `fidex-as5-logo.png` (768K) from GreicodexJM/fidex-protocol

## Next Potential Tasks
- Add PHP code coverage (install Xdebug or PCOV in the Docker image)
- Add integration test suite for RoadRunner worker lifecycle
- Monitor `php-standard-library/phpstan-extension` for PSL 5.x support to enable upgrade
- Add `make ci-check` to GitHub Actions workflow
