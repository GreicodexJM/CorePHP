---
layout: default
title: Migration Guide
parent: Standard Library
nav_order: 7
---

# Migration Guide — Plain PHP Arrays → std Collections

This guide shows how to incrementally replace plain PHP arrays with `Vec` and `Dict` without rewriting everything at once.

## Why migrate?

| Plain PHP | Problem | std fix |
|---|---|---|
| `$arr[] = $val` | No type safety — any type accepted | `$vec->add($val)` — throws on wrong type |
| `$arr[$key]` | Returns `null` silently on missing key | `$dict->get($key)` — throws `OutOfBoundsException` |
| `json_decode($s)` | Returns `null` on error | `Safe::jsonDecode($s)` — throws `JsonDecodeException` |
| `$obj->dynamic = 1` | Dynamic property created silently | `BaseObject` throws `RuntimeException` |

## Step 1 — Wrap at the boundary

Migrate the entry point (controller / service). Keep internal array code for now.

```php
// Before
function getActiveUserIds(array $rows): array {
    return array_column($rows, 'id');
}

// After — wrap at boundary, use bridge helper internally
function getActiveUserIds(array $rows): Vec {
    $ids = array_column($rows, 'id');
    return arr_to_list('int', $ids);    // bridge: array → Vec
}
```

## Step 2 — Push types inward

Once the boundary returns `Vec`, update callers to use the typed API.

```php
// Calling code after Step 1
$ids   = getActiveUserIds($rows);
$first = $ids->first();    // throws on empty (not returns null)
$sum   = $ids->reduce(fn(int $c, int $id) => $c + $id, 0);
```

## Step 3 — Replace associative arrays with Dict

```php
// Before
function getDbConfig(): array {
    return ['host' => getenv('DB_HOST'), 'port' => getenv('DB_PORT')];
}

// After
function getDbConfig(): Dict {
    return Dict::fromArray('string', [
        'host' => (string) getenv('DB_HOST'),
        'port' => (string) getenv('DB_PORT'),
    ]);
}

// Caller — crash immediately on missing key instead of silent null
$config = getDbConfig();
$dsn = "mysql:host={$config->get('host')};port={$config->get('port')}";
```

## Step 4 — Replace base classes

```php
// Before
class User {
    public string $name;
}

// After — catches all typos at runtime
class User extends BaseObject {
    public string $name;
}
```

## Backward compatibility during migration

Use bridge helpers to interop with old array-based code:

```php
// Old code returns array — wrap it
$legacyRows = $oldService->getRows();
$list       = arr_to_list(UserRow::class, $legacyRows);

// New code returns Vec — pass to old function
$oldFunction(list_to_arr($newList));

// Assoc arrays
$dict    = arr_to_dict('string', $oldConfig);
$oldCode(dict_to_arr($newDict));
```

## Checklist

- [ ] Wrap all `json_decode` → `Safe::jsonDecode` / `s_json()`
- [ ] Wrap all `file_get_contents` → `Safe::fileRead` / `s_file()`
- [ ] Replace sequential arrays with `Vec` / `ArrayList`
- [ ] Replace associative arrays with `Dict`
- [ ] Extend entity classes from `BaseObject`
- [ ] Remove `@property` hacks — use proper typed properties
- [ ] Enable `declare(strict_types=1)` via `.php-cs-fixer.dist.php`
- [ ] Run PHPStan level 9 (`make lint`)
