<?php

declare(strict_types=1);

namespace core;

use core\Internal\Array\TypedCollection;
use Psl\Vec as PslVec;

/**
 * core\Vec — A typed sequential list. (Java ArrayList / Kotlin List equivalent)
 *
 * A thin semantic alias over TypedCollection for sequential, ordered data.
 * All items must be of the declared type. Mixed types throw immediately.
 *
 * Extends TypedCollection which provides PSL-backed functional methods:
 *   filter(), map(), reduce(), each(), sort(), reverse(), slice(), chunk(), unique()
 *
 * Usage (with global alias — no `use` required):
 *   $users  = new ArrayList(User::class);
 *   $users->add(new User('Alice'));
 *   $users[] = new User('Bob');          // ArrayAccess syntax
 *
 *   $ids    = new ArrayList('int');
 *   $ids->add(42);
 *   $ids->add('oops');                   // throws InvalidArgumentException
 *
 *   foreach ($users as $user) { echo $user->name; }
 *   echo count($users);                  // Countable
 *
 *   // PSL-powered operations:
 *   $sorted   = $ids->sort(fn($a, $b) => $a <=> $b);
 *   $reversed = $ids->reverse();
 *   $page     = $ids->slice(0, 10);
 *   $pages    = $ids->chunk(10);
 *   $evens    = $ids->filter(fn(int $n) => $n % 2 === 0);
 *   $doubles  = $ids->map(fn(int $n) => $n * 2, 'int');
 *
 *   // PSL plain-array operations (when you don't need typing):
 *   $filtered = Vec::values([3, 1, 2]);  // [3, 1, 2] — re-indexed plain list
 *   $sorted   = Vec::sortedValues([3, 1, 2]); // [1, 2, 3] — plain sorted list
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
    //   sort(), reverse(), slice(), chunk(), unique()
    //   first(), last(), get(), contains(), isEmpty(), isNotEmpty()
    //   ArrayAccess, Iterator, Countable
    //
    // Because TypedCollection::fromArray() uses `new static(...)`,
    // calling Vec::fromArray(...) always returns a Vec — not a TypedCollection.

    // -------------------------------------------------------------------------
    // PSL-backed static utilities (plain array, no type enforcement)
    // Use these when you need quick functional operations on raw arrays.
    // -------------------------------------------------------------------------

    /**
     * Return the values of an iterable as a plain re-indexed list.
     * Backed by Psl\Vec\values().
     *
     * @template U
     * @param iterable<U> $iterable
     *
     * @return list<U>
     */
    public static function values(iterable $iterable): array
    {
        return PslVec\values($iterable);
    }

    /**
     * Filter a plain array and return a re-indexed list of matching elements.
     * Backed by Psl\Vec\filter().
     *
     * @template U
     * @param iterable<U>       $iterable
     * @param callable(U): bool $predicate
     *
     * @return list<U>
     */
    public static function filterValues(iterable $iterable, callable $predicate): array
    {
        return PslVec\filter($iterable, $predicate);
    }

    /**
     * Map over a plain array and return a re-indexed list.
     * Backed by Psl\Vec\map().
     *
     * @template U
     * @template V
     * @param iterable<U>   $iterable
     * @param callable(U): V $transform
     *
     * @return list<V>
     */
    public static function mapValues(iterable $iterable, callable $transform): array
    {
        return PslVec\map($iterable, $transform);
    }

    /**
     * Return the iterable sorted as a plain list using the given comparator.
     * Backed by Psl\Vec\sort_by().
     *
     * @template U
     * @param iterable<U>         $iterable
     * @param callable(U, U): int $comparator
     *
     * @return list<U>
     */
    public static function sortedValues(iterable $iterable, callable $comparator): array
    {
        return PslVec\sort_by($iterable, static fn($a) => $a, $comparator);
    }

    /**
     * Return the iterable as a reversed plain list.
     * Backed by Psl\Vec\reverse().
     *
     * @template U
     * @param iterable<U> $iterable
     *
     * @return list<U>
     */
    public static function reversedValues(iterable $iterable): array
    {
        return PslVec\reverse($iterable);
    }

    /**
     * Concatenate two iterables into a single plain list.
     * Backed by Psl\Vec\concat().
     *
     * @template U
     * @param iterable<U> $first
     * @param iterable<U> $second
     *
     * @return list<U>
     */
    public static function concat(iterable $first, iterable $second): array
    {
        return PslVec\concat($first, $second);
    }

    /**
     * Return a range of integers as a plain list.
     * Backed by Psl\Vec\range().
     *
     * @param int $start Start value (inclusive)
     * @param int $end   End value (inclusive)
     * @param int $step  Step value (default: 1)
     *
     * @return list<int>
     */
    public static function range(int $start, int $end, int $step = 1): array
    {
        return PslVec\range($start, $end, $step);
    }
}
