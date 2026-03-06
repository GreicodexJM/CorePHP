# Product Context: CorePHP (PHP-JVM)

## Why This Project Exists

PHP has a fundamental architectural problem: **it dies on every request**. Every HTTP request spawns a new PHP process, loads all files, initializes all objects, connects to the database, and then destroys everything. This is the opposite of how the JVM works — where the process is long-lived and only the request-handling context changes.

Additionally, PHP's standard library has dozens of functions that **fail silently** — returning `false`, `null`, or `0` instead of throwing exceptions. This leads to impossible-to-debug bugs that only surface as strange behavior deep in application logic.

## Problems It Solves

| Problem | Solution |
|---|---|
| PHP re-initializes on every request | RoadRunner persistent worker process |
| `json_decode` returns null silently | `Safe::jsonDecode` throws `JsonDecodeException` |
| `file_get_contents` returns false silently | Overridden via runkit7 → throws `FileReadException` |
| `unserialize` is a remote code execution vector | Disabled in `disable_functions` |
| Mixed-type arrays cause runtime errors | `TypedCollection` enforces single type |
| Undefined property access is silently allowed | `StrictObject` throws on `__get`/`__set` |
| curl failures return false silently | `HttpClient` wraps curl → throws `HttpException` |
| Cold starts for every request | RoadRunner keeps workers warm |

## How It Should Work

### For Application Developers
```php
// Before (PHP default - silent failure):
$data = json_decode($body); // returns null if invalid, no exception
$user = $data->user;        // NULL dereference, no error

// After (CorePHP - loud failure):
$data = \core\Security\Safe\Safe::jsonDecode($body); // throws JsonDecodeException immediately
```

```php
// Before (mixed arrays):
$users = [];
$users[] = new User();
$users[] = "oops"; // No error, type corruption

// After (TypedCollection):
$users = new \core\Internal\Array\TypedCollection(User::class);
$users->add(new User()); // OK
$users->add("oops");     // throws InvalidArgumentException immediately
```

## User Experience Goals
- **Zero configuration** — base image works out of the box
- **Fail loudly** — every problem is immediately visible as a named exception
- **Composable** — use as `FROM corephp-vm:latest` in any project
- **Transparent** — all overrides are documented and auditable
