# 01 — Project Charter: CorePHP (PHP-JVM)

## Project Name
**CorePHP** — A "PHP-JVM" Base Docker Image

## Vision
Build a production-grade, persistent PHP runtime that mimics the JVM stability model. PHP traditionally re-initializes on every request; this project eliminates that by embedding PHP 8.3 CLI inside RoadRunner as a long-lived process — just like the JVM.

## Mission
Provide a **portable, hardened base Docker image** that any future PHP project can use as its `FROM` image, receiving:
- Persistent process model (no cold-starts)
- Type-safe standard library (`std`)
- Engine-level safety enforcement (runkit7 + php.ini + bootstrap)
- Zero silent failures — every error surface is a named exception

## Stakeholders
- Backend PHP teams adopting production-grade PHP
- VPS deployments (RoadRunner mode)
- Shared Hosting deployments (Simulated JVM mode via `.user.ini`)

## Success Criteria
1. `docker build -t corephp-vm .` completes successfully
2. RoadRunner handles PSR-7 HTTP requests without process death
3. Calling `json_decode('{invalid}')` throws `JsonDecodeException` (not returns `null`)
4. Calling `file_get_contents('/nonexistent')` throws `FileReadException` (not returns `false`)
5. `unserialize()` and unsafe functions are completely disabled
6. PHPStan Level 9 passes on `std` library
7. PHP-CS-Fixer enforces `declare(strict_types=1)` on all files

## Scope
- **In Scope:** Base image, std library, bootstrap sandbox, runkit7 overrides, enforcement tooling
- **Out of Scope:** Application-level business logic, ORM, framework integrations

## Timeline
Initial implementation: Single sprint
