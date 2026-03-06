# Progress: CorePHP (PHP-JVM)

## What Works
- ✅ Memory Bank initialized (all GOS-required files)
- ✅ Makefile + Docker Compose (production + development override)
- ✅ **Dockerfile builds successfully** (`docker build -t php-jvm:latest .` exit code 0)
  - PHP 8.3 CLI Alpine + runkit7 (built from GitHub source) + RoadRunner 2024.1.5
  - `auto_prepend_file=""` override prevents bootstrap from running during `composer install`
- ✅ Hardened php.ini (disable_functions + runkit.internal_override + prepend sandbox)
- ✅ .rr.yaml (RoadRunner config with worker pool + supervisor)
- ✅ worker.php (PSR-7 request loop with global Throwable catch)
- ✅ bootstrap.php (error handler + FunctionOverrider + global class aliases)
- ✅ std\Engine\FunctionOverrider (runkit7 overrides for 11 native functions)
- ✅ **DX Layer — short namespace API (std\)**
  - `std\StrictObject` — undefined property guard
  - `std\Any` — universal strict base class (`BaseObject` alias)
  - `std\Vec` — typed sequential list (`ArrayList` alias)
  - `std\Dict` — typed key-value store (`Dict` alias in global ns)
  - `std\IO` — safe file + HTTP facade (`IO` alias in global ns)
  - `std\Security\Safe\Safe` — safe stdlib (`Safe` alias in global ns)
  - `src/functions.php` — global shims (`s_json`, `s_int`, `s_float`, `s_file`, `s_write`, `s_get`, `s_post`)
- ✅ `std\Internal\Array\TypedCollection` (Pillar 1)
- ✅ `std\Net\Http\HttpClient + HttpResponse + HttpException` (Pillar 2)
- ✅ `std\Security\Safe\Safe` + 6 typed exceptions (Pillar 3)
- ✅ `.php-cs-fixer.dist.php` (declare strict_types enforcement)
- ✅ `phpstan.neon` (Level 9)
- ✅ `ci/lint.sh` + `ci/test.sh`
- ✅ `composer.json` (root) + `opt/php-jvm/std/composer.json` (std library)
- ✅ `.phpstorm.meta.php` (IDE autocomplete for global aliases)
- ✅ `.gitignore`, `.user.ini` (Shared Hosting mode)
- ✅ `README.md` (full documentation)

## What's Left to Build
- [ ] PHPUnit tests for std library (TDD follow-up task)

## Current Status
🟢 **FULLY COMPLETE** — Image builds and all files are generated.

## Known Issues / Design Notes
- `eval()` is a language construct and cannot be blocked in php.ini; PHPStan covers statically
- `auto_prepend_file=""` override needed for `composer install` steps in Dockerfile (bootstrap requires Psr\Log which is not installed at that point)
- runkit7 must be built from GitHub source — PECL registry does not have the package
- `std\Vec` extends `TypedCollection` which is fine; PHPStan will follow the chain

## Evolution of Decisions
- 2026-03-06: Project initialized from `docs/PROJECT.md`
- 2026-03-06: Three Safety Pillars architecture finalized
- 2026-03-06: runkit7 PECL registry failure → switched to `git clone + phpize + make`
- 2026-03-06: DX Layer added: short namespaces, global aliases, function shims
- 2026-03-06: `std\StrictObject` moved from bootstrap.php inline definition → std library
- 2026-03-06: `composer install` in Dockerfile updated to use `-d auto_prepend_file=""`
- 2026-03-06: Docker build confirmed working — exit code 0
