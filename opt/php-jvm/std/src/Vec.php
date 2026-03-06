<?php

declare(strict_types=1);

namespace std;

use std\Internal\Array\TypedCollection;

/**
 * std\Vec — A typed sequential list. (Java ArrayList / Kotlin List equivalent)
 *
 * A thin semantic alias over TypedCollection for sequential, ordered data.
 * All items must be of the declared type. Mixed types throw immediately.
 *
 * Usage (with global alias — no `use` required):
 *   $users  = new ArrayList(User::class);
 *   $users->add(new User('Alice'));
 *   $users[] = new User('Bob');     // ArrayAccess syntax
 *
 *   $ids    = new ArrayList('int');
 *   $ids->add(42);
 *   $ids->add('oops'); // throws InvalidArgumentException
 *
 *   foreach ($users as $user) { echo $user->name; }
 *   echo count($users);             // Countable
 *
 * Global alias: ArrayList (registered in bootstrap.php)
 *
 * @template T
 * @extends TypedCollection<T>
 */
final class Vec extends TypedCollection
{
    // Inherits ALL TypedCollection behaviour, including:
    //   fromArray(), fromIterable(), merge(), filter(), map(), reduce(), each()
    //   first(), last(), get(), contains(), isEmpty(), isNotEmpty()
    //   ArrayAccess, Iterator, Countable
    //
    // Because TypedCollection::fromArray() uses `new static(...)`,
    // calling Vec::fromArray(...) always returns a Vec — not a TypedCollection.
    //
    // This class exists to:
    //   1. Provide the intention-revealing short name `std\Vec`
    //   2. Expose the global ArrayList alias (registered in bootstrap.php)
    //
    // Usage — with global alias (no `use` required):
    //   $users = ArrayList::fromArray(User::class, $pdoRows);
    //   $ids   = new ArrayList('int');
    //   $ids[] = 42;
}
