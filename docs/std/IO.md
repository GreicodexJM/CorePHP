---
layout: default
title: IO
parent: Standard Library
nav_order: 4
---

# IO — Safe File & HTTP Facade

`IO` is a zero-configuration facade that provides safe, exception-throwing wrappers for file system and HTTP operations. All failures throw typed exceptions.

## File Operations

```php
// Read
$text = IO::read('/path/to/file.txt');
// throws FileReadException if not found / unreadable

// Write (overwrite)
$bytes = IO::write('/path/to/file.txt', 'content');

// Write (append)
$bytes = IO::write('/path/to/file.txt', 'more', true);
// throws FileWriteException on failure

// JSON round-trip
$data  = IO::json('/path/to/config.json');     // read + decode → assoc array
$bytes = IO::writeJson('/path/to/out.json', $data);  // encode + write

// Exists check
IO::exists('/path')   // true for files or directories
```

## HTTP Client

```php
// One-liner factory
$client = IO::http();                         // HttpClient with defaults
$client = IO::http(timeout: 5);               // custom timeout

$response = IO::http()->get('https://api.example.com/users');
$response = IO::http()->post('https://api.example.com/users', ['name' => 'Alice']);

// Response methods
$response->statusCode();    // int (200, 404, etc.)
$response->body();          // string
$response->json(true);      // decoded assoc array
$response->json(false);     // decoded stdClass
$response->isOk();          // true only for 200
$response->isSuccess();     // true for 2xx
$response->isClientError(); // true for 4xx
$response->isServerError(); // true for 5xx
$response->header('content-type');         // string|null
$response->requireHeader('authorization'); // string or throws HttpException
```

## Global shortcuts

```php
$response = s_get('https://api.example.com/users');
$response = s_post('https://api.example.com/users', ['name' => 'Alice']);
```

## Strict mode

```php
// Throw HttpException on 4xx/5xx — useful in internal service calls
$client = IO::http(strictStatus: true);
$data   = $client->get('https://api.example.com/data')->json(true);
```

## Exceptions

| Exception | Trigger |
|---|---|
| `FileReadException` | File missing, unreadable |
| `FileWriteException` | Directory not writable, device not accessible |
| `JsonDecodeException` | Invalid JSON on `IO::json()` |
| `HttpException` | curl error, empty URL, or (with `strictStatus`) 4xx/5xx |
