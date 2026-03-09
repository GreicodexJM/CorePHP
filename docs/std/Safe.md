---
layout: default
title: Safe (deleted)
parent: Standard Library
nav_order: 6
---

# `core\Security\Safe\Safe` — Deleted

> ⚠️ **This class has been removed.** The page is kept as a migration reference only.

`core\Security\Safe\Safe` was a static utility class that wrapped PHP's silent-failure functions
(json_decode, file_get_contents, intval, etc.) to throw exceptions instead.

It was deleted when **azjezz/psl** was integrated as Layer 2, providing the same guarantees
through a well-maintained, typed library.

---

## Migration Reference

Replace every `Safe::*` call with the corresponding shim or PSL function:

| Old `Safe::*` call | New equivalent | Exception thrown |
|---|---|---|
| `Safe::jsonDecode($str)` | `s_json($str)` | `Psl\Json\Exception\DecodeException` |
| `Safe::jsonEncode($val)` | `s_enc($val)` | `Psl\Json\Exception\EncodeException` |
| `Safe::toInt($val)` | `s_int($val)` | `Psl\Type\Exception\CoercionException` |
| `Safe::toFloat($val)` | `s_float($val)` | `Psl\Type\Exception\CoercionException` |
| `Safe::toString($val)` | `s_str($val)` | `Psl\Type\Exception\CoercionException` |
| `Safe::fileRead($path)` | `s_file($path)` | `Psl\File\Exception\RuntimeException` |
| `Safe::fileWrite($path, $data)` | `s_write($path, $data)` | `Psl\File\Exception\RuntimeException` |
| `Safe::regex($pat, $str)` | `s_match($pat, $str)` | `Psl\Regex\Exception\RuntimeException` |

All `s_*` shims are global (no `use` statement required) and are documented in [functions.md](functions.md).

## Why PSL?

- azjezz/psl is actively maintained and battle-tested
- PSL provides consistent, typed exceptions across all failure modes
- PSL adds `Vec`, `Dict`, `Regex`, `Env`, and more — beyond what `Safe` provided
- Staying on a standard library reduces the amount of custom code to maintain

## See Also

- [functions.md](functions.md) — Full list of global `s_*` shim functions
- [migration.md](migration.md) — General migration guide from native PHP arrays and calls
