# CorePHP — X/Twitter Launch Thread

> Copy-ready. Lead hook: **transparency** ("same app, no rewrite") + **zero silent failures**.
> Post only *after* the Q3 hard gate (RoadRunner worker crash fixed + a live persistent demo). A crashing "STABLE" demo on launch day is fatal to credibility.

---

## The thread

**1/ (hook)**
Your PHP app is one silent `false` away from a 3am 500.

`json_decode` returns `null` on bad input.
`file_get_contents` returns `false` when the file's gone.
`intval('abc')` returns `0`.

PHP lies to you — silently — in production.

CorePHP makes it stop. 🧵

**2/ (what it is — kill the "is this a framework?" fear immediately)**
CorePHP is a drop-in PHP 8.4 runtime.

Not a framework. Not a rewrite. Not a new stdlib to learn.

You point `FROM greicodex/corephp-vm` at your **existing** Symfony / Laravel / CodeIgniter app. On the happy path it's byte-for-byte plain PHP.

Then the dangerous parts stop failing silently.

**3/ (the proof shot — SAFE)**
[IMAGE: before/after code — plain PHP silently returns null → 500 later, vs CorePHP throwing a typed, traceable JsonException at the source]

Same code. Same signature. Same return value when it works.

When it *doesn't* work, you get a real exception — not a `null` that detonates three functions later.

**4/ (know at COMPILE time — the part nobody else does)**
You don't even have to wait for runtime.

`corephp audit` scans your app and tells you — at compile time — every place standing between you and a SAFE, SECURE, STABLE deploy:

▸ every unchecked json_decode / file_get_contents
▸ every unserialize / eval
▸ every static that leaks in a long-running worker

File. Line. Fix.

**5/ (the STABLE pillar)**
And it never cold-starts.

CorePHP runs your app in persistent, supervised workers (RoadRunner). One bad request can't take down the pool. Memory leaks get recycled instead of accumulating.

Persistent like the JVM — without adopting a single new framework idea.

**6/ (SECURE, briefly)**
`unserialize`, `eval`, `exec`, `system`, `shell_exec` — disabled at the engine level. Hardened `php.ini` by default. Fully auditable, no black boxes.

Safe. Secure. Stable. That's the whole pitch.

**7/ (the low-friction ask — widen the funnel)**
Not ready to swap your base image? Start smaller:

`composer require corephp/std`

and your stdlib stops lying today — in *any* project, no Docker required. *(coming v1.3 — see roadmap)*

**8/ (roadmap + CTA)**
It's open source and community-first. Here's the 12-month roadmap → [LINK TO VISUAL ROADMAP ARTIFACT]

⭐ Star it, and tell me: **which should I harden first — Symfony, Laravel, or CodeIgniter?**

[LINK TO REPO]

---

## Asset shot-list (build these before posting)

1. **Before/after code screenshot (T3)** — the single most important asset.
   - Left: vanilla PHP. `$data = json_decode($input); // null` → later `$data->foo` → `Attempt to read property on null` 500, no trace to the source.
   - Right: CorePHP. Same line throws `JsonException: Syntax error` *at the decode call*, with a stack trace. Dark theme, large font, minimal chrome.
2. **`corephp audit` terminal output (T4)** — a readiness report against a real Laravel/Symfony app. Show a summary line like `SAFE: 12 issues · SECURE: 3 · STABLE: 5` with 2–3 example findings (file:line + fix). Red/yellow/green.
3. **Compatibility matrix (reply / T5 alt)** — table: Symfony / Laravel / CodeIgniter / WordPress × runs unmodified? × silent errors surfaced. Green checks.
4. **Benchmark chart** — cold-start vs persistent p50/p95 latency, CorePHP vs PHP-FPM. Reuse the reproducible harness from v1.0.
5. **Visual roadmap page** — the Artifact linked in T8.

## Posting notes

- **Timing:** Tue–Thu, ~15:00 UTC (US morning + EU afternoon overlaps the PHP dev crowd).
- **Cross-post:** r/PHP, Show HN ("Show HN: CorePHP – run your existing PHP app with zero silent failures"), phpc.social (Mastodon), dev.to writeup.
- **Engage the ecosystem:** tag/mention RoadRunner and reference FrankenPHP/Swoole respectfully as neighbors, not competitors — CorePHP is about *safety*, not just persistence.
- **Pin T1.** Reply to your own thread over the following days with each proof asset as a standalone post.
- **Repo must be launch-ready first:** README hero matches this framing, `ROADMAP.md`, `CONTRIBUTING.md`, `good first issue`s, a working `docker run` quickstart.
