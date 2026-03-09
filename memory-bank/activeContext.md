# Active Context: CorePHP (PHP-JVM)

## Current Work Focus
✅ **PHPStan Level 9 clean pass achieved — 56 pre-existing type errors fixed across all source files.**

## Status
🟢 **COMPLETE** — PHPStan: 0 errors | PHPUnit: 208/208 tests pass.

## What Was Fixed (PHPStan / CI Hardening Session — 2026-03-09)

### Problem Discovered
PHPStan Level 9 had **never actually run clean** on this project. Three infrastructure bugs prevented it:
1. `ci/lint.sh` and `ci/test.sh` referenced a stale path (`opt/php-jvm/std/` → `opt/corephp-vm/std/`)
2. `phpstan.neon` used removed PHPStan 2.x parameters and was missing the `parallel: maximumNumberOfProcesses: 1` setting required inside Docker
3. Production `php.ini` has `disable_functions = unserialize,` — PHPStan uses `unserialize()` for its cache, so it silently failed

### Fixes Applied

#### Infrastructure ✅
- **`ci/lint.sh`** — corrected path from `opt/php-jvm/std/` to `opt/corephp-vm/std/`
- **`ci/test.sh`** — same path correction
- **`phpstan.neon`** — removed deprecated params (`checkMissingIterableValueType`, `checkGenericClassInNonGenericObjectType`, `checkAlwaysTrueCheckTypeFunctionCall`); added `parallel: maximumNumberOfProcesses: 1`
- **`config/php-ci.ini`** — new file: identical to `php.ini` but with `disable_functions =` (empty) for CI tools

#### PHPStan Type Fixes (56 errors → 0) ✅
| Category | Files | Fix Applied |
|---|---|---|
| `@throws \Psl\Json\Exception` (non-Throwable) | `IO.php`, `HttpResponse.php`, `functions.php` | Updated to `\Psl\Json\Exception\DecodeException` / `EncodeException` |
| `callable` vs `Closure` (PSL 4.x strict) | `Vec.php`, `Dict.php`, `TypedCollection.php`, `functions.php` | Wrapped with `\Closure::fromCallable($fn)` before PSL calls |
| `non-empty-string` for `Psl\File\read/write` | `IO.php`, `functions.php` | Added `assert($path !== '')` to narrow type |
| `non-empty-string` for `Psl\Env\get_var` | `functions.php` | Added `assert($key !== '')` |
| `int<0, max>` for `Psl\Vec\slice` | `TypedCollection.php` | Added `assert($offset >= 0)` and `assert($length === null \|\| $length >= 0)` |
| `new static()` unsafe | `TypedCollection.php` | Added `@phpstan-consistent-constructor` to class docblock + `/** @var static<T> */` casts |
| `TypedCollection::fromArray()` return type | `TypedCollection.php` | Changed `@param array<mixed>` (items already validated at runtime) |
| `Dict::fromArray()` `array<string, mixed>` vs `array<mixed>` | `Dict.php` | Changed `@param` to `array<mixed>` (keys cast to string internally) |
| `CURLOPT_URL` expects `non-empty-string` | `HttpClient.php` | Added `assert($url !== '')` in `configureCurl()` after URL already validated |
| `CURLOPT_POSTFIELDS` raw string body | `HttpClient.php`, `phpstan.neon` | Added `ignoreErrors` entry (empty body is valid HTTP) |

#### Makefile ✅
Added two standalone CI targets that work without a running compose container:
- **`make ci-test`** — runs PHPUnit via ephemeral `docker run --rm` with CI php.ini mounted
- **`make ci-lint`** — runs PHPStan + CS-Fixer via ephemeral `docker run --rm` with CI php.ini mounted

### Final State
```
PHPStan Level 9: [OK] No errors
PHPUnit:         Tests: 208, Assertions: 271, Failures: 0
                 (1 warning: no xdebug driver — expected; 11 PHPUnit API deprecations — cosmetic)
```

---

## What Was Added (AI-Agent Documentation Session — 2026-03-09)

### AI-Friendly Artifacts ✅
Six new files added to make the repository usable by any LLM coding agent without prior context:

| File | Tool / Agent | Purpose |
|---|---|---|
| `CLAUDE.md` | Claude Code (Anthropic) | Full agent instructions: commands, architecture, all APIs, constraints, known issues |
| `AGENTS.md` | OpenAI Codex CLI / Codex Agents | `commands:` + `constraints:` structured blocks for programmatic parsing |
| `.clinerules` | Cline (local repo rules) | CorePHP-specific rules layered on top of global GOS rules |
| `.cursorrules` | Cursor IDE | Namespace tree, shim functions, hard rules for inline completions |
| `llms.txt` | Any LLM / generic | Scannable project overview: concepts glossary, file map, API summary, constraints |
| `.github/copilot-instructions.md` | GitHub Copilot | Workspace instructions for Copilot Chat + inline suggestions |

### Content Strategy
- **Non-duplicative** — each file cross-references the others rather than repeating prose
- **`CLAUDE.md`** is the canonical deep reference; others are summaries pointing to it
- **`llms.txt`** serves as a quick-start orientation (< 200 lines) for any LLM dropped into the repo
- **`.clinerules`** extends global GOS rules — adds CorePHP-specific gotchas and workflow enforcement

### Key Content Captured in Agent Files
- The "Safe class was deleted" anti-pattern (PSL replaces it)
- The PSL `^4.2` version lock and why upgrading to `^5.0` is blocked
- All 25 global `s_*` shim functions with signatures
- Full `core\*` namespace tree
- Exception hierarchy (custom + PSL)
- `TypedCollection` is NOT `final` (Vec extends it)
- `HttpResponse::header()` returns `?string`
- `FunctionOverrider::install()` is idempotent
- TDD requirement (tests before code)
- `make test` + `make lint` must pass before any task is complete

## Previous Work Focus (kept for history)
✅ **PSL Integration complete — azjezz/psl replaces hand-rolled Safe class as Layer 2.**

## Previous Status
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
4. **Monitor `php-standard-library/phpstan-extension` for PSL 5.0.0 support** — upgrade PSL once a compatible version is published (see PSL 5.0.0 Evaluation below)

## PSL 5.0.0 Evaluation (2026-03-09) — ⛔ BLOCKED

PSL 5.0.0 (`azjezz/psl ^5.0`) was evaluated for adoption. **Upgrade is currently blocked.**

### What PSL 5.0.0 Offers
- PHP 8.4+ requirement (✅ project already on `^8.4`)
- Up to 100% performance improvements in `Vec`, `Dict`, `Str`, `Iter`, `Type` (all used by CorePHP)
- New `Psl\Crypto` module (libsodium — highly relevant to `core\Security\`)
- New `Psl\Process` for async process management
- New `Psl\Terminal` / `Psl\Ansi` for terminal UI

### Breaking Changes (vs. current `^4.2`)
| Change | CorePHP Impact |
|---|---|
| PHP 8.4+ minimum | ✅ No impact — already on `^8.4` |
| Full `Psl\Network` / `Psl\TCP` / `Psl\Unix` rewrite | ✅ No impact — CorePHP uses curl-based `HttpClient`, not PSL networking |
| `Psl\Env\temp_dir()` canonicalization | ⚠️ Minor — path string format may differ |
| `Filesystem\create_temporary_file()` canonicalization | ⚠️ Minor — path string format may differ |
| PHPUnit 13 (dev dependency of PSL itself) | ✅ No impact — CorePHP's PHPUnit is independent |

### ⛔ Hard Blocker
`php-standard-library/phpstan-extension ^2.0` (currently in `require-dev`) **explicitly conflicts with `azjezz/psl >= 5.0`** and no PSL 5.0.0-compatible version has been published.

Dropping the extension would degrade PHPStan type inference for PSL generics — unacceptable on a Level 9 project. **Do not upgrade until a compatible extension release is available.**

## Key Technical Decisions (Final)
- runkit7 for native function override at boot-time (intercepted from ALL code including vendor)
- php.ini `disable_functions` for truly dangerous functions (unserialize, exec, etc.)
- eval() handled by PHPStan only (cannot disable language constructs in php.ini)
- TypedCollection handles primitives via `get_debug_type()` (not instanceof)
- FunctionOverrider is idempotent (safe for RoadRunner worker restarts)
- bootstrap.php uses `defined('PHP_JVM_BOOTSTRAP_LOADED')` guard to prevent double-execution
