# System Patterns: CorePHP (PHP-JVM)

## Runtime Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    Docker Container                      │
│                                                         │
│  ┌──────────────────────────────────────────────────┐   │
│  │              RoadRunner (port 8080)              │   │
│  │         Persistent HTTP Application Server       │   │
│  └─────────────────────┬────────────────────────────┘   │
│                        │ PSR-7 Request                  │
│  ┌─────────────────────▼────────────────────────────┐   │
│  │                 worker.php                       │   │
│  │          (Long-lived PHP process)                │   │
│  │   while (true) {                                 │   │
│  │     try { handleRequest(); }                     │   │
│  │     catch (Throwable $e) { audit($e); }          │   │
│  │   }                                              │   │
│  └─────────────────────┬────────────────────────────┘   │
│                        │ auto_prepend_file               │
│  ┌─────────────────────▼────────────────────────────┐   │
│  │              bootstrap.php                       │   │
│  │  1. set_error_handler → ErrorException           │   │
│  │  2. FunctionOverrider::install() (runkit7)       │   │
│  │  3. Monolog audit handler registered             │   │
│  └──────────────────────────────────────────────────┘   │
│                                                         │
│  ┌──────────────────────────────────────────────────┐   │
│  │               php.ini hardening                  │   │
│  │  disable_functions = unserialize, exec, ...      │   │
│  │  allow_url_fopen = Off                           │   │
│  │  runkit.internal_override = 1                    │   │
│  └──────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
```

## Startup Sequence

1. Container starts → RoadRunner reads `.rr.yaml`
2. RoadRunner spawns PHP worker(s) via `worker.php`
3. PHP loads `bootstrap.php` (via `auto_prepend_file`) **before** `worker.php` runs
4. `bootstrap.php` calls `FunctionOverrider::install()` → runkit7 redefines ~15 native functions
5. Worker enters the request loop — all unsafe functions are now replaced
6. On request: worker handles PSR-7, routes to application, returns response
7. On any Throwable: caught by worker loop, reported to Monolog, 500 response returned — **process does NOT die**

## Key Design Patterns

### Bootstrap / Prepend Pattern
The `auto_prepend_file` directive ensures `bootstrap.php` runs before ANY user code, including in shared hosting environments.

### Overrider Registry Pattern (`FunctionOverrider`)
All `runkit7_function_redefine()` calls are centralized in a single class with a static `install()` method. This makes the override inventory explicit and auditable.

### Value Object Pattern (HttpResponse)
`HttpResponse` is immutable. Once constructed from a curl response, its state never changes.

### Named Exception Hierarchy
```
\RuntimeException
├── \std\Net\Http\HttpException
├── \std\Security\Exceptions\SecurityException
└── \std\Security\Exceptions\EncodingException

\InvalidArgumentException
├── \std\Security\Safe\JsonDecodeException
├── \std\Security\Safe\JsonEncodeException
├── \std\Security\Safe\TypeCoercionException
├── \std\Security\Safe\FileReadException
├── \std\Security\Safe\FileWriteException
└── \std\Security\Safe\RegexException
```

## Critical Implementation Notes

1. **runkit7 + RoadRunner interaction:** `FunctionOverrider::install()` must be idempotent — check if the function is already redefined before calling `runkit7_function_redefine()` to avoid errors on repeated calls.

2. **Worker restart policy:** `.rr.yaml` sets `max_jobs` to limit memory growth (e.g., restart worker after 500 requests). This is normal and expected — it's NOT a crash.

3. **disable_functions limitation:** Cannot disable language constructs (`eval`, `include`, `require`). PHPStan rules cover these statically.

4. **TypedCollection and primitives:** For primitive types (string, int, float, bool), use PHP's built-in type names. `instanceof` doesn't work on primitives — use `get_debug_type()` instead.
