<?php

declare(strict_types=1);

namespace core;

use Psl\Dict as PslDict;

/**
 * core\Dict — A typed key-value dictionary. (Java HashMap / Python dict equivalent).
 *
 * Keys are always strings. Values must be of the declared type.
 * Accessing a missing key throws OutOfBoundsException instead of returning null.
 * Setting a wrong-type value throws InvalidArgumentException immediately.
 *
 * Usage (with global alias — no `use` required):
 *   $config = new Dict('string');
 *   $config->set('host', 'localhost');
 *   $config->set('port', 3306); // throws InvalidArgumentException (wrong type)
 *   echo $config->get('host');  // 'localhost'
 *   echo $config->get('missing'); // throws OutOfBoundsException
 *
 *   $users = new Dict(User::class);
 *   $users->set('alice', new User('Alice'));
 *   $user = $users->get('alice'); // User
 *
 * Global alias: Dict (registered in bootstrap.php — already a short name)
 *
 * @template V
 *
 * @implements \ArrayAccess<string, V>
 * @implements \IteratorAggregate<string, V>
 */
final class Dict implements \ArrayAccess, \IteratorAggregate, \Countable
{
    /**
     * The declared value type for all entries.
     */
    private readonly string $type;

    /**
     * Internal storage.
     *
     * @var array<string, V>
     */
    private array $data = [];

    /**
     * @param string $type FQCN or primitive type name for values
     */
    public function __construct(string $type)
    {
        if (trim($type) === '') {
            throw new \InvalidArgumentException(
                'Dict: value type must be a non-empty class name or primitive type string.',
            );
        }
        $this->type = $type;
    }

    // -------------------------------------------------------------------------
    // Static factories (compatibility: old array → new Dict)
    // -------------------------------------------------------------------------

    /**
     * Create a Dict from a plain PHP associative array.
     *
     * Every value is validated against the declared type.
     * Throws InvalidArgumentException on the first type mismatch.
     *
     * Example — migrating old code:
     *   Before: $config = ['host' => 'localhost', 'port' => '3306'];
     *   After:  $config = Dict::fromArray('string', ['host' => 'localhost', 'db' => 'app']);
     *
     * @param string       $type Declared value type (FQCN or primitive)
     * @param array<mixed> $data Associative PHP array (non-string keys are cast to string)
     *
     * @throws \InvalidArgumentException on the first value with the wrong type
     *
     * @return static<V>
     */
    public static function fromArray(string $type, array $data): static
    {
        $dict = new static($type);
        foreach ($data as $key => $value) {
            $dict->set((string) $key, $value);
        }
        return $dict;
    }

    /**
     * Create a Dict from a plain object (stdClass or any public-property object).
     *
     * Uses get_object_vars() to extract public properties as string keys.
     *
     * Example:
     *   $obj = json_decode('{"host":"localhost","db":"app"}');
     *   $config = Dict::fromObject('string', $obj);
     *
     * @param string $type   Declared value type
     * @param object $object Source object
     *
     * @return static<V>
     */
    public static function fromObject(string $type, object $object): static
    {
        return static::fromArray($type, get_object_vars($object));
    }

    // -------------------------------------------------------------------------
    // Mutation
    // -------------------------------------------------------------------------

    /**
     * Merge another Dict of the same type into a NEW Dict.
     *
     * Neither the original nor the $other is mutated.
     * In case of key collision, $other's values win.
     *
     * @param Dict<V> $other
     *
     * @throws \InvalidArgumentException if the declared types differ
     *
     * @return static<V>
     */
    public function merge(self $other): static
    {
        if ($this->type !== $other->getType()) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Dict::merge() — type mismatch: cannot merge Dict<%s> into Dict<%s>.',
                    $other->getType(),
                    $this->type,
                ),
            );
        }
        $merged = static::fromArray($this->type, $this->data);
        foreach ($other->toArray() as $key => $value) {
            $merged->set($key, $value);
        }
        return $merged;
    }

    /**
     * Return a NEW Dict containing only the specified keys.
     * Missing keys are silently skipped (not an error).
     *
     * @return static<V>
     */
    public function only(string ...$keys): static
    {
        $subset = new static($this->type);
        foreach ($keys as $key) {
            if (array_key_exists($key, $this->data)) {
                $subset->set($key, $this->data[$key]);
            }
        }
        return $subset;
    }

    /**
     * Return a NEW Dict with the specified keys excluded.
     *
     * @return static<V>
     */
    public function except(string ...$keys): static
    {
        $filtered = new static($this->type);
        $excluded = array_flip($keys);
        foreach ($this->data as $key => $value) {
            if (!isset($excluded[$key])) {
                $filtered->set($key, $value);
            }
        }
        return $filtered;
    }

    /**
     * Return true if the dictionary has no entries.
     */
    public function isEmpty(): bool
    {
        return empty($this->data);
    }

    /**
     * Return true if the dictionary has at least one entry.
     */
    public function isNotEmpty(): bool
    {
        return !empty($this->data);
    }

    /**
     * Set a key-value pair.
     *
     * @param string $key   The dictionary key
     * @param V      $value The value (must match the declared type)
     *
     * @throws \InvalidArgumentException if value does not match declared type
     */
    public function set(string $key, mixed $value): void
    {
        $this->assertType($value);
        $this->data[$key] = $value;
    }

    /**
     * Get a value by key.
     *
     * @param string $key The key to retrieve
     *
     * @throws \OutOfBoundsException if the key does not exist
     *
     * @return V The value
     */
    public function get(string $key): mixed
    {
        if (!array_key_exists($key, $this->data)) {
            throw new \OutOfBoundsException(
                sprintf(
                    'Dict<%s>: key "%s" does not exist. Available keys: [%s]',
                    $this->type,
                    $key,
                    implode(', ', array_keys($this->data)),
                ),
            );
        }
        return $this->data[$key];
    }

    /**
     * Return a value by key, or the given default if the key does not exist.
     *
     * @param V $default
     *
     * @return V
     */
    public function getOrDefault(string $key, mixed $default): mixed
    {
        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }

    /**
     * Returns true if the key exists.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Remove a key from the dictionary.
     */
    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    /**
     * Return all keys.
     *
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->data);
    }

    /**
     * Return all values.
     *
     * @return array<int, V>
     */
    public function values(): array
    {
        return array_values($this->data);
    }

    /**
     * Return the internal data as a plain associative array.
     *
     * @return array<string, V>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Return the declared value type.
     */
    public function getType(): string
    {
        return $this->type;
    }

    // -------------------------------------------------------------------------
    // Countable
    // -------------------------------------------------------------------------

    public function count(): int
    {
        return count($this->data);
    }

    // -------------------------------------------------------------------------
    // ArrayAccess
    // -------------------------------------------------------------------------

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists((string) $offset, $this->data);
    }

    /** @return V */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    /** @param V $value */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set((string) $offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->remove((string) $offset);
    }

    // -------------------------------------------------------------------------
    // IteratorAggregate
    // -------------------------------------------------------------------------

    /** @return \ArrayIterator<string, V> */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->data);
    }

    // -------------------------------------------------------------------------
    // Type assertion
    // -------------------------------------------------------------------------

    private const PRIMITIVE_TYPES = ['string', 'int', 'integer', 'float', 'double', 'bool', 'boolean'];

    private function assertType(mixed $value): void
    {
        $actual = get_debug_type($value);

        if (in_array($this->type, self::PRIMITIVE_TYPES, true)) {
            $normalized = $this->normalizePrimitive($this->type);
            if ($this->normalizePrimitive($actual) !== $normalized) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Dict<%s>: expected value of type %s, got %s.',
                        $this->type,
                        $this->type,
                        $actual,
                    ),
                );
            }
            return;
        }

        if (!($value instanceof $this->type)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Dict<%s>: expected instance of %s, got %s.',
                    $this->type,
                    $this->type,
                    $actual,
                ),
            );
        }
    }

    private function normalizePrimitive(string $type): string
    {
        return match ($type) {
            'integer' => 'int',
            'double'  => 'float',
            'boolean' => 'bool',
            default   => $type,
        };
    }

    // -------------------------------------------------------------------------
    // PSL-backed static utilities (plain array, no type enforcement)
    // Use these when you need quick functional operations on raw associative arrays.
    // -------------------------------------------------------------------------

    /**
     * Merge two or more plain associative arrays, later values win on collision.
     * Backed by Psl\Dict\merge().
     *
     * @template Tk of array-key
     * @template Tv
     *
     * @param array<Tk, Tv> $first
     * @param array<Tk, Tv> $second
     *
     * @return array<Tk, Tv>
     */
    public static function mergeArrays(array $first, array $second): array
    {
        return PslDict\merge($first, $second);
    }

    /**
     * Filter a plain associative array by value predicate, preserving keys.
     * Backed by Psl\Dict\filter().
     *
     * @template Tk of array-key
     * @template Tv
     *
     * @param array<Tk, Tv>      $data
     * @param callable(Tv): bool $predicate
     *
     * @return array<Tk, Tv>
     */
    public static function filterArray(array $data, callable $predicate): array
    {
        return PslDict\filter($data, \Closure::fromCallable($predicate));
    }

    /**
     * Filter a plain associative array by key predicate, preserving values.
     * Backed by Psl\Dict\filter_keys().
     *
     * @template Tk of array-key
     * @template Tv
     *
     * @param array<Tk, Tv>      $data
     * @param callable(Tk): bool $predicate
     *
     * @return array<Tk, Tv>
     */
    public static function filterKeys(array $data, callable $predicate): array
    {
        return PslDict\filter_keys($data, \Closure::fromCallable($predicate));
    }

    /**
     * Map over a plain associative array's values, preserving keys.
     * Backed by Psl\Dict\map().
     *
     * @template Tk of array-key
     * @template Tv
     * @template Tu
     *
     * @param array<Tk, Tv>    $data
     * @param callable(Tv): Tu $transform
     *
     * @return array<Tk, Tu>
     */
    public static function mapArray(array $data, callable $transform): array
    {
        return PslDict\map($data, \Closure::fromCallable($transform));
    }

    /**
     * Select only the specified keys from a plain associative array.
     * Backed by Psl\Dict\select_keys().
     *
     * @template Tk of array-key
     * @template Tv
     *
     * @param array<Tk, Tv> $data
     * @param list<Tk>      $keys
     *
     * @return array<Tk, Tv>
     */
    public static function selectKeys(array $data, array $keys): array
    {
        return PslDict\select_keys($data, $keys);
    }

    /**
     * Sort a plain associative array by value using a comparator, preserving keys.
     * Backed by Psl\Dict\sort().
     *
     * @template Tk of array-key
     * @template Tv
     *
     * @param array<Tk, Tv>         $data
     * @param callable(Tv, Tv): int $comparator
     *
     * @return array<Tk, Tv>
     */
    public static function sortArray(array $data, callable $comparator): array
    {
        return PslDict\sort($data, \Closure::fromCallable($comparator));
    }

    /**
     * Flip keys and values in a plain associative array.
     * Backed by Psl\Dict\flip().
     *
     * @template Tk of array-key
     * @template Tv of array-key
     *
     * @param array<Tk, Tv> $data
     *
     * @return array<Tv, Tk>
     */
    public static function flipArray(array $data): array
    {
        return PslDict\flip($data);
    }
}
