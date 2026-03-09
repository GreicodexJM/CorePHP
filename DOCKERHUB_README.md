<p align="center">
  <a href="https://github.com/GreicodexJM">
    <img src="https://raw.githubusercontent.com/GreicodexJM/CorePHP/main/docs/greicodex-logo.png" alt="Greicodex" width="160" />
  </a>
</p>
<p align="center">
  <img src="https://raw.githubusercontent.com/GreicodexJM/CorePHP/main/docs/corephp-logo.png" alt="CorePHP Logo" width="220" />
</p>

# CorePHP — PHP 8.4 Base Docker Image

[![Build & Push](https://github.com/GreicodexJM/CorePHP/actions/workflows/docker-publish.yml/badge.svg)](https://github.com/GreicodexJM/CorePHP/actions/workflows/docker-publish.yml)
[![Docs](https://github.com/GreicodexJM/CorePHP/actions/workflows/pages.yml/badge.svg)](https://greicodexjm.github.io/CorePHP/)
[![Docker Hub](https://img.shields.io/docker/v/greicodex/corephp-vm?logo=docker&label=greicodex%2Fcorephp-vm)](https://hub.docker.com/r/greicodex/corephp-vm)
[![PHP 8.4](https://img.shields.io/badge/PHP-8.4-blue?logo=php)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://github.com/GreicodexJM/CorePHP/blob/main/LICENSE)

> **A production-grade, persistent PHP 8.4 runtime that brings JVM-like stability to PHP.**

PHP traditionally re-initializes on every request. CorePHP eliminates this by running PHP inside **RoadRunner** as a long-lived process — just like the JVM. It also replaces PHP's silent-failure standard library with one that throws typed exceptions on every error.

---

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

## 🚀 Quick Start

### 1. Use as a base image in your project

```dockerfile
FROM greicodex/corephp-vm:latest

WORKDIR /app
COPY . .
RUN composer install --no-dev --optimize-autoloader

CMD ["rr", "serve", "-c", "/app/.rr.yaml"]
```

### 2. Start with Docker Compose

```yaml
services:
  app:
    image: greicodex/corephp-vm:latest
    ports:
      - "8080:8080"
    volumes:
      - .:/app
```

```bash
docker compose up -d
```

Your application is now running at `http://localhost:8080`.

---

## 📦 Standard Library (`std`)

All classes are under the `core\` namespace and are automatically available via Composer autoload.

### Pillar 1 — Type-Safe Collections

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

// Iteration + filtering
foreach ($users as $user) {
    echo $user->name . PHP_EOL;
}
$admins = $users->filter(fn(User $u) => $u->isAdmin());
```

### Pillar 2 — HTTP Client (no silent failures)

```php
use core\Net\Http\HttpClient;
use core\Net\Http\HttpException;

$client = new HttpClient(timeout: 10, strictStatus: true);

try {
    $response = $client->get('https://api.example.com/users');
    $users    = $response->json(associative: true);   // throws on non-JSON body
    $status   = $response->statusCode();               // 200
    $type     = $response->header('content-type');     // string or null

    // POST with JSON body (array → auto-encoded)
    $created = $client->post('https://api.example.com/users', ['name' => 'Alice']);

} catch (HttpException $e) {
    // curl error, connection refused, timeout, or 4xx/5xx (in strictStatus mode)
}
```

### Pillar 3 — Global `s_*()` Function Shims

Backed by [azjezz/psl](https://github.com/azjezz/psl), these replace PHP's silent-failure built-ins. No `use` statement required — always available:

```php
// JSON — throws on invalid input (never returns null/false)
$data = s_json('{"key":"value"}');       // array
$json = s_enc(['key' => 'value']);       // string
$json = s_enc(['key' => 'value'], true); // pretty-printed

// Type coercion — throws CoercionException (not silent 0)
$id  = s_int('42');    // 42
$id  = s_int('hello'); // throws CoercionException
$n   = s_float('3.14');
$str = s_str(42);      // "42"

// File I/O — throws on error (never returns false)
$contents = s_file('/etc/hostname');
$bytes    = s_write('/tmp/out.txt', 'hello');
$bytes    = s_append('/tmp/out.txt', ' world');

// Regex — throws on bad pattern (never returns false)
s_match('/^\d+$/', '123');           // true / false
s_regex('/(\d+)-(\d+)/', '10-99');  // ['10', '99'] or null

// Environment — throws if missing (never returns empty string silently)
s_env('APP_KEY');                    // string or throws
s_env_or('APP_ENV', 'production');  // string with fallback

// HTTP — throws HttpException on any failure
$r = s_get('https://api.example.com/users');
$r = s_post('https://api.example.com', ['name' => 'Alice']);
```

---

## 🔒 Security Hardening

### Disabled Functions (`php.ini`)

The following functions are **permanently disabled** at the PHP engine level:

```
unserialize, serialize, exec, shell_exec, system, passthru,
proc_open, popen, pcntl_exec, pcntl_fork, pcntl_signal,
posix_kill, posix_setuid, posix_setgid, dl, phpinfo,
symlink, link, putenv, ini_set, ini_restore, show_source, highlight_file
```

### runkit7 Native Function Overrides (boot-time)

| Function | Old Failure | New Behaviour |
|---|---|---|
| `json_decode()` | returns `null` | throws `JsonException` |
| `json_encode()` | returns `false` | throws `JsonException` |
| `file_get_contents()` | returns `false` | throws `FileReadException` |
| `file_put_contents()` | returns `false` | throws `FileWriteException` |
| `intval()` | returns `0` silently | throws `TypeCoercionException` |
| `floatval()` | returns `0.0` silently | throws `TypeCoercionException` |
| `preg_match()` | returns `false` | throws `RegexException` |
| `preg_replace()` | returns `null` | throws `RegexException` |
| `curl_exec()` | returns `false` | throws `HttpException` |
| `base64_decode()` | returns `false` | throws `EncodingException` |

---

## 🏠 Shared Hosting Mode (no Docker)

If you cannot use Docker (cPanel, Plesk), a subset of features is available via `.user.ini`:

| Feature | Docker + RoadRunner | Shared Hosting |
|---|---|---|
| Persistent process | ✅ | ❌ (restarts per request) |
| runkit7 overrides | ✅ | ❌ |
| bootstrap.php sandbox | ✅ | ✅ |
| StrictObject | ✅ | ✅ |
| Global `s_*()` shims | ✅ | ✅ |
| Error → Exception | ✅ | ✅ |

---

## 📖 Source & Documentation

- **GitHub:** [GreicodexJM/CorePHP](https://github.com/GreicodexJM/CorePHP)
- **Docs site:** [greicodexjm.github.io/CorePHP](https://greicodexjm.github.io/CorePHP/)
- **std library API:** [docs/std/README.md](https://github.com/GreicodexJM/CorePHP/blob/main/docs/std/README.md)

---

## License

GPL-3.0 — see [LICENSE](https://github.com/GreicodexJM/CorePHP/blob/main/LICENSE)
