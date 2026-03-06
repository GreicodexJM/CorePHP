<p align="center">
  <img src="docs/corephp-logo.png" alt="CorePHP Logo" width="220" />
</p>

# CorePHP — Base Docker Image

[![Build & Push](https://github.com/GreicodexJM/CorePHP/actions/workflows/docker-publish.yml/badge.svg)](https://github.com/GreicodexJM/CorePHP/actions/workflows/docker-publish.yml)
[![Docs](https://github.com/GreicodexJM/CorePHP/actions/workflows/pages.yml/badge.svg)](https://greicodexjm.github.io/CorePHP/)
[![Docker Hub](https://img.shields.io/docker/v/greicodex/corephp-vm?logo=docker&label=greicodex%2Fcorephp-vm)](https://hub.docker.com/r/greicodex/corephp-vm)
[![PHP 8.3](https://img.shields.io/badge/PHP-8.3-blue?logo=php)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

> **A production-grade, persistent PHP 8.3 runtime that brings JVM-like stability to PHP.**

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
│                ├── FunctionOverrider (runkit7)  │
│                └── StrictObject                 │
│                                                 │
│  php.ini hardening                              │
│    ├── disable_functions (unserialize, exec...) │
│    ├── allow_url_fopen = Off                    │
│    └── runkit.internal_override = 1             │
└─────────────────────────────────────────────────┘
```

### Three Enforcement Layers

| Layer | Mechanism | When |
|---|---|---|
| **Static** | PHPStan Level 9 + PHP-CS-Fixer | CI / pre-commit |
| **Boot** | runkit7 `FunctionOverrider` | Once at process startup |
| **Runtime** | `bootstrap.php` error handler | Every request |

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
| runkit7 overrides | ✅ | ❌ (extension not available) |
| bootstrap.php sandbox | ✅ | ✅ |
| StrictObject | ✅ | ✅ |
| Safe static class | ✅ | ✅ |
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

### Pillar 3 — `core\Security\Safe`

Safe replacements for PHP's silent-failure functions:

```php
use core\Security\Safe\Safe;
use core\Security\Safe\JsonDecodeException;
use core\Security\Safe\TypeCoercionException;
use core\Security\Safe\FileReadException;

// json_decode — throws JsonDecodeException instead of returning null
$data = Safe::jsonDecode('{"key":"value"}');
$data = Safe::jsonDecode('{invalid}'); // throws JsonDecodeException

// json_encode — throws JsonEncodeException instead of returning false
$json = Safe::jsonEncode(['key' => 'value']);

// Type coercion — strict, no silent 0
$id = Safe::toInt('42');        // returns 42
$id = Safe::toInt('hello');     // throws TypeCoercionException
$n  = Safe::toFloat('3.14');    // returns 3.14

// File reading — throws FileReadException instead of returning false
$contents = Safe::fileRead('/etc/hostname');
$contents = Safe::fileRead('/nonexistent'); // throws FileReadException

// File writing — throws FileWriteException instead of returning false
$bytes = Safe::fileWrite('/tmp/output.txt', 'hello');
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

### runkit7 Native Function Overrides (boot-time)

The following native PHP functions are **replaced at process startup** with versions that throw typed exceptions:

| Function | Old Failure | New Behaviour |
|---|---|---|
| `json_decode()` | returns `null` | throws `JsonDecodeException` |
| `json_encode()` | returns `false` | throws `JsonEncodeException` |
| `file_get_contents()` | returns `false` | throws `FileReadException` |
| `file_put_contents()` | returns `false` | throws `FileWriteException` |
| `intval()` | returns `0` silently | throws `TypeCoercionException` |
| `floatval()` | returns `0.0` silently | throws `TypeCoercionException` |
| `preg_match()` | returns `false` | throws `RegexException` |
| `preg_replace()` | returns `null` | throws `RegexException` |
| `curl_exec()` | returns `false` | throws `HttpException` |
| `base64_decode()` | returns `false` | throws `EncodingException` |

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
│           ├── Engine/
│           │   └── FunctionOverrider.php  # runkit7 overrides
│           ├── Internal/Array/
│           │   └── TypedCollection.php    # Pillar 1
│           ├── Net/Http/
│           │   ├── HttpClient.php         # Pillar 2
│           │   ├── HttpResponse.php
│           │   └── HttpException.php
│           └── Security/
│               ├── Safe/
│               │   ├── Safe.php           # Pillar 3
│               │   ├── JsonDecodeException.php
│               │   ├── JsonEncodeException.php
│               │   ├── TypeCoercionException.php
│               │   ├── FileReadException.php
│               │   ├── FileWriteException.php
│               │   └── RegexException.php
│               └── Exceptions/
│                   ├── SecurityException.php
│                   └── EncodingException.php
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
