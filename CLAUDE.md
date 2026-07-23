# CLAUDE.md — CorePHP Agent Instructions

> **Read this file first.** It gives you everything you need to work safely and correctly in this codebase.

---

## What Is CorePHP?

CorePHP is a **production-grade, persistent PHP 8.4 runtime base Docker image** — a "PHP-JVM". It eliminates PHP's per-request cold-starts by embedding PHP CLI inside [RoadRunner](https://roadrunner.dev/) as a long-lived process, and replaces PHP's silent-failure standard library with one that throws typed exceptions on every error.

This repo produces: `greicodex/corephp-vm` on Docker Hub. Downstream projects `FROM` this image.

---

## Environment Setup & Key Commands

All tasks run via `make` inside Docker. **Never run PHP/Composer directly on the host.**

```bash
make build      # Build the Docker image (required before anything else)
make up         # Start Docker Compose services (detached)
make down       # Stop services
make shell      # Open sh inside the running container
make test       # Run PHPUnit suite (needs `make up` first — execs into running container)
make lint       # PHP-CS-Fixer dry-run + PHPStan Level 9 (needs `make up`)
make lint-fix   # Auto-fix PHP-CS-Fixer violations
make check      # Full gate: test + lint in sequence (needs `make up`)
make ci-test    # Run PHPUnit standalone — ephemeral `docker run`, no `make up` needed
make ci-lint    # Run lint standalone — ephemeral `docker run`, no `make up` needed
make rr-start   # Start RoadRunner server inside the container
make logs       # Follow container logs
make clean      # Prune stopped containers + dangling images
make tag VERSION=x.y.z   # Tag + push a semver release (GH Actions builds/pushes to Docker Hub)
make help       # List all targets
```

**`make test`/`make lint` vs `make ci-test`/`make ci-lint`:** the plain targets `docker compose exec` into an already-running container (`make up` first). The `ci-*` targets spin up a fresh `docker run` and mount `config/php-ci.ini`, which clears `disable_functions` so PHPStan (`unserialize`) and PHPUnit (`proc_open`) can run — use these for one-shot checks or CI.

**Run a single test** (there is no Make target — exec directly):
```bash
make shell   # then, inside the container:
opt/corephp-vm/std/vendor/bin/phpunit --configuration=opt/corephp-vm/std/phpunit.xml --filter '::testMethodName'
opt/corephp-vm/std/vendor/bin/phpunit --configuration=opt/corephp-vm/std/phpunit.xml opt/corephp-vm/std/tests/VecTest.php
```

**TDD Workflow (mandatory):**
1. `make up` — ensure services are running
2. Write failing test(s) first
3. `make test` — confirm they fail
4. Write minimum code to pass
5. `make lint` — ensure static analysis passes
6. `make test` — confirm all green

---

## Project Structure

```
CorePHP/
├── Dockerfile                          # PHP 8.4-CLI Alpine + RoadRunner + runkit7
├── docker-compose.yaml                 # Production compose
├── docker-compose.override.yaml        # Development overrides (volume mounts)
├── Makefile                            # ALL developer tasks live here
├── .rr.yaml                            # RoadRunner: 4 workers, 500 max_jobs
├── worker.php                          # PSR-7 persistent request loop
├── config/php.ini                      # Hardened PHP (disable_functions, runkit)
├── .php-cs-fixer.dist.php              # PSR-12 + declare(strict_types=1)
├── phpstan.neon                        # PHPStan Level 9
├── .user.ini                           # Shared Hosting mode (no Docker)
├── opt/corephp-vm/
│   ├── bootstrap.php                   # auto_prepend_file sandbox
│   └── std/
│       ├── composer.json               # std library package (core\ namespace)
│       ├── phpunit.xml
│       └── src/
│           ├── Any.php                 # core\Any — universal strict base class
│           ├── StrictObject.php        # core\StrictObject — property guard
│           ├── Vec.php                 # core\Vec — typed sequential list
│           ├── Dict.php                # core\Dict — typed key-value store
│           ├── IO.php                  # core\IO — safe file + HTTP facade
│           ├── functions.php           # 25 global shim functions (s_json, etc.)
│           ├── Engine/FunctionOverrider.php   # runkit7 native override installer
│           ├── Internal/Array/TypedCollection.php
│           ├── Net/Http/HttpClient.php
│           ├── Net/Http/HttpResponse.php
│           ├── Net/Http/HttpException.php
│           └── Security/Exceptions/
│               ├── SecurityException.php
│               └── EncodingException.php
├── ci/
│   ├── lint.sh                         # CI: PHP-CS-Fixer + PHPStan
│   └── test.sh                         # CI: PHPUnit
├── docs/std/                           # std library documentation (7 files)
└── memory-bank/                        # GOS project documentation
```

---

## Two Composer Roots (important)

This repo has **two separate Composer packages**, each with its own `vendor/`, autoload, and PHPStan level:

| Root | Package | Namespace → path | PHPStan | Purpose |
|---|---|---|---|---|
| `./composer.json` | `corephp/corephp-vm` | `App\` → `src/` | `^1.11` | Runtime shell: RoadRunner worker, PSR-7 loop (`worker.php`) |
| `opt/corephp-vm/std/composer.json` | `corephp/std` | `core\` → `opt/corephp-vm/std/src/` | `^2.0` + PSL ext | The `std` library — the actual deliverable, all `core\*` classes + `s_*` shims |

`make test` runs `opt/corephp-vm/std/phpunit.xml` (the **std** suite) — that is where all test coverage lives. The PSL version lock (see below) applies to the `std` root. New `core\*` code and its tests always go under `opt/corephp-vm/std/`.

---

## Architecture — Three Enforcement Layers

| Layer | Mechanism | Timing | What It Covers |
|---|---|---|---|
| **1. Static** | PHPStan Level 9 + PHP-CS-Fixer | CI/pre-commit | Type errors, `mixed`, eval, style |
| **2. Boot** | `runkit7` `FunctionOverrider` | Process startup (once) | Replaces native functions — **DISABLED by default** (segfaults on PHP 8.4; opt-in via `COREPHP_ENABLE_RUNKIT_OVERRIDE=1`). See Known Issues. |
| **3. Runtime** | `bootstrap.php` error handler | Every request | Warnings/Notices → Exceptions |

### The `std` Library — Namespace Tree

```
core\
├── Any                         — Universal strict base class (alias: BaseObject)
├── StrictObject                — Undefined property guard (abstract)
├── Vec                         — Typed sequential list (alias: ArrayList)
├── Dict                        — Typed key-value dictionary
├── IO                          — Safe file + HTTP facade
├── Engine\
│   └── FunctionOverrider       — runkit7 override installer (boot-time)
├── Internal\Array\
│   └── TypedCollection         — Base class for Vec (type-safe collection)
├── Net\Http\
│   ├── HttpClient              — curl wrapper (throws on all failures)
│   ├── HttpResponse            — Immutable response value object
│   └── HttpException           — Transport + HTTP status failures
└── Security\Exceptions\
    ├── SecurityException       — unserialize/eval blocks
    └── EncodingException       — base64_decode failures
```

### Exception Hierarchy

```
\RuntimeException
├── core\Net\Http\HttpException
├── core\Security\Exceptions\SecurityException
└── core\Security\Exceptions\EncodingException

PSL Exceptions (use these — Safe class was deleted):
├── Psl\Json\Exception                        — JSON decode/encode failures
├── Psl\File\Exception\RuntimeException       — File read/write failures
├── Psl\Type\Exception\CoercionException      — Type coercion failures
└── Psl\Regex\Exception\RuntimeException      — Regex pattern failures
```

---

## Globally Available Functions (`s_*` shims — no `use` required)

These are registered by `bootstrap.php` and available everywhere:

### JSON
```php
s_json(string $json, bool $assoc = true): mixed    // Safe json_decode → Psl\Json\decode()
s_enc(mixed $val, bool $pretty = false): string    // Safe json_encode → Psl\Json\encode()
```

### Type Coercion (strict — throws on bad input)
```php
s_int(mixed $val): int      // → Psl\Type\int()->coerce()
s_float(mixed $val): float  // → Psl\Type\float()->coerce()
s_str(mixed $val): string   // → Psl\Type\string()->coerce()
s_bool(mixed $val): bool    // → Psl\Type\bool()->coerce()
```

### File I/O
```php
s_file(string $path): string                         // Safe read → Psl\File\read()
s_write(string $path, string $data): int             // Safe write → Psl\File\write()
s_append(string $path, string $data): int            // Safe append
s_fwrite(resource $handle, string $data): int        // Safe fwrite handle
```

### Regex
```php
s_match(string $pattern, string $subject): bool      // Bool match test
s_regex(string $pattern, string $subject): ?array    // First match groups
s_regex_all(string $pattern, string $subject): array // All match groups
s_replace(string $pat, string $repl, string $subj): string // Safe preg_replace → Psl\Regex\replace()
```

### Encoding
```php
s_b64(string $encoded): string   // Safe base64_decode (strict) → throws EncodingException on invalid
```

### Environment
```php
s_env(string $key): string                    // Throws if not set
s_env_or(string $key, string $default): string // Returns default if not set
```

### HTTP
```php
s_get(string $url, array $headers = []): HttpResponse
s_post(string $url, array|string $body = [], array $headers = []): HttpResponse
```

### Array ↔ Collection Bridge
```php
arr_to_list(string $type, array $items): core\Vec   // array → typed Vec
list_to_arr(TypedCollection $list): array            // Vec → plain array
arr_to_dict(string $type, array $data): core\Dict   // assoc array → typed Dict
dict_to_arr(core\Dict $dict): array                 // Dict → assoc array
```

### Functional Shims
```php
vec_filter(iterable $list, callable $fn): array     // Psl\Vec\filter()
vec_map(iterable $list, callable $fn): array        // Psl\Vec\map()
dict_filter(array $dict, callable $fn): array       // Psl\Dict\filter()
dict_map(array $dict, callable $fn): array          // Psl\Dict\map()
dict_merge(array ...$dicts): array                  // Psl\Dict\merge()
```

### runkit7-Overridden Native Functions
These are **redefined at boot** to throw instead of silently failing:

| Function | Old Failure | New Behaviour |
|---|---|---|
| `json_decode()` | returns `null` | throws `Psl\Json\Exception` |
| `json_encode()` | returns `false` | throws `Psl\Json\Exception` |
| `file_get_contents()` | returns `false` | throws `Psl\File\Exception\RuntimeException` |
| `file_put_contents()` | returns `false` | throws `Psl\File\Exception\RuntimeException` |
| `intval()` | returns `0` silently | throws `Psl\Type\Exception\CoercionException` |
| `floatval()` | returns `0.0` silently | throws `Psl\Type\Exception\CoercionException` |
| `preg_match()` | returns `false` | throws `Psl\Regex\Exception\RuntimeException` |
| `preg_replace()` | returns `null` | throws `Psl\Regex\Exception\RuntimeException` |
| `curl_exec()` | returns `false` | throws `core\Net\Http\HttpException` |
| `base64_decode()` | returns `false` | throws `core\Security\Exceptions\EncodingException` |

---

## Coding Conventions — MUST Follow

### Required
- ✅ Every PHP file MUST start with `declare(strict_types=1);`
- ✅ All classes MUST be in the `core\*` namespace
- ✅ All public methods MUST have explicit return type declarations
- ✅ Use `TypedCollection` / `Vec` / `Dict` instead of bare `array` types in public interfaces
- ✅ All exceptions MUST be typed (never bare `\Exception` or `\Throwable` in a throw)
- ✅ TDD: write the failing test first, then write code to make it pass

### Forbidden
- ❌ `mixed` type in any public interface
- ❌ Bare `array` type in public method signatures — use `TypedCollection`
- ❌ Silent `catch (\Throwable $e) { /* silence */ }` blocks
- ❌ `unserialize()`, `exec()`, `shell_exec()`, `system()`, `eval()` — disabled or forbidden
- ❌ `new core\Security\Safe\Safe` — **the `Safe` class was deleted**; use PSL directly (`Psl\Json\*`, `Psl\File\*`, `Psl\Type\*`)
- ❌ Upgrading `azjezz/psl` past `^4.2` — **BLOCKED** (see below)
- ❌ Direct `json_decode()` without using `s_json()` or `Psl\Json\decode()` — with the runkit override disabled by default (see Known Issues), native `json_decode()` will **silently return `null`** on bad input. Always use `s_json()` / `Psl\Json\decode()`.

---

## ⛔ Critical Constraint: PSL Version Lock

**DO NOT upgrade `azjezz/psl` to `^5.0` or higher.**

`php-standard-library/phpstan-extension ^2.0` (a `require-dev` dependency that provides PSL generic type inference for PHPStan Level 9) **explicitly conflicts** with `azjezz/psl >= 5.0`. No compatible extension version has been published yet. Upgrading PSL would break PHPStan Level 9 type inference — unacceptable for this project.

**Action:** Stay on `azjezz/psl ^4.2` and monitor https://packagist.org/packages/php-standard-library/phpstan-extension for a `^3.0` release.

---

## Known Issues

1. **RoadRunner worker crash — RESOLVED (2026-07-23).** The worker used to die at startup
   (`WorkerAllocate: EOF` / `no CONTROL flag`). It was **four chained bugs**, all now fixed and
   verified end-to-end (`/health` → 200, 4 workers ready, 208 tests green):
   - **`ext-sockets` missing** — required by `spiral/roadrunner-worker`; without it the root
     `composer` step failed and `/app/vendor` never shipped. Added `sockets` + `linux-headers` to the Dockerfile.
   - **Root vendor never installed** — `composer install` needs a lock (none exists) and its failure was
     swallowed by `2>/dev/null || true`. Switched to `composer update`, removed the error-swallow.
   - **`getmypid()` was in `disable_functions`** — RoadRunner's handshake calls it to send the worker
     PID; disabling it threw `undefined function`, which the catch-all masked as an opaque 500. Removed
     `getmypid`/`getmyuid`/`getmygid` (and the pointless `serialize` block) from `config/php.ini`.
   - **Compose bind mount `./:/app` shadowed the image's `/app/vendor`.** Removed the mount from the
     production compose; the dev override keeps a `/app/vendor` anonymous volume.

2. **runkit7 transparent override (Layer 2) is DISABLED by default.** The unofficial PHP-8.4 runkit7
   build segfaults the process at shutdown whenever an internal function is redefined (even a no-op
   redefine, opcache on or off). `FunctionOverrider` also had an infinite-recursion bug (override bodies
   self-called). SAFE behaviour now comes from the `s_*()` shims + PSL + the Layer-3 error handler +
   PHPStan. Opt in with `COREPHP_ENABLE_RUNKIT_OVERRIDE=1` (experimental — expect shutdown instability).

3. **`eval()` cannot be blocked** via `php.ini` (language construct). PHPStan covers this statically. Never use `eval()`.

---

## Adding New Code — Checklist

When adding a new class to `opt/corephp-vm/std/src/`:

- [ ] Place it in the correct `core\*` sub-namespace
- [ ] Add `declare(strict_types=1);` at the top
- [ ] Add full PHPDoc (including `@throws`, `@param` types, `@return` type)
- [ ] Write unit tests in `opt/corephp-vm/std/tests/` (mirroring src/ structure)
- [ ] Run `make test` — all must pass
- [ ] Run `make lint` — PHPStan Level 9 must pass
- [ ] Update `docs/std/README.md` namespace tree if adding a new class
- [ ] Update `memory-bank/activeContext.md` to reflect the change

---

## Memory Bank Files (GOS Documentation)

For deeper context, read in this order:

1. `memory-bank/01_PROJECT_CHARTER.md` — Vision, success criteria, scope
2. `memory-bank/02_ARCHITECTURE_PRINCIPLES.md` — Hexagonal arch + SOLID applied
3. `memory-bank/03_AGENTIC_WORKFLOW.md` — SPARC framework + Makefile targets
4. `memory-bank/systemPatterns.md` — Runtime architecture diagrams
5. `memory-bank/techContext.md` — Tech stack, constraints, file locations
6. `memory-bank/activeContext.md` — Current state, recent changes, next steps
7. `memory-bank/progress.md` — Full change history
