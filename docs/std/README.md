---
layout: default
title: Standard Library
nav_order: 2
has_children: true
permalink: /std/
---

# std — CorePHP Standard Library

The `core` namespace is the foundation of CorePHP. It provides a minimal, JVM-inspired type system that makes PHP behave like a statically-typed language at runtime.

## Philosophy

PHP's standard library returns `false`, `null`, or `0` on failures and allows mixed arrays and dynamic properties. The `core` namespace eliminates all of that:

| Old PHP | CorePHP std |
|---|---|
| `json_decode($str)` → `null` on error | `s_json($str)` → throws `Psl\Json\Exception` |
| `file_get_contents($path)` → `false` | `s_file($path)` → throws `Psl\File\Exception\RuntimeException` |
| `$arr[] = "wrong type"` → silent | `$list->add("wrong type")` → throws `InvalidArgumentException` |
| `$obj->typo` → `null` | `BaseObject->typo` → throws `RuntimeException` |
| `intval("bad")` → `0` | `s_int("bad")` → throws `Psl\Type\Exception\CoercionException` |

## Namespace Overview

```
core\
├── Any                             — Universal strict base class (alias: BaseObject)
├── StrictObject                    — Undefined property guard (abstract)
├── Vec                             — Typed sequential list (alias: ArrayList)
├── Dict                            — Typed key-value dictionary
├── IO                              — Safe file + HTTP facade
│
├── Security\Exceptions\
│   ├── SecurityException           — unserialize/eval guard exceptions
│   └── EncodingException           — base64_decode failures
│
├── Net\Http\
│   ├── HttpClient                  — curl wrapper (throws on failure)
│   ├── HttpResponse                — Immutable response value object
│   └── HttpException
│
├── Engine\
│   └── FunctionOverrider           — runkit7 override installer (boot-time)
│
└── Internal\Array\
    └── TypedCollection             — Base class for Vec

Note: core\Security\Safe\Safe was deleted — use azjezz/psl or s_* shims instead.
See Safe.md for the migration guide.
```

## Global Aliases (no `use` required)

These are registered in `bootstrap.php` and are available in every file:

| Alias | Full class |
|---|---|
| `ArrayList` | `core\Vec` |
| `Dict` | `core\Dict` (also in global namespace) |
| `BaseObject` | `core\Any` |
| `StrictObject` | `core\StrictObject` |
| `IO` | `core\IO` |

> **Note:** The `Safe` alias was removed — `core\Security\Safe\Safe` was deleted.
> Use the `s_*` global shim functions or `azjezz/psl` directly. See [Safe.md](Safe.md).

## Global Functions (no `use` required)

```php
s_json($str)          // Safe json_decode (throws Psl\Json\Exception)
s_enc($val)           // Safe json_encode (throws Psl\Json\Exception)
s_int($val)           // Safe intval (throws Psl\Type\Exception\CoercionException)
s_float($val)         // Safe floatval (throws Psl\Type\Exception\CoercionException)
s_file($path)         // Safe file_get_contents (throws Psl\File\Exception\RuntimeException)
s_write($path, $data) // Safe file_put_contents (throws Psl\File\Exception\RuntimeException)
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
- [Safe.md](Safe.md) — Safe class deleted → PSL migration guide
- [StrictObject.md](StrictObject.md) — StrictObject + Any reference
- [functions.md](functions.md) — Global shim functions reference
- [migration.md](migration.md) — Migrating from plain PHP arrays guide
