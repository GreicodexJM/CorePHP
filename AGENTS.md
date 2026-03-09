# AGENTS.md — CorePHP Coding Agent Instructions

> For OpenAI Codex CLI, Codex Agents, and any agent that reads `AGENTS.md`.
> For Claude Code agents, see `CLAUDE.md` (which has more detail).

---

## Project Summary

CorePHP is a **production-grade PHP 8.4 base Docker image** ("PHP-JVM") that:
1. Runs PHP as a **long-lived persistent process** via RoadRunner (no cold-starts)
2. Replaces PHP's silent-failure stdlib with one that **throws typed exceptions on every error**
3. Provides a hardened `std` library under the `core\` namespace

Output: `greicodex/corephp-vm` Docker image on Docker Hub.

---

## commands

```bash
# Setup
make build      # Build Docker image (run first)
make up         # Start services (detached)
make down       # Stop services
make shell      # Enter container shell

# Quality gates (run inside container via Docker)
make test       # PHPUnit test suite
make lint       # PHP-CS-Fixer dry-run + PHPStan Level 9
make lint-fix   # Auto-fix code style violations

# Maintenance
make logs       # Follow logs
make clean      # Remove stopped containers + dangling images
make help       # List all targets
```

**All commands run inside Docker.** Never run `php`, `composer`, or `phpunit` directly on the host.

---

## constraints

### Code Style
- Every PHP file: `declare(strict_types=1);` — **first line, always**
- All classes: `core\*` namespace (e.g., `core\MyService`, `core\Net\Http\MyClient`)
- All public methods: explicit return type declarations (no missing return types)
- PHPStan Level 9 must pass — zero warnings allowed

### Typing Rules
- ❌ No `mixed` in public interfaces
- ❌ No bare `array` in public method signatures — use `core\Vec` or `core\Dict`
- ❌ No `catch (\Throwable $e) {}` silencing blocks
- ✅ Use `TypedCollection` / `Vec` / `Dict` for all collections
- ✅ All exceptions must be typed (specific named exception class)

### Forbidden Functions/Patterns
- `unserialize()` — disabled in `php.ini`
- `exec()`, `shell_exec()`, `system()`, `passthru()` — disabled in `php.ini`
- `eval()` — language construct, cannot disable; PHPStan catches usage
- `core\Security\Safe\Safe` — **this class was deleted**; use PSL directly

### Dependency Constraint (CRITICAL)
- **DO NOT upgrade `azjezz/psl` past `^4.2`**
- `php-standard-library/phpstan-extension ^2.0` conflicts with `azjezz/psl >= 5.0`
- No compatible phpstan extension version has been published for PSL 5.x
- Upgrading breaks PHPStan Level 9 — not acceptable

---

## workflow

This project uses **strict TDD**:

1. Write failing test(s) in `opt/corephp-vm/std/tests/` first
2. Run `make test` to confirm failure
3. Write minimum code in `opt/corephp-vm/std/src/` to pass
4. Run `make lint` to verify PHPStan Level 9 + CS-Fixer
5. Run `make test` to confirm all pass
6. Update `memory-bank/activeContext.md` with what changed

---

## key APIs

### Global Shim Functions (always available — no `use` needed)

```
s_json($str)          → safe json_decode  (throws Psl\Json\Exception)
s_enc($val)           → safe json_encode  (throws Psl\Json\Exception)
s_int($val)           → strict int coerce (throws Psl\Type\Exception\CoercionException)
s_float($val)         → strict float coerce
s_str($val)           → strict string coerce
s_bool($val)          → strict bool coerce
s_file($path)         → safe file read    (throws Psl\File\Exception\RuntimeException)
s_write($path, $data) → safe file write
s_append($path, $data)→ safe file append
s_match($pat, $str)   → bool regex test   (throws Psl\Regex\Exception\RuntimeException)
s_regex($pat, $str)   → first match groups
s_regex_all($p, $s)   → all match groups
s_env($key)           → env var or throw  (throws \RuntimeException if missing)
s_env_or($key, $def)  → env var with fallback
s_get($url)           → HTTP GET → HttpResponse
s_post($url, $body)   → HTTP POST → HttpResponse
arr_to_list($type, $arr) → array → core\Vec
arr_to_dict($type, $arr) → array → core\Dict
vec_filter($list, $fn)   → Psl\Vec\filter()
vec_map($list, $fn)      → Psl\Vec\map()
dict_filter($d, $fn)     → Psl\Dict\filter()
dict_map($d, $fn)        → Psl\Dict\map()
dict_merge(...$dicts)    → Psl\Dict\merge()
```

### Core Classes

```
core\Vec                  — Typed sequential list (extends TypedCollection)
core\Dict                 — Typed key-value store
core\IO                   — Safe file + HTTP facade (static methods)
core\Any                  — Universal strict base class (alias: BaseObject)
core\StrictObject         — Undefined property guard (abstract, extend this)
core\Net\Http\HttpClient  — curl wrapper (all errors → HttpException)
core\Net\Http\HttpResponse— Immutable response VO
core\Net\Http\HttpException
core\Internal\Array\TypedCollection — Base collection (extended by Vec)
core\Engine\FunctionOverrider — runkit7 boot installer (call install() once)
```

### Exception Types

```
core\Net\Http\HttpException                    (extends \RuntimeException)
core\Security\Exceptions\SecurityException     (extends \RuntimeException)
core\Security\Exceptions\EncodingException     (extends \RuntimeException)
Psl\Json\Exception                            (JSON encode/decode)
Psl\File\Exception\RuntimeException           (file read/write)
Psl\Type\Exception\CoercionException          (type coercion)
Psl\Regex\Exception\RuntimeException          (regex)
```

---

## file locations

```
opt/corephp-vm/std/src/       — All std library source code
opt/corephp-vm/std/tests/     — All PHPUnit tests (mirrors src/ structure)
opt/corephp-vm/bootstrap.php  — Boot sandbox (auto_prepend_file)
config/php.ini                — PHP hardening (disable_functions, runkit)
.rr.yaml                      — RoadRunner config
worker.php                    — PSR-7 request loop
ci/lint.sh                    — CI lint runner
ci/test.sh                    — CI test runner
memory-bank/                  — Project documentation (GOS standard)
docs/std/                     — std library docs (7 markdown files)
```

---

## known issues

1. **RoadRunner `WorkerAllocate: EOF`** in `docker compose up` — `spiral/roadrunner-worker` missing from vendor. Run `make shell` then `composer install`.
2. **`eval()`** is a language construct — cannot be blocked in `php.ini`. PHPStan catches usage statically. Never use it.
3. **runkit7** must be built from GitHub source — not available in PECL registry for Alpine/PHP 8.4.

---

## deeper context

See `CLAUDE.md` for expanded documentation.
See `memory-bank/` for full project history and architectural decisions.
