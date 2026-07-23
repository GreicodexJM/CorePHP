<p align="center">
  <a href="https://github.com/GreicodexJM">
    <img src="docs/greicodex-logo.png" alt="Greicodex" width="160" />
  </a>
</p>
<p align="center">
  <img src="docs/corephp-logo.png" alt="CorePHP Logo" width="220" />
</p>

# CorePHP — Base Docker Image

[![Build & Push](https://github.com/GreicodexJM/CorePHP/actions/workflows/docker-publish.yml/badge.svg)](https://github.com/GreicodexJM/CorePHP/actions/workflows/docker-publish.yml)
[![Docs](https://github.com/GreicodexJM/CorePHP/actions/workflows/pages.yml/badge.svg)](https://greicodexjm.github.io/CorePHP/)
[![Docker Hub](https://img.shields.io/docker/v/greicodex/corephp-vm?logo=docker&label=greicodex%2Fcorephp-vm)](https://hub.docker.com/r/greicodex/corephp-vm)
[![PHP 8.4](https://img.shields.io/badge/PHP-8.4-blue?logo=php)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

> **A production-grade, persistent PHP 8.4 runtime that brings JVM-like stability to PHP.**

PHP traditionally re-initializes on every request. CorePHP eliminates this by running PHP inside **RoadRunner** as a long-lived process — just like the JVM. It also replaces PHP's silent-failure standard library with one that throws typed exceptions on every error.

## 🐳 Pull from Docker Hub

```bash
# Latest stable release
docker pull greicodex/corephp-vm:latest

# Specific version
docker pull greicodex/corephp-vm:1.0.0

# Latest development build (main branch)
docker pull greicodex/corephp-vm:edge
```

Use as your base image:

```dockerfile
FROM greicodex/corephp-vm:latest
COPY . /app
```

---

## 🏗️ Architecture

```
┌─────────────────────────────────────────────────┐
│              Docker Container                   │
│                                                 │
│  RoadRunner (port 8080)                         │
│    └── worker.php (long-lived PHP process)      │
│          └── bootstrap.php (auto_prepend_file)  │
│                ├── Error handler → ErrorException│
│                ├── s_*() safe shims + PSL        │
│                └── StrictObject                 │
│                                                 │
│  php.ini hardening                              │
│    ├── disable_functions (unserialize, exec...) │
│    └── allow_url_fopen = Off                    │
└─────────────────────────────────────────────────┘
```

### Three Enforcement Layers

| Layer | Mechanism | When |
|---|---|---|
| **Static** | PHPStan Level 9 + PHP-CS-Fixer | CI / pre-commit |
| **Safe API** | pure-PHP `s_*()` shims + PSL (typed, throwing) | Wherever you call them |
| **Runtime** | `bootstrap.php` error handler (warnings/notices → exceptions) | Every request |

---

## 🚀 Quick Start (VPS Mode)

### 1. Build the base image

```bash
docker build -t corephp-vm:latest .
```

### 2. Use it as a base image in your project

```dockerfile
FROM corephp-vm:latest

WORKDIR /app
COPY . .
RUN composer install --no-dev --optimize-autoloader

CMD ["rr", "serve", "-c", "/app/.rr.yaml"]
```

### 3. Start with Docker Compose

```bash
make up
```

Your application is now running at `http://localhost:8080`.

---

## 🏠 Shared Hosting Mode (Simulated JVM)

If you cannot use Docker (shared hosting, cPanel, Plesk), you can use a subset of PHP-JVM features via `.user.ini`:

### Setup

1. Upload `opt/corephp-vm/` to a location **outside your web root** (e.g., `/home/user/corephp-vm/`)
2. Install the `std` library: `cd opt/corephp-vm/std && composer install --no-dev`
3. Edit `.user.ini` and set the correct path to `bootstrap.php`
4. Upload `.user.ini` to your web root (e.g., `public_html/`)

### Limitations vs Full Docker Mode

| Feature | Docker + RoadRunner | Shared Hosting (.user.ini) |
|---|---|---|
| Persistent process | ✅ | ❌ (restarts per request) |
| bootstrap.php sandbox | ✅ | ✅ |
| StrictObject | ✅ | ✅ |
| Global `s_*()` shims | ✅ | ✅ |
| Session hardening | ✅ | ✅ |
| Error → Exception | ✅ | ✅ |

---

## 📦 Standard Library (`std`)

The `std` library is automatically loaded via Composer. All classes are under the `core\` namespace.

### Pillar 1 — `core\Internal\Array\TypedCollection`

A type-safe replacement for native PHP arrays:

```php
use core\Internal\Array\TypedCollection;

// Class type enforcement
$users = new TypedCollection(User::class);
$users->add(new User('Alice')); // OK
$users->add('not a user');      // throws InvalidArgumentException immediately

// Primitive type enforcement
$ids = new TypedCollection('int');
$ids->add(42);    // OK
$ids->add('foo'); // throws InvalidArgumentException

// Iteration
foreach ($users as $user) {
    echo $user->name . PHP_EOL;
}

// Filtering
$admins = $users->filter(fn(User $u) => $u->isAdmin());
```

### Pillar 2 — `core\Net\Http\HttpClient`

A curl wrapper that converts ALL HTTP failures into exceptions:

```php
use core\Net\Http\HttpClient;
use core\Net\Http\HttpException;

$client = new HttpClient(timeout: 10, strictStatus: true);

try {
    $response = $client->get('https://api.example.com/users');
    $users    = $response->json(associative: true);   // throws JsonDecodeException if body is not JSON
    $status   = $response->statusCode();               // 200
    $type     = $response->header('content-type');     // 'application/json'

    // POST with JSON body (array → auto-encoded)
    $created = $client->post('https://api.example.com/users', ['name' => 'Alice']);

} catch (HttpException $e) {
    // curl error, connection refused, timeout, or 4xx/5xx (in strictStatus mode)
}
```

### Pillar 3 — Global `s_*()` Function Shims (azjezz/psl)

Backed by [azjezz/psl](https://github.com/azjezz/psl), these global shim functions replace PHP's
silent-failure built-ins. No `use` statement required — they are always available:

```php
// JSON — throws Psl\Json\Exception\DecodeException / EncodeException
$data = s_json('{"key":"value"}');        // array
$json = s_enc(['key' => 'value']);        // string
$json = s_enc(['key' => 'value'], true);  // pretty-printed string

// Type coercion — throws Psl\Type\Exception\CoercionException
$id  = s_int('42');         // 42     (not silent 0)
$id  = s_int('hello');      // throws CoercionException
$n   = s_float('3.14');     // 3.14
$str = s_str(42);           // "42"

// File I/O — throws Psl\File\Exception\RuntimeException
$contents = s_file('/etc/hostname');              // string
$bytes    = s_write('/tmp/out.txt', 'hello');     // int (bytes written)
$bytes    = s_append('/tmp/out.txt', ' world');   // int

// Regex — throws Psl\Regex\Exception\RuntimeException
s_match('/^\d+$/', '123');            // true / false
s_regex('/(\d+)-(\d+)/', '10-99');   // ['10', '99'] or null

// Environment — throws RuntimeException if missing
s_env('APP_KEY');                     // string or throws
s_env_or('APP_ENV', 'production');   // string with fallback

// HTTP (returns core\Net\Http\HttpResponse — throws HttpException on failure)
$r = s_get('https://api.example.com/users');
$r = s_post('https://api.example.com', ['name' => 'Alice']);
```

---

## 🔒 Security Hardening

### Disabled Functions (`php.ini`)

The following functions are **permanently disabled** at the PHP engine level and cannot be called by any code:

```
unserialize, serialize, exec, shell_exec, system, passthru,
proc_open, popen, pcntl_exec, pcntl_fork, pcntl_signal,
posix_kill, posix_setuid, posix_setgid, dl, phpinfo,
symlink, link, putenv, ini_set, ini_restore, show_source, highlight_file
```

### Safe Function Replacements (pure PHP)

CorePHP does **not** transparently override native functions (the runkit7 approach segfaulted on
PHP 8.4 and was removed). Instead, use these pure-PHP `s_*()` shims — always available, always
throwing on failure instead of returning `null`/`false`/`0`:

| Native (silent) | Safe replacement | Throws on failure |
|---|---|---|
| `json_decode()` → `null` | `s_json()` | `Psl\Json\Exception\DecodeException` |
| `json_encode()` → `false` | `s_enc()` | `Psl\Json\Exception\EncodeException` |
| `file_get_contents()` → `false` | `s_file()` | `Psl\File\Exception\RuntimeException` |
| `file_put_contents()` → `false` | `s_write()` / `s_append()` | `Psl\File\Exception\RuntimeException` |
| `intval()` → `0` | `s_int()` | `Psl\Type\Exception\CoercionException` |
| `floatval()` → `0.0` | `s_float()` | `Psl\Type\Exception\CoercionException` |
| `preg_match()` → `false` | `s_match()` / `s_regex()` | `Psl\Regex\Exception\ExceptionInterface` |
| `preg_replace()` → `null` | `s_replace()` | `Psl\Regex\Exception\ExceptionInterface` |
| `curl_exec()` → `false` | `s_get()` / `s_post()` | `core\Net\Http\HttpException` |
| `base64_decode()` → `false` | `s_b64()` | `core\Security\Exceptions\EncodingException` |

> File/stream operations that emit a PHP **warning** on failure also throw automatically via the
> `bootstrap.php` error handler (warnings/notices → `ErrorException`) — no shim required.

---

## 🧪 Development

```bash
# Build image
make build

# Start services
make up

# Open shell in container
make shell

# Run tests
make test

# Run lint (dry-run)
make lint

# Auto-fix lint violations
make lint-fix

# View all available commands
make help
```

---

## 📁 Project Structure

```
CorePHP/
├── Dockerfile                          # PHP-JVM base image
├── docker-compose.yaml                 # Production compose
├── docker-compose.override.yaml        # Development overrides
├── Makefile                            # Developer task runner
├── .rr.yaml                            # RoadRunner configuration
├── worker.php                          # PSR-7 request loop
├── composer.json                       # Root project dependencies
├── .php-cs-fixer.dist.php              # PHP-CS-Fixer (declare strict_types)
├── phpstan.neon                        # PHPStan Level 9
├── .user.ini                           # Shared Hosting mode config
├── .gitignore
├── config/
│   └── php.ini                         # Hardened PHP configuration
├── opt/corephp-vm/
│   ├── bootstrap.php                   # Auto-prepend sandbox
│   └── std/
│       ├── composer.json               # std library package
│       ├── phpunit.xml
│       └── src/
│           ├── Internal/Array/
│           │   └── TypedCollection.php    # Pillar 1
│           ├── Net/Http/
│           │   ├── HttpClient.php         # Pillar 2
│           │   ├── HttpResponse.php
│           │   └── HttpException.php
│           └── Security/Exceptions/
│               ├── SecurityException.php    # unserialize guard
│               └── EncodingException.php    # base64_decode guard
├── ci/
│   ├── lint.sh                         # CI lint runner
│   └── test.sh                         # CI test runner
├── docs/
│   └── PROJECT.md                      # Original specification
└── memory-bank/                        # GOS documentation
```

---

## 📖 Memory Bank (GOS)

This project follows the Greicodex OS (GOS) standards. See `memory-bank/` for full architecture documentation:

- `01_PROJECT_CHARTER.md` — Vision and success criteria
- `02_ARCHITECTURE_PRINCIPLES.md` — Hexagonal architecture + SOLID
- `03_AGENTIC_WORKFLOW.md` — SPARC development framework
- `systemPatterns.md` — Runtime architecture diagrams
- `techContext.md` — Technology stack and constraints

---

## License

GPL3
