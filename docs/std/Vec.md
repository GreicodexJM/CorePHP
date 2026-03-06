---
layout: default
title: Vec (ArrayList)
parent: Standard Library
nav_order: 1
---

# Vec (ArrayList) — Typed Sequential List

`Vec` (`ArrayList`) is a type-safe replacement for plain PHP arrays. Every element must match the declared type; violations throw immediately.

## Creation

```php
// Empty list
$ids = new Vec('int');
$ids = new ArrayList('int');   // global alias

// From array
$users = Vec::fromArray(User::class, $pdo->fetchAll(PDO::FETCH_CLASS, User::class));

// From generator / iterator
$col = Vec::fromIterable('string', $csvRows);
```

## Adding Elements

```php
$list = new Vec('int');
$list->add(42);          // OK
$list->add('wrong');     // InvalidArgumentException

$list[] = 99;            // ArrayAccess syntax also works
```

## Reading

```php
$first = $list->first();   // throws OutOfBoundsException if empty
$last  = $list->last();
$item  = $list->get(2);    // throws OutOfBoundsException if not found

echo $list[0];             // ArrayAccess read
echo count($list);         // Countable
```

## Functional Operations

```php
// filter — returns new Vec of same type
$adults = $users->filter(fn(User $u): bool => $u->age >= 18);

// map — returns new Vec, can change type
$names = $users->map(fn(User $u): string => $u->name, 'string');

// reduce
$total = $orders->reduce(fn(int $carry, Order $o): int => $carry + $o->amount, 0);

// each — side-effects, no return
$list->each(function(int $n, int $idx): void { echo "$idx: $n\n"; });
```

## Queries

```php
$list->contains(42);    // true/false (strict equality)
$list->isEmpty();       // true/false
$list->isNotEmpty();    // true/false
$list->count();         // int
$list->getType();       // 'int' | 'User' | etc.
```

## Merging

```php
$merged = $a->merge($b);  // new Vec — originals unchanged
// Types must match or InvalidArgumentException is thrown
```

## Iteration

```php
foreach ($list as $index => $item) { ... }   // Iterator
```

## Conversion

```php
$plain = $list->toArray();     // array<int, T>

// Global bridge helpers
$list  = arr_to_list('int', [1, 2, 3]);
$array = list_to_arr($list);
```

## Supported Types

Any FQCN or primitive: `'int'`, `'float'`, `'string'`, `'bool'`, `User::class`, `SomeInterface::class`, etc.
