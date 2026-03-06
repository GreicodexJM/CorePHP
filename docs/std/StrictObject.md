---
layout: default
title: StrictObject & Any
parent: Standard Library
nav_order: 5
---

# StrictObject & Any — Undefined Property Guards

## StrictObject (abstract)

Extend `StrictObject` to immediately catch typos and dynamic property access. It overrides `__get`, `__set`, `__isset`, and `__unset` to throw `RuntimeException` instead of silently creating or returning `null`.

```php
abstract class StrictObject { ... }
```

**Errors caught at runtime:**

```php
class Config extends StrictObject {
    public string $host = 'localhost';
}

$c = new Config();

echo $c->host;   // OK — declared property
echo $c->hoost;  // RuntimeException: Attempt to read undefined property 'hoost'

$c->host  = 'db';    // OK
$c->extra = 'val';   // RuntimeException: Attempt to write undefined property 'extra'

isset($c->undef);    // RuntimeException: Cannot use isset() on undefined property 'undef'
unset($c->undef);    // RuntimeException: Cannot use unset() on undefined property 'undef'
```

## Any (abstract) — extends StrictObject

`Any` (`BaseObject`) adds a `__toString()` implementation that returns `ClassName@{objectId}`, useful for logging and debugging.

```php
abstract class Any extends StrictObject {
    public function __toString(): string { ... }
}
```

```php
class User extends Any {
    public function __construct(public readonly string $name) {}
}

$u = new User('Alice');
echo $u;    // User@42
```

## Global aliases

```php
// bootstrap.php registers these automatically:
class_alias(\core\StrictObject::class, 'StrictObject');
class_alias(\core\Any::class, 'BaseObject');
```

Use `BaseObject` as the base for all domain entities:

```php
class OrderLine extends BaseObject {
    public function __construct(
        public readonly Product $product,
        public readonly int     $quantity,
        public readonly float   $unitPrice,
    ) {}
}
```
