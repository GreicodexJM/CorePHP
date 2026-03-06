# std — CorePHP Standard Library

The `core` namespace is the foundation of CorePHP. It provides a minimal, JVM-inspired type system that makes PHP behave like a statically-typed language at runtime.

## Philosophy

PHP's standard library returns `false`, `null`, or `0` on failures and allows mixed arrays and dynamic properties. The `core` namespace eliminates all of that:

| Old PHP | CorePHP std |
|---|---|
| `json_decode($str)` → `null` on error | `Safe::jsonDecode($str)` → throws `JsonDecodeException` |
| `file_get_contents($path)` → `false` | `Safe::fileRead($path)` → throws `FileReadException` |
| `$arr[] = "wrong type"` → silent | `$list->add("wrong type")` → throws `InvalidArgumentException` |
| `$obj->typo` → `null` | `BaseObject->typo` → throws `RuntimeException` |
| `intval("bad")` → `0` | `Safe::toInt("bad")` → throws `TypeCoercionException` |

## Namespace Overview

```
core\
├── Any                             — Universal strict base class (alias: BaseObject)
├── StrictObject                    — Undefined property guard (abstract)
├── Vec                             — Typed sequential list (alias: ArrayList)
├── Dict                            — Typed key-value dictionary
├── IO                              — Safe file + HTTP facade
│
├── Security\Safe\
│   ├── Safe                        — Static safe wrappers for PHP builtins
│   ├── JsonDecodeException
│   ├── JsonEncodeException
│   ├── FileReadException
│   ├── FileWriteException
│   ├── TypeCoercionException
│   └── RegexException
│
├── Net\Http\
│   ├── HttpClient                  — curl wrapper (throws on failure)
│   ├── HttpResponse                — Immutable response value object
│   └── HttpException
│
└── Internal\Array\
    └── TypedCollection             — Base class for Vec
```

## Global Aliases (no `use` required)

These are registered in `bootstrap.php` and are available in every file:

| Alias | Full class |
|---|---|
| `ArrayList` | `core\Vec` |
| `Dict` | `core\Dict` (also in global namespace) |
| `BaseObject` | `core\Any` |
| `StrictObject` | `core\StrictObject` |
| `Safe` | `core\Security\Safe\Safe` |
| `IO` | `core\IO` |

## Global Functions (no `use` required)

```php
s_json($str)          // Safe json_decode (throws JsonDecodeException)
s_enc($val)           // Safe json_encode (throws JsonEncodeException)
s_int($val)           // Safe intval (throws TypeCoercionException)
s_float($val)         // Safe floatval (throws TypeCoercionException)
s_file($path)         // Safe file_get_contents (throws FileReadException)
s_write($path, $data) // Safe file_put_contents (throws FileWriteException)
s_get($url)           // Safe HTTP GET (throws HttpException)
s_post($url, $body)   // Safe HTTP POST (throws HttpException)

// Compatibility bridge (old ↔ new)
arr_to_list($type, $arr)  // array → ArrayList
list_to_arr($list)        // ArrayList → array
arr_to_dict($type, $arr)  // assoc array → Dict
dict_to_arr($dict)        // Dict → assoc array
```

## Quick Start

```php
// Typed list — replaces mixed arrays
$users = new ArrayList(User::class);
$users->add(new User('Alice'));
$users->add('wrong');  // InvalidArgumentException immediately

// Typed dictionary — replaces associative arrays
$config = new Dict('string');
$config->set('host', 'localhost');
echo $config->get('missing'); // OutOfBoundsException (not null)

// Safe I/O — no more false returns
$data = IO::json('config.json');     // throws on missing/bad JSON
$body = s_get('https://api.com')->body();

// Strict base class — no dynamic properties
class User extends BaseObject {
    public function __construct(public readonly string $name) {}
}
$user = new User('Alice');
$user->typo; // RuntimeException immediately
```

## Further Reading

- [Vec.md](Vec.md) — ArrayList / TypedCollection API reference
- [Dict.md](Dict.md) — Dict API reference
- [IO.md](IO.md) — IO + Safe file/HTTP reference
- [Safe.md](Safe.md) — Safe static class reference
- [StrictObject.md](StrictObject.md) — StrictObject + Any reference
- [functions.md](functions.md) — Global shim functions reference
- [migration.md](migration.md) — Migrating from plain PHP arrays guide
