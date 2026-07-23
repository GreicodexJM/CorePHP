# Tech Context: CorePHP (PHP-JVM)

## Technology Stack

| Component | Technology | Version |
|---|---|---|
| Runtime | PHP CLI | 8.5 |
| Base OS | Alpine Linux | 3.21+ |
| App Server | RoadRunner | 2024.1+ |
| Safe API | pure-PHP `s_*` shims + azjezz/psl | ^4.2 |
| Package Manager | Composer | 2.x |
| Logging | Monolog | 3.x |
| HTTP PSR | spiral/roadrunner-http | 3.x |
| Containerization | Docker | 24+ |
| Static Analysis | PHPStan | 1.x |
| Code Style | PHP-CS-Fixer | 3.x |
| Testing | PHPUnit | 11.x |

## Development Setup

### Prerequisites
- Docker 24+
- Docker Compose V2
- make

### Quick Start
```bash
git clone <repo>
cd CorePHP
make build    # Build corephp-vm Docker image
make up       # Start services
make shell    # Enter container
make test     # Run tests
make lint     # Run lint checks
```

### Building the Image
```bash
docker build -t corephp-vm:latest .
# Or via Makefile:
make build
```

## Technical Constraints

### Safety mechanism (runkit7 removed — 2026-07-23)
- CorePHP does **not** transparently override native functions. runkit7 was removed: its unofficial
  PHP 8.4 build segfaulted the process at shutdown on any internal-function redefine, and the approach
  (mutating the engine function table) is inherently fragile.
- SAFE behaviour is delivered in pure PHP, which is portable and stable:
  - pure-PHP `s_*()` shims + azjezz/psl (typed, throwing safe API),
  - the `bootstrap.php` error handler (warnings/notices → `ErrorException`, transparent),
  - dangerous primitives disabled in `php.ini` (`disable_functions`),
  - PHPStan Level 9 (static enforcement).
- Evaluated alternatives (uopz, purpose-built `zend_execute_internal` C extension, php-src fork) —
  deferred in favour of the userland approach for portability/stability. A forked/patched PHP was
  explicitly rejected (security-rebase burden; breaks the "it's still standard PHP" promise).

### RoadRunner Constraints
- Binary downloaded at Docker build time from GitHub releases
- `.rr.yaml` must specify `command: php worker.php`
- Workers restart after `max_jobs` requests to prevent memory leaks (configurable)
- Port 8080 is the default HTTP port

### PHP 8.5 / Alpine Constraints
- Alpine uses `musl libc` — some PECL extensions require extra build deps
- `$PHPIZE_DEPS` must be installed before `pecl install`
- Remove build deps after PECL install to keep image small

## File Locations in Container

| File | Path |
|---|---|
| PHP binary | `/usr/local/bin/php` |
| RoadRunner binary | `/usr/local/bin/rr` |
| bootstrap.php | `/opt/corephp-vm/bootstrap.php` |
| std library | `/opt/corephp-vm/std/` |
| php.ini | `/usr/local/etc/php/php.ini` |
| RoadRunner config | `/.rr.yaml` (working dir) |
| worker.php | `/app/worker.php` |
| PHP error log | `/var/log/php/error.log` |

## Dependency Management

The `std` library is a standalone Composer package with no external dependencies for its core classes. Monolog is a `require` dependency (for audit logging in `bootstrap.php`). The `std` package is installed globally via Composer path repository in the Dockerfile.
