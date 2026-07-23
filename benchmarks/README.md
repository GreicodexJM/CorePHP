# CorePHP Benchmarks

Reproducible comparison of the two PHP process models on the **identical** application:

- **CorePHP** — persistent RoadRunner workers (the interpreter + your bootstrap initialise **once**, then handle many requests), *with* CorePHP's hardening active (auto-prepend bootstrap + `disable_functions`).
- **PHP-FPM** — the traditional model (the process dies after each request, so everything re-initialises **every** request), with **opcache on** — every fair advantage.

## Run it

```bash
# From the repo root, build the image once:
make build          # or: docker build -t corephp-vm:latest .

# Then run the benchmark:
cd benchmarks
./run.sh
# knobs: DURATION=30s CONNS=100 THREADS=8 ./run.sh
```

`run.sh` builds the PHP-FPM baseline, starts both stacks, **verifies both return byte-identical
responses**, load-tests each with [`wrk`](https://github.com/wg/wrk) (run in a container — nothing
to install on the host), and writes [`RESULTS.md`](./RESULTS.md).

## Latest results

See [`RESULTS.md`](./RESULTS.md). On a 12-core machine, CorePHP served **~5.2× the throughput** of
PHP-FPM (10,792 vs 2,061 req/s) at **p50 4ms vs 21ms** — with byte-identical output.

## What is actually being measured (read this)

The app ([`app/app.php`](./app/app.php)) has two phases, mirroring a real application:

| Phase | What it does | CorePHP | PHP-FPM |
|---|---|---|---|
| `bench_bootstrap()` | framework-style init — config tree, compiled route table, service container | runs **once per worker** | runs **on every request** |
| `bench_handle($ctx)` | the per-request work, using the bootstrapped context | every request | every request |

**This is the whole point, and it's honest:** persistence eliminates *per-request bootstrap*, not
compute. A cold-start runtime pays framework initialisation on every single request; a persistent
one pays it once. `opcache` caches compiled **opcodes**, but **not** the runtime cost of *executing*
that bootstrap (building arrays, compiling routes, wiring services) — so PHP-FPM pays it every
request even with opcache on. This is exactly why [Laravel Octane](https://laravel.com/docs/octane)
and Swoole exist; CorePHP gives any app the same benefit without adopting a framework.

### Honest caveats

- **A pure-CPU endpoint with no bootstrap shows little-to-no gain** (persistence has nothing to
  amortize, and RoadRunner adds a small IPC cost). The win scales with how much your app does
  per-request *before* it gets to the actual request handling — which, for real frameworks, is a lot.
- Synthetic workload on a single machine; no database or network I/O. Absolute numbers vary by
  hardware — **the ratio is the signal**, and you can reproduce it yourself with `./run.sh`.
- Both runtimes use 4 workers; PHP-FPM keeps its default dynamic pool. Tune `CONNS`/`THREADS` and the
  worker counts (`.rr.bench.yaml`, FPM `www.conf`) to match your target hardware.

## Files

| File | Purpose |
|---|---|
| `app/app.php` | shared workload (`bench_bootstrap` + `bench_handle`), dependency-free |
| `app/worker.php` | CorePHP entrypoint — bootstrap once, then the request loop |
| `app/fpm-index.php` | PHP-FPM entrypoint — bootstrap + handle every request |
| `.rr.bench.yaml` | RoadRunner config for the benchmark worker |
| `fpm/` | PHP-FPM baseline image (opcache on) + nginx config |
| `docker-compose.bench.yaml` | brings up both stacks (CorePHP :8090, PHP-FPM :8091) |
| `run.sh` | orchestrates the run and writes `RESULTS.md` |
