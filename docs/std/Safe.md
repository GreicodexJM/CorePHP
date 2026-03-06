# Safe — Safe Wrappers for PHP Builtins

All methods are static. Every failure path throws a typed exception — never returns `false` or `null`.

## JSON

```php
// Decode
$data = Safe::jsonDecode($string);           // default: object (stdClass)
$data = Safe::jsonDecode($string, true);     // associative array
// throws JsonDecodeException on invalid JSON or empty string

// Encode
$json = Safe::jsonEncode($array);
$json = Safe::jsonEncode($array, JSON_PRETTY_PRINT);
// throws JsonEncodeException on unencodable values (INF, resource, etc.)
```

## Type Coercion

```php
$int   = Safe::toInt('42');    // 42
$int   = Safe::toInt(3.9);     // 3
$int   = Safe::toInt('bad');   // throws TypeCoercionException
$int   = Safe::toInt(null);    // throws TypeCoercionException

$float = Safe::toFloat('3.14');
$float = Safe::toFloat('bad'); // throws TypeCoercionException
```

## File I/O

```php
$contents = Safe::fileRead('/path/to/file.txt');
// throws FileReadException if missing, unreadable, or empty result

$bytes = Safe::fileWrite('/path/to/file.txt', 'content');
$bytes = Safe::fileWrite('/path/to/file.txt', 'more', FILE_APPEND);
// throws FileWriteException on failure
```

## Exception Hierarchy

```
\RuntimeException
└── core\Security\Exceptions\SecurityException
    ├── core\Security\Safe\JsonDecodeException
    ├── core\Security\Safe\JsonEncodeException
    ├── core\Security\Safe\FileReadException
    ├── core\Security\Safe\FileWriteException
    ├── core\Security\Safe\TypeCoercionException
    └── core\Security\Safe\RegexException
```
