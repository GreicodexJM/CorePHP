# Active Context: CorePHP (PHP-JVM)

## Current Work Focus
✅ **Initial implementation complete.**

## Status
🟢 **COMPLETE** — All 29 project files generated from specification in `docs/PROJECT.md`.

## What Was Built

### Phase 1 — Memory Bank ✅
All GOS-required documentation files created under `memory-bank/`.

### Phase 2 — Infrastructure ✅
- `Makefile` with targets: build, up, down, shell, lint, lint-fix, test, rr-start
- `docker-compose.yaml` (production) + `docker-compose.override.yaml` (development)
- `config/php.ini` — hardened PHP configuration (disable_functions, runkit.internal_override)
- `.gitignore`, `.user.ini` (Shared Hosting mode)

### Phase 3 — Docker Image ✅
- `Dockerfile` — PHP 8.3 CLI Alpine + RoadRunner + runkit7 PECL + Composer
- `.rr.yaml` — RoadRunner config (4 workers, 500 max_jobs, supervisor)

### Phase 4 — Bootstrap Sandbox ✅
- `worker.php` — PSR-7 persistent request loop with global Throwable catch
- `opt/php-jvm/bootstrap.php` — `set_error_handler` + Monolog audit + `FunctionOverrider::install()` + `std\StrictObject`

### Phase 5 — Standard Library (std) ✅

**Pillar 1 — `std\Internal\Array\TypedCollection`**
- Type-safe homogeneous collection (replaces native arrays)
- Supports class/interface types AND primitives (int, string, float, bool)
- Implements ArrayAccess + Iterator + Countable
- `add()`, `filter()`, `map()`, `toArray()`

**Pillar 2 — `std\Net\Http\HttpClient`**
- curl wrapper — ALL errors become `HttpException`
- `get()`, `post()`, `put()`, `delete()`
- `HttpResponse` immutable VO with `json()`, `header()`, `statusCode()`
- `strictStatus` mode throws on 4xx/5xx
- `HttpException::fromCurlError()` + `::fromHttpStatus()`

**Pillar 3 — `std\Security\Safe`**
- `Safe::jsonDecode()` → `JsonDecodeException`
- `Safe::jsonEncode()` → `JsonEncodeException`
- `Safe::toInt()` → `TypeCoercionException`
- `Safe::toFloat()` → `TypeCoercionException`
- `Safe::fileRead()` → `FileReadException`
- `Safe::fileWrite()` → `FileWriteException`

**Engine — `std\Engine\FunctionOverrider`**
- runkit7 idempotent installer
- Overrides: json_decode, json_encode, file_get_contents, file_put_contents, intval, floatval, preg_match, preg_match_all, preg_replace, curl_exec, base64_decode
- Unserialize guard (no-op if already in disable_functions)

### Phase 6 — Enforcement Tooling ✅
- `.php-cs-fixer.dist.php` — PSR-12 + `declare_strict_types` enforced
- `phpstan.neon` — Level 9, strict null checks, no mixed types

### Phase 7 — CI ✅
- `ci/lint.sh` — PHP-CS-Fixer dry-run + PHPStan
- `ci/test.sh` — PHPUnit
- `opt/php-jvm/std/phpunit.xml` — test suite config

### Phase 8 — Documentation ✅
- `README.md` — full documentation with VPS + Shared Hosting instructions
- Memory Bank updated

## Next Steps (Future Work)
1. Write PHPUnit tests for the std library (TDD follow-up)
2. Run `docker build -t php-jvm .` to verify build succeeds
3. Add Prometheus metrics endpoint to `.rr.yaml`
4. Consider adding `std\Database\Connection` wrapper (PDO → typed exceptions)

## Key Technical Decisions (Final)
- runkit7 for native function override at boot-time (intercepted from ALL code including vendor)
- php.ini `disable_functions` for truly dangerous functions (unserialize, exec, etc.)
- eval() handled by PHPStan only (cannot disable language constructs in php.ini)
- TypedCollection handles primitives via `get_debug_type()` (not instanceof)
- FunctionOverrider is idempotent (safe for RoadRunner worker restarts)
- bootstrap.php uses `defined('PHP_JVM_BOOTSTRAP_LOADED')` guard to prevent double-execution
