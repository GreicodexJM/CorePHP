<?php

declare(strict_types=1);

namespace core\Internal\Array;

use Psl\Vec;

/**
 * TypedCollection — A type-safe, homogeneous collection.
 *
 * Replaces native PHP arrays where mixed types can silently corrupt data.
 * Every item added must be an instance of the declared type (for classes/interfaces)
 * or match the declared primitive type (for string, int, float, bool).
 *
 * Functional methods (filter, map, reduce, each, sort, reverse, slice, chunk)
 * leverage azjezz/psl (Psl\Vec\*) for their implementations wherever the return
 * type is a plain list. Methods that must return a typed collection build on PSL
 * results then wrap them back into a new `static` of the same declared type.
 *
 * Implements ArrayAccess, Iterator, and Countable for drop-in compatibility
 * with foreach loops and count().
 *
 * Usage:
 *   $users = new TypedCollection(User::class);
 *   $users->add(new User());          // OK
 *   $users->add("not a user");        // throws InvalidArgumentException
 *
 *   $ids = new TypedCollection('int');
 *   $ids->add(42);                    // OK
 *   $ids->add(3.14);                  // throws InvalidArgumentException
 *
 *   foreach ($users as $user) {       // standard iteration
 *       echo $user->name;
 *   }
 *
 * @template T
 *
 * @implements \ArrayAccess<int, T>
 * @implements \Iterator<int, T>
 *
 * @phpstan-consistent-constructor
 */
class TypedCollection implements \ArrayAccess, \Iterator, \Countable
{
    /**
     * The declared type for all items in this collection.
     * Can be a FQCN (class/interface) or a primitive: 'string', 'int', 'float', 'bool'.
     */
    private readonly string $type;

    /**
     * Internal storage.
     *
     * @var array<int, T>
     */
    private array $items = [];

    /**
     * Iterator cursor.
     */
    private int $position = 0;

    /**
     * Valid primitive type names accepted for scalar type checking.
     *
     * @var array<string>
     */
    private const PRIMITIVE_TYPES = ['string', 'int', 'integer', 'float', 'double', 'bool', 'boolean'];

    /**
     * @param class-string<T>|string $type FQCN or primitive type name
     *
     * @throws \InvalidArgumentException if the type string is empty
     */
    public function __construct(string $type)
    {
        if (trim($type) === '') {
            throw new \InvalidArgumentException(
                'TypedCollection: type must be a non-empty class name or primitive type string.',
            );
        }
        $this->type = $type;
    }

    // -------------------------------------------------------------------------
    // Static factories (compatibility: old array → new TypedCollection)
    // -------------------------------------------------------------------------

    /**
     * Create a TypedCollection from a plain PHP array.
     *
     * Every element in the array is validated against the declared type.
     * Throws InvalidArgumentException on the first type mismatch.
     *
     * Example — migrating old code:
     *   Before: $ids = [1, 2, 3];
     *   After:  $ids = TypedCollection::fromArray('int', [1, 2, 3]);
     *
     * @param class-string<T>|string $type  Declared element type
     * @param array<mixed>           $items Plain PHP array
     *
     * @throws \InvalidArgumentException on the first element with the wrong type
     *
     * @return static<T>
     */
    public static function fromArray(string $type, array $items): static
    {
        /** @var static<T> $collection */
        $collection = new static($type);
        foreach ($items as $item) {
            $collection->add($item);
        }
        return $collection;
    }

    /**
     * Create a TypedCollection from any iterable (arrays, generators, other collections).
     *
     * Example — wrapping an existing iterator:
     *   $col = TypedCollection::fromIterable(User::class, $pdo->fetchAll());
     *
     * @param class-string<T>|string $type  Declared element type
     * @param iterable<mixed>        $items Any iterable source
     *
     * @throws \InvalidArgumentException on the first element with the wrong type
     *
     * @return static<T>
     */
    public static function fromIterable(string $type, iterable $items): static
    {
        /** @var static<T> $collection */
        $collection = new static($type);
        foreach ($items as $item) {
            $collection->add($item);
        }
        return $collection;
    }

    // -------------------------------------------------------------------------
    // Mutation / addition
    // -------------------------------------------------------------------------

    /**
     * Add an item to the collection.
     *
     * @param T $item The item to add
     *
     * @throws \InvalidArgumentException if the item does not match the declared type
     */
    public function add(mixed $item): void
    {
        $this->assertType($item);
        $this->items[] = $item;
    }

    /**
     * Merge another TypedCollection of the same type into a NEW collection.
     *
     * Neither the original nor the $other collection is mutated.
     * Both collections must have the same declared type.
     *
     * @param TypedCollection<T> $other
     *
     * @throws \InvalidArgumentException if the declared types differ
     *
     * @return static<T>
     */
    public function merge(self $other): static
    {
        if ($this->normalizePrimitive($this->type) !== $this->normalizePrimitive($other->getType())) {
            throw new \InvalidArgumentException(
                sprintf(
                    'TypedCollection::merge() — type mismatch: cannot merge <%s> into <%s>.',
                    $other->getType(),
                    $this->type,
                ),
            );
        }
        /** @var static<T> $merged */
        $merged = new static($this->type);
        foreach ($this->items as $item) {
            $merged->add($item);
        }
        foreach ($other->toArray() as $item) {
            $merged->add($item);
        }
        return $merged;
    }

    // -------------------------------------------------------------------------
    // Safe access
    // -------------------------------------------------------------------------

    /**
     * Return the first element, or throw if the collection is empty.
     *
     *
     * @throws \OutOfBoundsException if the collection is empty
     *
     * @return T
     */
    public function first(): mixed
    {
        if (empty($this->items)) {
            throw new \OutOfBoundsException(
                sprintf('TypedCollection<%s>::first() called on an empty collection.', $this->type),
            );
        }
        return $this->items[0];
    }

    /**
     * Return the last element, or throw if the collection is empty.
     *
     *
     * @throws \OutOfBoundsException if the collection is empty
     *
     * @return T
     */
    public function last(): mixed
    {
        if (empty($this->items)) {
            throw new \OutOfBoundsException(
                sprintf('TypedCollection<%s>::last() called on an empty collection.', $this->type),
            );
        }
        return $this->items[count($this->items) - 1];
    }

    /**
     * Return the element at the given index, or throw if not found.
     *
     *
     * @throws \OutOfBoundsException if the index is out of range
     *
     * @return T
     */
    public function get(int $index): mixed
    {
        if (!isset($this->items[$index])) {
            throw new \OutOfBoundsException(
                sprintf(
                    'TypedCollection<%s>::get(%d) — index out of range (size: %d).',
                    $this->type,
                    $index,
                    count($this->items),
                ),
            );
        }
        return $this->items[$index];
    }

    // -------------------------------------------------------------------------
    // Queries
    // -------------------------------------------------------------------------

    /**
     * Return true if the collection contains the given item (strict equality).
     *
     * @param T $item
     */
    public function contains(mixed $item): bool
    {
        return in_array($item, $this->items, strict: true);
    }

    /**
     * Return true if the collection is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Return true if the collection has at least one element.
     */
    public function isNotEmpty(): bool
    {
        return !empty($this->items);
    }

    // -------------------------------------------------------------------------
    // Declared type
    // -------------------------------------------------------------------------

    /**
     * Return the declared type of this collection.
     */
    public function getType(): string
    {
        return $this->type;
    }

    // -------------------------------------------------------------------------
    // Conversions (compatibility: new TypedCollection → old array)
    // -------------------------------------------------------------------------

    /**
     * Return all items as a plain PHP array.
     *
     * Use this when passing data to legacy code that expects arrays.
     *
     * @return array<int, T>
     */
    public function toArray(): array
    {
        return $this->items;
    }

    // -------------------------------------------------------------------------
    // Functional operations (PSL-backed where possible)
    // -------------------------------------------------------------------------

    /**
     * Return a new TypedCollection containing only items matching the predicate.
     * Backed by Psl\Vec\filter() internally; result is re-wrapped into a typed collection.
     *
     * @param callable(T): bool $predicate
     *
     * @return static<T>
     */
    public function filter(callable $predicate): static
    {
        /** @var static<T> $filtered */
        $filtered = new static($this->type);
        foreach (Vec\filter($this->items, \Closure::fromCallable($predicate)) as $item) {
            $filtered->add($item);
        }
        return $filtered;
    }

    /**
     * Apply a transformation and return a new TypedCollection of the given output type.
     * Backed by Psl\Vec\map() internally; result is re-wrapped into a typed collection.
     *
     * @template U
     *
     * @param callable(T): U         $transform
     * @param class-string<U>|string $outputType
     *
     * @return static<U>
     */
    public function map(callable $transform, string $outputType): static
    {
        /** @var static<U> $mapped */
        $mapped = new static($outputType);
        foreach (Vec\map($this->items, \Closure::fromCallable($transform)) as $item) {
            $mapped->add($item);
        }
        return $mapped;
    }

    /**
     * Reduce the collection to a single value.
     *
     * @template U
     *
     * @param callable(U, T): U $callback
     * @param U                 $initial
     *
     * @return U
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        $carry = $initial;
        foreach ($this->items as $item) {
            $carry = $callback($carry, $item);
        }
        return $carry;
    }

    /**
     * Execute a callback for each item (side-effects only, no return value).
     *
     * @param callable(T, int): void $callback
     */
    public function each(callable $callback): void
    {
        foreach ($this->items as $index => $item) {
            $callback($item, $index);
        }
    }

    /**
     * Return a new TypedCollection with items in reversed order.
     * Backed by Psl\Vec\reverse().
     *
     * @return static<T>
     */
    public function reverse(): static
    {
        return static::fromArray($this->type, Vec\reverse($this->items));
    }

    /**
     * Return a new TypedCollection with a slice of items.
     * Backed by Psl\Vec\slice().
     *
     * @param int      $offset Zero-based start index
     * @param int|null $length Maximum number of items (null = to end)
     *
     * @return static<T>
     */
    public function slice(int $offset, ?int $length = null): static
    {
        assert($offset >= 0, 'TypedCollection::slice(): $offset must be non-negative.');
        assert($length === null || $length >= 0, 'TypedCollection::slice(): $length must be non-negative or null.');
        return static::fromArray($this->type, Vec\slice($this->items, $offset, $length));
    }

    /**
     * Return the collection split into chunks of at most $size items.
     * Each chunk is a new TypedCollection of the same declared type.
     * Backed by Psl\Vec\chunk().
     *
     * @param int $size Maximum items per chunk (must be >= 1)
     *
     * @return list<static<T>>
     */
    public function chunk(int $size): array
    {
        if ($size < 1) {
            throw new \InvalidArgumentException(
                sprintf('TypedCollection::chunk() — $size must be a positive integer, got %d.', $size),
            );
        }
        /** @var list<static<T>> $result */
        $result = [];
        foreach (Vec\chunk($this->items, $size) as $chunkItems) {
            $result[] = static::fromArray($this->type, $chunkItems);
        }
        return $result;
    }

    /**
     * Return a new TypedCollection sorted by a comparator.
     * Backed by Psl\Vec\sort().
     *
     * @param callable(T, T): int $comparator
     *
     * @return static<T>
     */
    public function sort(callable $comparator): static
    {
        return static::fromArray($this->type, Vec\sort($this->items, \Closure::fromCallable($comparator)));
    }

    /**
     * Return a new TypedCollection with duplicate items removed (strict equality).
     * Items are compared via spl_object_id for objects, or value equality for primitives.
     *
     * @return static<T>
     */
    public function unique(): static
    {
        $seen = [];
        /** @var static<T> $unique */
        $unique = new static($this->type);
        foreach ($this->items as $item) {
            $key = is_object($item) ? spl_object_id($item) : serialize($item);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique->add($item);
            }
        }
        return $unique;
    }

    // -------------------------------------------------------------------------
    // Countable
    // -------------------------------------------------------------------------

    public function count(): int
    {
        return count($this->items);
    }

    // -------------------------------------------------------------------------
    // ArrayAccess
    // -------------------------------------------------------------------------

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * @return T
     */
    public function offsetGet(mixed $offset): mixed
    {
        if (!isset($this->items[$offset])) {
            throw new \OutOfBoundsException(
                sprintf(
                    'TypedCollection<%s>: offset %s does not exist (collection has %d items).',
                    $this->type,
                    var_export($offset, true),
                    count($this->items),
                ),
            );
        }
        return $this->items[$offset];
    }

    /**
     * @param int|null $offset
     * @param T        $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->assertType($value);
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
        // Re-index to maintain sequential integer keys
        $this->items = array_values($this->items);
    }

    // -------------------------------------------------------------------------
    // Iterator
    // -------------------------------------------------------------------------

    /**
     * @return T
     */
    public function current(): mixed
    {
        return $this->items[$this->position];
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        ++$this->position;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        return isset($this->items[$this->position]);
    }

    // -------------------------------------------------------------------------
    // Type assertion
    // -------------------------------------------------------------------------

    /**
     * Assert that the given item matches the declared type.
     *
     *
     * @throws \InvalidArgumentException if the type does not match
     */
    private function assertType(mixed $item): void
    {
        $actualType = get_debug_type($item);

        // Check primitives first using get_debug_type() (works for null, scalars, etc.)
        if (in_array($this->type, self::PRIMITIVE_TYPES, true)) {
            $normalizedDeclared = $this->normalizePrimitive($this->type);
            $normalizedActual = $this->normalizePrimitive($actualType);

            if ($normalizedDeclared !== $normalizedActual) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'TypedCollection<%s>: expected value of type %s, got %s. Value: %s',
                        $this->type,
                        $this->type,
                        $actualType,
                        var_export($item, true),
                    ),
                );
            }
            return;
        }

        // Class / interface check
        if (!($item instanceof $this->type)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'TypedCollection<%s>: expected instance of %s, got %s.',
                    $this->type,
                    $this->type,
                    $actualType,
                ),
            );
        }
    }

    /**
     * Normalize primitive type aliases for comparison.
     */
    private function normalizePrimitive(string $type): string
    {
        return match ($type) {
            'integer' => 'int',
            'double'  => 'float',
            'boolean' => 'bool',
            default   => $type,
        };
    }
}
