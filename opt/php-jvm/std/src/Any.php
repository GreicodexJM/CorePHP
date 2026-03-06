<?php

declare(strict_types=1);

namespace std;

/**
 * std\Any — The universal strict base class.
 *
 * Equivalent to Java's `Object` — a common root for all domain objects.
 * Inherits all undefined-property protections from StrictObject.
 *
 * Use this as your base class instead of extending nothing:
 *
 *   class User extends \std\Any { ... }
 *
 * Global alias: BaseObject (no `use` statement required)
 *
 *   class User extends BaseObject { ... }
 */
abstract class Any extends StrictObject
{
    /**
     * Return a debug string representation of this object.
     */
    public function __toString(): string
    {
        return sprintf('%s@%s', static::class, spl_object_id($this));
    }
}
