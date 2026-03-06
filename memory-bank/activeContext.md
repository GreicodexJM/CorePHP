# Active Context: CorePHP (PHP-JVM)

## Current Work Focus
✅ **PSL Integration complete — azjezz/psl replaces hand-rolled Safe class as Layer 2.**

## Status
🟢 **COMPLETE** — PSL integrated as direct Layer 2, new shims added, tests updated.

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
- `opt/corephp-vm/bootstrap.php` — `set_error_handler` + Monolog audit + `FunctionOverrider::install()` + `core\StrictObject`

### Phase 5 — Standard Library (std) ✅

**Pillar 1 — `core\Internal\Array\TypedCollection`**
- Type-safe homogeneous collection (replaces native arrays)
- Supports class/interface types AND primitives (int, string, float, bool)
- Implements ArrayAccess + Iterator + Countable
- `add()`, `filter()`, `map()`, `toArray()`

**Pillar 2 — `core\Net\Http\HttpClient`**
- curl wrapper — ALL errors become `HttpException`
- `get()`, `post()`, `put()`, `delete()`
- `HttpResponse` immutable VO with `json()`, `header()`, `statusCode()`
- `strictStatus` mode throws on 4xx/5xx
- `HttpException::fromCurlError()` + `::fromHttpStatus()`

**Pillar 3 — azjezz/psl (Direct Layer 2)**
- `Psl\Json\decode/encode()` — JSON with exception on failure
- `Psl\File\read/write()` — File I/O with exception on failure
- `Psl\Type\*()->coerce()` — Strict type coercion
- `Psl\Regex\matches/first_match/every_match()` — Safe regex
- `Psl\Vec\*` + `Psl\Dict\*` — Functional array operations
- `Psl\Env\get_var()` — Environment variable access
- ~~`core\Security\Safe\Safe`~~ — **Deleted** (replaced by PSL directly)
- ~~6 custom exception classes~~ — **Deleted** (use PSL exceptions)

**Engine — `core\Engine\FunctionOverrider`**
- runkit7 idempotent installer
- Overrides: json_decode, json_encode, file_get_contents, file_put_contents, intval, floatval, preg_match, preg_match_all, preg_replace, curl_exec, base64_decode
- Unserialize guard (no-op if already in disable_functions)

### Phase 6 — Enforcement Tooling ✅
- `.php-cs-fixer.dist.php` — PSR-12 + `declare_strict_types` enforced
- `phpstan.neon` — Level 9, strict null checks, no mixed types

### Phase 7 — CI ✅
- `ci/lint.sh` — PHP-CS-Fixer dry-run + PHPStan
- `ci/test.sh` — PHPUnit
- `opt/corephp-vm/std/phpunit.xml` — test suite config

### Phase 8 — Documentation ✅
- `README.md` — full documentation with VPS + Shared Hosting instructions
- Memory Bank updated

## What Was Added (TDD Session)

### Tests ✅ — 155 tests, 0 failures
- `TypedCollectionTest` (37 tests) — full API coverage
- `VecTest` (12 tests) — subclass contract + covariant factories
- `DictTest` (38 tests) — full API coverage including only/except
- `SafeTest` (26 tests) — all Safe static methods + all exception types
- `IOTest` (14 tests) — file + JSON + HTTP factory methods
- `StrictObjectTest` (9 tests) — all magic method guards
- `AnyTest` (7 tests) — inheritance + __toString
- `HttpClientTest` (28 tests) — constructor validation, HttpResponse VO, integration (tagged)

### API Fixes Applied During TDD
- `TypedCollection` → removed `final` keyword (required by `Vec` inheritance)
- `HttpClient` → constructor validates `timeout > 0`
- `HttpResponse::header()` → returns `?string` (was throwing on missing)
- `HttpResponse::requireHeader()` → new method, throws `HttpException` if absent
- `HttpResponse::isOk()` → new method, checks `statusCode === 200`

### Documentation ✅ — `docs/std/` (7 files)
- `README.md`, `Vec.md`, `Dict.md`, `Safe.md`, `IO.md`, `StrictObject.md`, `functions.md`, `migration.md`

## What Was Added (PSL Integration Session — 2026-03-06)

### PSL Integration ✅
- `azjezz/psl ^2.9` added as a direct `require` dependency (not dev-only)
- `php-standard-library/phpstan-extension ^2.0` added to `require-dev`
- `phpstan.neon` updated to include PSL extension neon

### Deleted (replaced by PSL) ✅
- `Security/Safe/Safe.php` — replaced by `Psl\Json\*`, `Psl\File\*`, `Psl\Type\*`
- `Security/Safe/JsonDecodeException.php` — use `Psl\Json\Exception`
- `Security/Safe/JsonEncodeException.php` — use `Psl\Json\Exception`
- `Security/Safe/FileReadException.php` — use `Psl\File\Exception\RuntimeException`
- `Security/Safe/FileWriteException.php` — use `Psl\File\Exception\RuntimeException`
- `Security/Safe/RegexException.php` — use `Psl\Regex\Exception\RuntimeException`
- `Security/Safe/TypeCoercionException.php` — use `Psl\Type\Exception\CoercionException`
- `tests/Security/Safe/SafeTest.php` — replaced by `tests/FunctionShimsTest.php`

### Updated ✅
- `IO.php` → calls `Psl\Json\decode/encode` and `Psl\File\read/write` directly
- `IO.php` → new `append()` method added
- `HttpClient.php` → uses `Psl\Json\encode()` for request body serialization
- `HttpResponse.php` → uses `Psl\Json\decode()` for `json()` method
- `FunctionOverrider.php` → override bodies reference PSL exception FQCNs
- `functions.php` → expanded with new PSL-backed shims (see below)
- `TypedCollection.php` → `filter/map` use `Psl\Vec\*` internally; new methods added
- `Vec.php` → new PSL-backed static utilities: `values`, `filterValues`, `mapValues`, `sortedValues`, `reversedValues`, `concat`, `range`
- `Dict.php` → new PSL-backed static utilities: `mergeArrays`, `filterArray`, `filterKeys`, `mapArray`, `selectKeys`, `sortArray`, `flipArray`

### New Global Shims (functions.php) ✅
| Shim | Backed By | Description |
|---|---|---|
| `s_json($str, $assoc)` | `Psl\Json\decode()` | Safe JSON decode |
| `s_enc($val, $pretty)` | `Psl\Json\encode()` | Safe JSON encode |
| `s_int($val)` | `Psl\Type\int()->coerce()` | Strict int coerce |
| `s_float($val)` | `Psl\Type\float()->coerce()` | Strict float coerce |
| `s_str($val)` | `Psl\Type\string()->coerce()` | Strict string coerce |
| `s_bool($val)` | `Psl\Type\bool()->coerce()` | Strict bool coerce |
| `s_file($path)` | `Psl\File\read()` | Safe file read |
| `s_write($p,$d,$app)` | `Psl\File\write()` | Safe file write (returns bytes) |
| `s_append($p,$d)` | `Psl\File\write(APPEND)` | Safe file append |
| `s_fwrite($h,$d)` | `fwrite()` + throw | Safe handle write |
| `s_match($pat,$sub)` | `Psl\Regex\matches()` | Bool regex match |
| `s_regex($pat,$sub)` | `Psl\Regex\first_match()` | First match groups |
| `s_regex_all($p,$s)` | `Psl\Regex\every_match()` | All match groups |
| `s_env($key)` | `Psl\Env\get_var()` + throw | Safe env var read |
| `s_env_or($key,$def)` | `Psl\Env\get_var()` | Env var with default |
| `vec_filter($list,$fn)` | `Psl\Vec\filter()` | Plain array filter |
| `vec_map($list,$fn)` | `Psl\Vec\map()` | Plain array map |
| `dict_filter($d,$fn)` | `Psl\Dict\filter()` | Assoc array filter |
| `dict_map($d,$fn)` | `Psl\Dict\map()` | Assoc array map |
| `dict_merge(...$d)` | `Psl\Dict\merge()` | Assoc array merge |

### New TypedCollection Methods (PSL-backed) ✅
- `reverse()` → `Psl\Vec\reverse()`
- `slice($offset, $length)` → `Psl\Vec\slice()`
- `chunk($size)` → `Psl\Vec\chunk()`
- `sort($comparator)` → `Psl\Vec\sort_by()`
- `unique()` — key-based uniqueness filter

### Tests Updated ✅
- `tests/IOTest.php` — exception imports updated to PSL
- `tests/FunctionShimsTest.php` — new comprehensive test for all 25 shims
- `tests/Internal/Array/TypedCollectionTest.php` — new PSL method tests (15 new tests)

## Next Steps (Future Work)
1. Fix RoadRunner worker.php crash (`WorkerAllocate: EOF` — likely missing spiral/roadrunner-worker in vendor)
2. Add Prometheus metrics endpoint to `.rr.yaml`
3. Consider adding `core\Database\Connection` wrapper (PDO → typed exceptions)

## Key Technical Decisions (Final)
- runkit7 for native function override at boot-time (intercepted from ALL code including vendor)
- php.ini `disable_functions` for truly dangerous functions (unserialize, exec, etc.)
- eval() handled by PHPStan only (cannot disable language constructs in php.ini)
- TypedCollection handles primitives via `get_debug_type()` (not instanceof)
- FunctionOverrider is idempotent (safe for RoadRunner worker restarts)
- bootstrap.php uses `defined('PHP_JVM_BOOTSTRAP_LOADED')` guard to prevent double-execution
