---
layout: default
title: Global Functions
parent: Standard Library
nav_order: 6
---

# Global Functions Reference

Registered via `bootstrap.php` → available in every file without `use` statements.

## Safe I/O Shims

| Function | Equivalent | Throws |
|---|---|---|
| `s_json(string $str, bool $assoc = true)` | `json_decode` | `JsonDecodeException` |
| `s_enc(mixed $val, int $flags = 0)` | `json_encode` | `JsonEncodeException` |
| `s_int(mixed $val)` | `intval` | `TypeCoercionException` |
| `s_float(mixed $val)` | `floatval` | `TypeCoercionException` |
| `s_file(string $path)` | `file_get_contents` | `FileReadException` |
| `s_write(string $path, string $data, bool $append = false)` | `file_put_contents` | `FileWriteException` |

## HTTP Shims

| Function | Description | Throws |
|---|---|---|
| `s_get(string $url, array $headers = [])` | HTTP GET → `HttpResponse` | `HttpException` |
| `s_post(string $url, array $body = [], array $headers = [])` | HTTP POST → `HttpResponse` | `HttpException` |

## Compatibility Bridge

These allow incremental migration — keep old array-based code working while gradually adopting `Vec` / `Dict`:

```php
// array → Vec
$list = arr_to_list('int', [1, 2, 3]);

// Vec → array
$plain = list_to_arr($list);

// assoc array → Dict
$dict = arr_to_dict('string', ['host' => 'localhost']);

// Dict → assoc array
$plain = dict_to_arr($dict);
```

## Usage Examples

```php
// Read + decode config in one line
$config = s_json(s_file('/etc/app/config.json'));

// Fetch remote API, decode response
$users = s_get('https://api.example.com/users')->json(true);

// Save result
s_write('/tmp/users.json', s_enc($users, JSON_PRETTY_PRINT));

// Type-safe collection from raw PHP array
$ids = arr_to_list('int', array_column($rawRows, 'id'));
$sum = $ids->reduce(fn(int $carry, int $id) => $carry + $id, 0);
```
