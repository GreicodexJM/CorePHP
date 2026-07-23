# CorePHP Drop-in Proof

The **same unmodified application**, on two runtimes, hitting one realistic latent bug —
a **config-drift** (ops renamed the pricing key `unit_price` → `price`). No CorePHP APIs,
no `s_*()` shims, no `try/catch` in the app. Just the code you already have.

## Run it

```bash
# From the repo root, build the image once:
make build          # or: docker build -t corephp-vm:latest .

cd demo
./run.sh
```

## What you see

```
══ Act 1 — the same app on STOCK PHP ══
GET /order  (pricing config drifted: 'unit_price' → 'price')

  HTTP 200   {"item":"widget","qty":3,"unit_price":null,"total_cents":0}
  ✗ Shipped a widget for $0.00 — HTTP 200, no error to anyone.
  The warning is buried in the log, the app carried on:
    PHP Warning:  Undefined array key "unit_price" in /demo/app.php on line 47

══ Act 2 — the SAME app on CorePHP ══
GET /order  (identical code, identical config)

  HTTP 500   {"error":"Internal Server Error","type":"ErrorException",
              "detail":"Undefined array key \"unit_price\"","at":"app.php:47"}
  ✓ Caught at the source — HTTP 500, typed and pinpointed.
  Full audit trail (stack trace + request path), logged automatically:
    [ErrorException] Undefined array key "unit_price" in /demo/app.php:47
    #0 /demo/app.php(47): {closure bootstrap.php}(2, 'Undefined array…', …)
    #1 /demo/app.php(33): handle_order()
    #2 /demo/worker.php(34): app_dispatch('/order')

══ …and the worker kept serving ══
  GET /health → HTTP 200  (the fatal did not take the process down)
```

## Why this happens (and why it's honest)

| | Stock PHP-FPM / CLI | CorePHP |
|---|---|---|
| The undefined-key access | **E_WARNING** — logged and swallowed; execution continues with `null` | the boot error handler turns it into a typed **`ErrorException`** at the exact line |
| Result | **HTTP 200**, order total `$0` — silently wrong | **HTTP 500**, caught and rejected |
| Traceability | a bare warning line, no request correlation | structured audit entry: exception type, `file:line`, **full stack trace**, request path |
| The process | fresh per request (never sees the pattern) | worker **catches, audits, and keeps serving** |

CorePHP does **not** transparently rewrite native functions (that was the runkit7 approach — removed
because it segfaulted on PHP 8.4). This advantage is delivered by the parts that *are* transparent and
stable for an unmodified app:

1. **The boot error handler** (`bootstrap.php`, `auto_prepend_file`) — converts PHP's silent
   warnings/notices into loud, typed, catchable exceptions. Stock PHP does not do this by default.
2. **The worker safety net** — every `Throwable` is audited with full context and a clean 500 is
   returned; one bad request never takes down the pool.

For the *silent-return* class that emits no warning (`json_decode()` → `null`, `intval('x')` → `0`),
use the pure-PHP `s_*()` shims (`s_json`, `s_int`, …) — a one-word change that makes those loud too.

## Files

| File | Purpose |
|---|---|
| `app/app.php` | the unmodified application (`/health`, `/order`) with the latent config-drift bug |
| `app/data/pricing.json` | the drifted config (`price` instead of `unit_price`) |
| `app/worker.php` | CorePHP entrypoint — persistent loop, catch → audit |
| `app/server.php` | stock-PHP entrypoint (`php -S`), default error handling |
| `.rr.demo.yaml` | RoadRunner config for the demo worker |
| `docker-compose.demo.yaml` | brings up both runtimes (CorePHP :8092, stock :8093) |
| `run.sh` | plays both acts and prints the verdict |
