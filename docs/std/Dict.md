---
layout: default
title: Dict
parent: Standard Library
nav_order: 2
---

# Dict — Typed Key-Value Dictionary

`Dict` is a type-safe replacement for PHP associative arrays. All values must be the declared type; keys are always strings.

## Creation

```php
$config = new Dict('string');
$config = Dict::fromArray('string', ['host' => 'localhost', 'port' => '3306']);
$config = Dict::fromObject('string', json_decode($json));
```

## Reading

```php
$val = $config->get('host');              // throws OutOfBoundsException if missing
$val = $config->getOrDefault('port', '3306');  // safe with fallback
```

## Writing / Removing

```php
$config->set('host', 'localhost');
$config->set('port', 3306);  // InvalidArgumentException — wrong type

$config->remove('key');
$config['key'] = 'value';    // ArrayAccess
unset($config['key']);
```

## Querying

```php
$config->has('key');       // bool
$config->isEmpty();        // bool
$config->isNotEmpty();     // bool
count($config);            // int
$config->keys();           // array<string>
$config->values();         // array<T>
$config->getType();        // declared type string
```

## Slicing

```php
$only = $config->only('host', 'port');   // new Dict with just those keys
$rest = $config->except('password');     // new Dict without those keys
```

## Merging

```php
$merged = $a->merge($b);  // new Dict — $b values overwrite $a on collision
// Types must match or InvalidArgumentException
```

## Iteration

```php
foreach ($config as $key => $value) { ... }  // IteratorAggregate
```

## Conversion

```php
$array = $config->toArray();   // assoc array

// Bridge helpers
$dict  = arr_to_dict('string', ['k' => 'v']);
$array = dict_to_arr($dict);
```

## Example: Config DTO

```php
$db = Dict::fromArray('string', [
    'host'     => $_ENV['DB_HOST'],
    'database' => $_ENV['DB_NAME'],
    'username' => $_ENV['DB_USER'],
    'password' => $_ENV['DB_PASS'],
]);

// Guaranteed to throw if key is missing or env is unset
$dsn = "mysql:host={$db->get('host')};dbname={$db->get('database')}";
```
