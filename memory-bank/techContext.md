# Tech Context: CorePHP (PHP-JVM)

## Technology Stack

| Component | Technology | Version |
|---|---|---|
| Runtime | PHP CLI | 8.3 |
| Base OS | Alpine Linux | 3.19+ |
| App Server | RoadRunner | 2024.1+ |
| Function Override | runkit7 (PECL) | 4.0.0a6+ |
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

### runkit7 Constraints
- Requires `runkit.internal_override = 1` in `php.ini` to override built-ins
- `runkit7_function_redefine()` cannot override language constructs (`eval`, `echo`, `include`)
- Must be installed as PECL extension; not available in `pecl install` for all Alpine versions — use `--build-arg` for version pinning
- The `runkit7-alpha` tag is the current stable release name on PECL

### RoadRunner Constraints
- Binary downloaded at Docker build time from GitHub releases
- `.rr.yaml` must specify `command: php worker.php`
- Workers restart after `max_jobs` requests to prevent memory leaks (configurable)
- Port 8080 is the default HTTP port

### PHP 8.3 / Alpine Constraints
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
