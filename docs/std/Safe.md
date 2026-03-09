---
layout: default
title: Safe (Deleted → PSL)
parent: Standard Library
nav_order: 3
---

# Safe — ⚠️ Deleted (Replaced by `azjezz/psl`)

> **`core\Security\Safe\Safe` no longer exists.** It was deleted and replaced by the
> [azjezz/psl](https://github.com/azjezz/psl) standard library, which provides
> the same guarantees with better type inference, PHPStan integration, and performance.

---

## Migration Map

| Old (`Safe::*`) | New (PSL or `s_*` shim) | Throws |
|---|---|---|
| `Safe::jsonDecode($str)` | `s_json($str)` or `Psl\Json\decode($str)` | `Psl\Json\Exception` |
| `Safe::jsonEncode($val)` | `s_enc($val)` or `Psl\Json\encode($val)` | `Psl\Json\Exception` |
| `Safe::toInt($val)` | `s_int($val)` or `Psl\Type\int()->coerce($val)` | `Psl\Type\Exception\CoercionException` |
| `Safe::toFloat($val)` | `s_float($val)` or `Psl\Type\float()->coerce($val)` | `Psl\Type\Exception\CoercionException` |
| `Safe::fileRead($path)` | `s_file($path)` or `Psl\File\read($path)` | `Psl\File\Exception\RuntimeException` |
| `Safe::fileWrite($path, $data)` | `s_write($path, $data)` or `Psl\File\write($path, $data)` | `Psl\File\Exception\RuntimeException` |

---

## Why Was It Deleted?

`core\Security\Safe\Safe` was a hand-rolled wrapper around PHP's silent-failure builtins. It was replaced by `azjezz/psl` which provides:

- **Better generics** — PSL type inference integrates deeply with PHPStan Level 9
- **More operations** — PSL covers JSON, File, Type, Regex, Env, Vec, Dict, Str, and more
- **Maintained by the community** — not a single-project internal class
- **Direct shims** — the global `s_*` functions in `functions.php` wrap PSL directly

---

## Old Exception Types → New Exception Types

| Old | New |
|---|---|
| `core\Security\Safe\JsonDecodeException` | `Psl\Json\Exception` |
| `core\Security\Safe\JsonEncodeException` | `Psl\Json\Exception` |
| `core\Security\Safe\FileReadException` | `Psl\File\Exception\RuntimeException` |
| `core\Security\Safe\FileWriteException` | `Psl\File\Exception\RuntimeException` |
| `core\Security\Safe\TypeCoercionException` | `Psl\Type\Exception\CoercionException` |
| `core\Security\Safe\RegexException` | `Psl\Regex\Exception\RuntimeException` |

All PSL exceptions extend `\RuntimeException`, so existing `catch (\RuntimeException $e)` blocks continue to work.

---

## Preferred Pattern: `s_*` Shims

The `s_*` functions are registered globally in `bootstrap.php` (via `functions.php`) and are available everywhere without any `use` statements:

```php
// JSON
$data = s_json($jsonString);
$json = s_enc($data, pretty: true);

// Type coercion — throws on bad input (no silent 0 or empty string)
$id    = s_int($request->get('id'));
$price = s_float($request->get('price'));

// File I/O
$config = s_json(s_file('/etc/app/config.json'));
s_write('/tmp/output.json', s_enc($result));
```

---

## Alternative: Use PSL Directly

For code that explicitly wants to `use` the PSL namespace:

```php
use Psl\Json;
use Psl\File;
use Psl\Type;
use Psl\Regex;

$data     = Json\decode($str);
$contents = File\read('/etc/hostname');
$id       = Type\int()->coerce($rawInput);
$matches  = Regex\first_match($subject, '/(\d+)/');
```

---

## See Also

- [functions.md](functions.md) — Complete `s_*` shim reference
- [PSL documentation](https://github.com/azjezz/psl) — Full `azjezz/psl` API
