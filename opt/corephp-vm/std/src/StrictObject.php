<?php

declare(strict_types=1);

namespace core;

/**
 * StrictObject — Abstract base class that prevents undefined property access.
 *
 * PHP silently creates dynamic properties when you write to an undefined one,
 * and returns null when you read from one. Both behaviors mask bugs.
 *
 * Extend this class to enforce strict property contracts on your domain objects.
 * Any access to an undeclared property throws a \RuntimeException immediately.
 *
 * Usage:
 *   class User extends \core\StrictObject {
 *       public function __construct(
 *           public readonly string $name,
 *           public readonly string $email,
 *       ) {}
 *   }
 *
 *   $user = new User('Alice', 'alice@example.com');
 *   $user->typo;    // throws RuntimeException: Attempt to read undefined property User::$typo
 *   $user->foo = 1; // throws RuntimeException: Attempt to write undefined property User::$foo
 *
 * Global alias: BaseObject (registered in bootstrap.php)
 */
abstract class StrictObject
{
    /**
     * Prevent reading undefined properties.
     * Without this, PHP returns null silently — a common source of bugs.
     */
    final public function __get(string $name): never
    {
        throw new \RuntimeException(
            sprintf(
                'Attempt to read undefined property %s::$%s. '
                . 'Define the property explicitly or check for typos.',
                static::class,
                $name,
            ),
        );
    }

    /**
     * Prevent writing to undefined properties.
     * Without this, PHP creates dynamic properties silently.
     */
    final public function __set(string $name, mixed $value): never
    {
        throw new \RuntimeException(
            sprintf(
                'Attempt to write undefined property %s::$%s = %s. '
                . 'All properties must be declared explicitly.',
                static::class,
                $name,
                get_debug_type($value),
            ),
        );
    }

    /**
     * Prevent isset() on undefined properties from silently returning false.
     */
    final public function __isset(string $name): bool
    {
        throw new \RuntimeException(
            sprintf(
                'Attempt to check isset() on undefined property %s::$%s.',
                static::class,
                $name,
            ),
        );
    }

    /**
     * Prevent unset() on undefined properties.
     */
    final public function __unset(string $name): never
    {
        throw new \RuntimeException(
            sprintf(
                'Attempt to unset() undefined property %s::$%s.',
                static::class,
                $name,
            ),
        );
    }
}
