# GitHub Copilot Workspace Instructions — CorePHP

> Applied to all Copilot Chat and inline completions in this workspace.

---

## Project Summary

CorePHP is a **PHP 8.4 base Docker image** that runs PHP as a persistent long-lived process (via RoadRunner) and replaces PHP's silent-failure standard library with one that throws typed exceptions on every error. The `std` library lives under the `core\` namespace.

**Docker Hub:** `greicodex/corephp-vm`

---

## Dev Commands

```bash
make build      # Build Docker image
make up         # Start Docker Compose (detached)
make test       # Run PHPUnit test suite (inside Docker)
make lint       # PHPStan Level 9 + PHP-CS-Fixer (inside Docker)
make lint-fix   # Auto-fix style violations
make shell      # Enter container shell
```

All commands run **inside Docker**. Never run `php` or `composer` directly on the host.

---

## Architecture at a Glance

### Three Enforcement Layers
| Layer | Mechanism | When |
|---|---|---|
| Static | PHPStan Level 9 + PHP-CS-Fixer | CI |
| Boot | `runkit7` `FunctionOverrider` | Process startup |
| Runtime | `bootstrap.php` error handler | Every request |

### Namespace Tree
```
core\Any                         — Strict base class
core\StrictObject                — Undefined property guard
core\Vec                         — Typed sequential list
core\Dict                        — Typed key-value store
core\IO                          — File + HTTP facade
core\Engine\FunctionOverrider    — runkit7 boot installer
core\Internal\Array\TypedCollection — Type-safe base collection
core\Net\Http\HttpClient         — curl HTTP client
core\Net\Http\HttpResponse       — Immutable response VO
core\Net\Http\HttpException      — HTTP + transport failures
core\Security\Exceptions\SecurityException
core\Security\Exceptions\EncodingException
```

---

## Coding Conventions

### Always
- First line of every PHP file: `declare(strict_types=1);`
- All classes: `core\*` namespace
- All public methods: explicit return types
- All exceptions: typed and named
- Write tests BEFORE implementation (TDD)

### Never
- `mixed` in public method signatures
- Bare `array` type in public interfaces → use `core\Vec` or `core\Dict`
- `json_decode()` / `json_encode()` directly → use `s_json()` / `s_enc()`
- `file_get_contents()` / `file_put_contents()` → use `s_file()` / `s_write()`
- `intval()` / `floatval()` → use `s_int()` / `s_float()`
- `unserialize()`, `exec()`, `shell_exec()`, `system()`, `eval()`
- Reference `core\Security\Safe\Safe` — **that class was deleted**
- Upgrade `azjezz/psl` past `^4.2` — phpstan extension incompatible with PSL ≥ 5.0

---

## Global Shim Functions (no `use` required)

```php
// JSON
s_json(string $json, bool $assoc = true): mixed
s_enc(mixed $val, bool $pretty = false): string

// Type coercion
s_int(mixed $val): int
s_float(mixed $val): float
s_str(mixed $val): string
s_bool(mixed $val): bool

// File I/O
s_file(string $path): string
s_write(string $path, string $data, bool $append = false): int
s_append(string $path, string $data): int

// Regex
s_match(string $pattern, string $subject): bool
s_regex(string $pattern, string $subject): ?array
s_regex_all(string $pattern, string $subject): array

// Environment
s_env(string $key): string          // throws if not set
s_env_or(string $key, string $default): string

// HTTP
s_get(string $url, array $headers = []): core\Net\Http\HttpResponse
s_post(string $url, array|string $body = [], array $headers = []): core\Net\Http\HttpResponse

// Collection bridge
arr_to_list(string $type, array $items): core\Vec
arr_to_dict(string $type, array $data): core\Dict
vec_filter(iterable $list, callable $fn): array
vec_map(iterable $list, callable $fn): array
dict_filter(array $dict, callable $fn): array
dict_map(array $dict, callable $fn): array
dict_merge(array ...$dicts): array
```

---

## Exception Types to Use

```php
Psl\Json\Exception                       // JSON decode/encode failures
Psl\File\Exception\RuntimeException      // File read/write failures
Psl\Type\Exception\CoercionException     // Type coercion failures
Psl\Regex\Exception\RuntimeException     // Regex failures
core\Net\Http\HttpException              // HTTP transport + status failures
core\Security\Exceptions\SecurityException
core\Security\Exceptions\EncodingException
```

---

## File Locations

| What | Where |
|---|---|
| std library source | `opt/corephp-vm/std/src/` |
| PHPUnit tests | `opt/corephp-vm/std/tests/` |
| Bootstrap | `opt/corephp-vm/bootstrap.php` |
| PHP config | `config/php.ini` |
| All dev commands | `Makefile` |
| Full agent docs | `CLAUDE.md` |
| Project history | `memory-bank/` |

---

## Gotchas
- `TypedCollection` is NOT `final` — `Vec` extends it
- `HttpResponse::header()` returns `?string` — use `requireHeader()` to throw on missing
- `FunctionOverrider::install()` is idempotent — safe to call multiple times
- RoadRunner `WorkerAllocate: EOF` on `make up` = missing `spiral/roadrunner-worker` in vendor; fix with `make shell` + `composer install`
