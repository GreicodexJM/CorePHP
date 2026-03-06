# 03 — Agentic Workflow: CorePHP (PHP-JVM)

## SPARC Development Framework

### S — Specification
- Project requirements defined in `docs/PROJECT.md`
- Three Safety Pillars defined for `std` library
- Docker base image as portable artifact

### P — Pseudocode / Plan
1. Initialize Memory Bank
2. Scaffold Docker + Makefile infrastructure
3. Build hardened `php.ini`
4. Implement `std` library (three pillars)
5. Implement `bootstrap.php` sandbox
6. Implement `FunctionOverrider` (runkit7)
7. Configure enforcement tooling
8. Write documentation

### A — Architecture
- See `02_ARCHITECTURE_PRINCIPLES.md`

### R — Refinement
- TDD: Write failing tests before implementation
- PHPStan Level 9 must pass on `std`
- PHP-CS-Fixer must pass on all PHP files

### C — Completion
- `docker build -t php-jvm .` succeeds
- All CI checks pass (`make lint`, `make test`)
- Memory Bank updated

---

## GOS Makefile Targets

| Target | Description |
|---|---|
| `make build` | Build the Docker image |
| `make up` | Start Docker Compose services |
| `make down` | Stop Docker Compose services |
| `make shell` | Open a shell in the running container |
| `make test` | Run PHPUnit tests via ci/test.sh |
| `make lint` | Run PHP-CS-Fixer + PHPStan via ci/lint.sh |
| `make lint-fix` | Auto-fix PHP-CS-Fixer violations |
| `make rr-start` | Start RoadRunner worker |

---

## CI Pipeline (ci/ directory)

- `ci/lint.sh` — Runs PHP-CS-Fixer (dry-run) + PHPStan Level 9
- `ci/test.sh` — Runs PHPUnit test suite

All CI scripts are executed **inside the Docker container** to ensure reproducibility.

---

## Development Conventions

1. All PHP files MUST begin with `declare(strict_types=1);`
2. All classes MUST be namespaced under `std\*`
3. All public methods MUST have full return type declarations
4. No `mixed` types in public interfaces
5. No `array` types — use `TypedCollection` instead
6. No bare `catch (\Throwable $e) { /* silence */ }` blocks
7. All exceptions MUST be typed (no bare `\Exception`)
