# 02 — Architecture Principles: CorePHP (PHP-JVM)

## Core Pattern: Hexagonal Architecture (Ports & Adapters)

The `std` library is structured as a self-contained hexagon:
- **Core:** Domain logic (TypedCollection, Safe utilities)
- **Ports:** Interfaces defining contracts (`HttpClientInterface`, etc.)
- **Adapters:** Concrete implementations (curl-based HttpClient, runkit7 overrides)

## Namespace Hierarchy

```
std\
├── Engine\          # Boot-time engine patches (runkit7 overrides)
│   └── FunctionOverrider
├── Internal\
│   └── Array\       # Type-safe array replacement
│       └── TypedCollection
├── Net\
│   └── Http\        # HTTP client (curl → exceptions)
│       ├── HttpClient
│       ├── HttpResponse
│       └── HttpException
└── Security\
    ├── Safe\        # Safe wrappers for silent-failure PHP functions
    │   ├── Safe
    │   ├── JsonDecodeException
    │   ├── JsonEncodeException
    │   ├── TypeCoercionException
    │   ├── FileReadException
    │   ├── FileWriteException
    │   └── RegexException
    └── Exceptions\
        ├── SecurityException    # unserialize/eval blocks
        └── EncodingException    # base64_decode failures
```

## SOLID Principles Applied

### Single Responsibility (SRP)
- `TypedCollection` only enforces type-safety on collections
- `Safe` only handles safe function wrapping
- `HttpClient` only handles HTTP transport
- `FunctionOverrider` only registers runkit7 overrides at boot

### Open/Closed (OCP)
- `TypedCollection` accepts any FQCN — extensible without modification
- New safe wrappers can be added to `FunctionOverrider` without changing existing ones

### Liskov Substitution (LSP)
- All exception types extend standard PHP exceptions — fully substitutable
- `HttpClientInterface` can be swapped (curl, Guzzle, mock) without changing callers

### Interface Segregation (ISP)
- `HttpClientInterface` is minimal — only `get()` and `post()`
- No God interfaces

### Dependency Inversion (DIP)
- `bootstrap.php` depends on `FunctionOverrider` interface, not the runkit7 PECL directly
- Components wired via constructor injection

## Three Enforcement Layers

| Layer | Mechanism | When | Covers |
|---|---|---|---|
| 1. Static | PHPStan Level 9 + PHP-CS-Fixer | CI / pre-commit | Type errors, mixed arrays, eval(), style |
| 2. Boot | runkit7 `FunctionOverrider` | Process startup (once) | json_decode, file_get_contents, curl, preg_*, etc. |
| 3. Runtime | bootstrap.php error handler | Every request | Warnings/Notices → Exceptions, StrictObject |

## Security Philosophy
- **Least privilege by default:** `disable_functions` in `php.ini` removes the most dangerous functions at the engine level
- **Fail loudly:** Every failure path throws a named, typed exception — never `false`, `null`, or `0`
- **Immutable types:** `TypedCollection` is append-only after construction with a fixed type contract
