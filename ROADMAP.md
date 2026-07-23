# CorePHP Roadmap

> **PHP that stops lying to you.**
> A drop-in production runtime that replaces PHP's dangerous, silent-failure primitives with safe ones — **same app, no rewrite, no framework.**

CorePHP is a hardened PHP 8.4 base Docker image (`greicodex/corephp-vm`). You `FROM` it, drop your existing application in — **Symfony, Laravel, CodeIgniter, or plain PHP** — and it runs exactly as before **on the happy path**. But the moment something goes wrong, PHP's silent `null` / `false` / `0` returns become proper, standard exceptions you can catch, log, and trace.

And you don't have to wait until runtime to find out: CorePHP tells you **at compile time** exactly what in your app stands between you and a SAFE, SECURE, STABLE deployment.

This document is the public, living roadmap. It's a **12-month vision** organized around three promises — **SAFE, SECURE, STABLE** — not a set of dated commitments. Milestones ship when they're ready.

---

## Philosophy

CorePHP is built on one conviction: **a silent failure is a bug, not a feature.**

- **Transparent, not intrusive.** On the happy path, CorePHP *is* plain PHP. Same functions, same signatures, same return values. If adopting CorePHP means rewriting your app, we've failed.
- **Not a framework.** We don't ask you to restructure your code, adopt our router, or learn our conventions. We replace the *dangerous pieces of the language*, underneath your app.
- **Fail loud and fast.** Every error surface becomes a named, standard exception. Fatals become traceable. Leaks get contained. No black boxes (Glass Box Protocol — everything is auditable).

### Non-goals

- ❌ Being a web framework, router, or ORM. Your framework stays your framework.
- ❌ Requiring you to rewrite your application.
- ❌ Inventing a new stdlib you *must* learn. (The typed `Vec`/`Dict`/`s_*` helpers exist, but they're **opt-in sugar** — never a prerequisite.)

### Two feedback loops: know at compile time, be safe at runtime

CorePHP protects your app at two moments, so nothing silent ever reaches production:

| Loop | When | What it does |
|---|---|---|
| 🔍 **Compile-time audit** | Before deploy (static analysis / CI) | Scans your existing app and reports every SAFE/SECURE/STABLE risk — unchecked `json_decode`, `unserialize`, request-scoped state that leaks in a long-running worker — with file, line, and fix. |
| 🛡️ **Runtime hardening** | While serving requests | Turns whatever slips through into a proper exception, contains leaks, and keeps one bad request from taking down the pool. |

The audit is framework-aware: point it at a **Symfony, Laravel, or CodeIgniter** codebase and it understands the idioms.

---

## The three pillars

| Pillar | The promise | How |
|---|---|---|
| 🛡️ **SAFE** | No more silent failures or untraceable fatals. | PHP's silent-failure functions are replaced with drop-in safe versions that throw standard exceptions. Fatals become catchable, logged, and traced. |
| 🔒 **SECURE** | No dangerous primitives reach production. | `unserialize`, `eval`, `exec`, `system`, `shell_exec` and friends disabled; hardened `php.ini`; fully auditable. |
| ⚙️ **STABLE** | One bad request can't take down your app; leaks can't accumulate forever. | Persistent, supervised worker processes (RoadRunner) with automatic recycling; per-request isolation; zero cold starts. |

### Strict by default, opt-out when you need it

CorePHP ships **strict**: safe functions throw on failure everywhere. For legacy apps that deliberately rely on old behavior (e.g. checking `json_decode(...) === null`), a **compat mode** relaxes specific functions so you can adopt gradually instead of all at once.

```dotenv
# Strict everywhere (default)
COREPHP_STRICT=1

# Relax specific functions for a legacy app during migration
COREPHP_COMPAT_FUNCTIONS=json_decode,intval
```

---

## Where we are today (pre-1.0)

Already shipped and green:

- ✅ Image builds and publishes to Docker Hub (`greicodex/corephp-vm`) via CI/CD, multi-arch aware, with auto-synced README.
- ✅ Boot-time override engine (runkit7) that redefines native footgun functions to throw instead of silently failing.
- ✅ Hardened `php.ini` — 34 dangerous functions disabled, sandbox `auto_prepend_file` bootstrap.
- ✅ Typed, PSL-backed helper library (`core\` namespace) + global `s_*` shims (pure-PHP safe replacements).
- ✅ 214 unit tests passing, PHPStan Level 9 clean, PHP-CS-Fixer enforced.
- ✅ Full documentation site and migration guide.
- ✅ **Persistent RoadRunner worker serves HTTP end-to-end** (`/health` → 200, worker pool stable). The STABLE pillar is verified.
- ✅ **runkit7 removed.** SAFE is delivered the portable, stable way — pure-PHP `s_*` shims + PSL + the boot error handler (warnings/notices → exceptions) + PHPStan — with no engine-patching C extension. (The transparent runkit override segfaulted on PHP 8.4; userland was chosen for portability and stability.)

Legend: ✅ done · 🚧 in progress · ⏳ planned

---

## Q3 2026 — *Make the headline true* → **v1.0 GA**

**Goal:** the drop-in stability promise is *demonstrably* real before we tell the world.

- ✅ **Fix the RoadRunner worker crash.** Done — verified persistent request loop (`/health` → 200, stable worker pool). Root cause was four chained silent failures (missing `ext-sockets`, swallowed `composer` step, `getmypid` wrongly in `disable_functions`, compose mount shadowing vendor); runkit7 removed in the process.
- ✅ **Drop-in proof.** Done — one-command demo in [`demo/`](demo/): the same unmodified app hits a config-drift bug that stock PHP ships silently (HTTP 200, a $0 order) while CorePHP catches it at the exact line with a full traced audit entry (HTTP 500) — and the worker keeps serving. Run it with `./run.sh`.
- ✅ **Happy-path compatibility test suite.** Done — `HappyPathParityTest` asserts every `s_*()` safe replacement returns byte-identical results to the native PHP function on valid input (only the failure path differs). Part of the CI gate (241 tests). It already caught and fixed a real `s_str()`/`strval()` divergence.
- ✅ **Reproducible benchmark.** Done — one-command harness in [`benchmarks/`](benchmarks/) comparing persistent (CorePHP) vs cold-start (PHP-FPM, opcache on) on a byte-identical app. **~6× throughput, p50 3.8ms vs 24ms.** Anyone can rerun it with `./run.sh`.
- ✅ **v1.0.0 released.** Tagged and published to Docker Hub — **multi-arch (amd64 + arm64)**: `docker pull greicodex/corephp-vm:1.0.0`.
- ✅ **Public launch** — announced on X. Q3 complete: the runtime works, every proof is built and reproducible, and v1.0.0 is GA on Docker Hub.

## Q4 2026 — *Know before you ship* → **v1.1 – v1.2**

**Goal:** ship the compile-time audit and widen SAFE coverage. This is the quarter that turns CorePHP from "a safer runtime" into "a runtime that tells you *why* your app isn't safe yet."

- ⏳ **`corephp audit` — the compile-time advisor (MVP).** A CLI + PHPStan-backed analyzer that scans your codebase and emits a **SAFE / SECURE / STABLE readiness report**: every unchecked `json_decode`/`file_get_contents`, every `unserialize`/`eval`, and every request-scoped global or static that leaks in a long-running worker — each with file, line, severity, and the fix. Runs locally and in CI (non-zero exit on regressions).
- ⏳ **Expand safe-override coverage** across PHP's silent-failure surface (array, string, date/time, and more) — tracked as a public "footgun catalog" the audit maps to.
- ⏳ **Fatal → traceable exception + structured logs.** Every fatal carries a stack trace and request context; nothing dies silently.
- ⏳ **Strictness / compat modes** (`COREPHP_STRICT`, per-function opt-out) shipped and documented for gradual adoption.
- ⏳ **Code coverage** (PCOV in the image) + comparative benchmarks vs. PHP-FPM, FrankenPHP, Swoole.

## Q1 2027 — *Run your framework, unmodified* → **v1.3**

**Goal:** first-class support for the frameworks people actually run, plus the widest-funnel adoption path.

- ⏳ **Framework support: Symfony, Laravel, CodeIgniter.** Framework-aware audit rules (understands each framework's idioms and long-running-worker pitfalls) + runtime adapters (Laravel Octane–style, Symfony Runtime, PSR-15). Run your app **as-is**.
- ⏳ **Unmodified compatibility matrix.** Run real Symfony / Laravel / CodeIgniter (and WordPress) apps as-is on CorePHP and publish exactly what the audit finds and what happens to the silent errors. The definitive transparency proof — and the launch's most screenshot-worthy asset.
- ⏳ **`corephp/std` on Packagist** — the safe stdlib functions usable in *any* project, **no Docker required**: `composer require corephp/std` and your stdlib stops lying today.
- ⏳ **One-command "wrap my existing app"** scaffolder / template.

## Q2 2027 — *Enforce it & scale it* → **v2.0 (north star)**

**Goal:** the audit becomes a CI gate; production-grade reliability.

- ⏳ **CorePHP audit → shippable PHPStan ruleset.** Downstream projects enforce "no silent failures" at CI time as a standard rule level; IDE integration surfaces findings inline.
- ⏳ **Memory-leak detection + auto-recycle tuning** — leaks are contained, never fatal.
- ⏳ **Observability:** OpenTelemetry hooks + health/metrics endpoints.
- ⏳ **Circuit breakers** for downstream dependency failures.
- ⏳ **PSL 5.x migration** (once `php-standard-library/phpstan-extension` unblocks) for performance + `Psl\Crypto`.
- ✅ **Multi-arch** (amd64 + arm64) — shipped early, in v1.0.0.

---

## Get involved

CorePHP is open source and community-first. The best way to shape it:

- ⭐ **Star the repo** and watch releases.
- 🗳️ **Tell us which framework adapter or footgun to prioritize** — open a Discussion.
- 🐛 **Try it on your app** and report what breaks (or, ideally, what silently doesn't anymore).
- 🔧 See `CONTRIBUTING.md` and issues tagged `good first issue`.

*This roadmap is a direction, not a contract — it will evolve with what the community needs. Dates are themes, not deadlines.*
