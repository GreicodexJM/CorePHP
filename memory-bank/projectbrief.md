# Project Brief: CorePHP (PHP-JVM)

## Summary
CorePHP is a hardened PHP 8.3 base Docker image that gives PHP the persistence, type-safety, and stability characteristics of a JVM-based runtime. It uses RoadRunner as the persistent process server, runkit7 for engine-level function overrides, and a custom `std` standard library to eliminate silent failures.

## Core Goals
1. **Persistent Runtime** — PHP process stays alive across requests (like a JVM)
2. **Zero Silent Failures** — Every PHP function that returns `false`/`null` on error is replaced with one that throws a typed exception
3. **Engine-Level Safety** — Dangerous functions disabled in `php.ini` + runkit7 overrides
4. **Type Safety** — `TypedCollection` replaces native mixed arrays; `StrictObject` prevents undefined property access
5. **Portable** — Single `docker build -t corephp-vm .` produces a reusable base image

## Key Technologies
- PHP 8.3 CLI (Alpine)
- RoadRunner v2024 (application server)
- runkit7 (PECL extension for function override)
- Composer (package manager for `std` library)
- PHPStan Level 9 + PHP-CS-Fixer (enforcement tooling)
- Monolog (audit logging)
- Docker + Docker Compose
