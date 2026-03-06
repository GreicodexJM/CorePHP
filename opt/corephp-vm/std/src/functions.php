<?php

declare(strict_types=1);

/**
 * CorePHP — Global Safety Function Shims
 *
 * Globally available functions (no `use` or namespace required) backed by
 * the azjezz/psl standard library. All throw typed PSL exceptions on failure —
 * never return false/null/0.
 *
 * Think of them as the "primitives" of CorePHP — always available, always safe.
 *
 * JSON:
 *   s_json($str)           — Safe json_decode   → Psl\Json\decode()
 *   s_enc($val, $pretty)   — Safe json_encode   → Psl\Json\encode()
 *
 * Type coercion (strict — throws on bad input):
 *   s_int($val)            — Strict int coerce  → Psl\Type\int()->coerce()
 *   s_float($val)          — Strict float coerce → Psl\Type\float()->coerce()
 *   s_str($val)            — Strict string coerce → Psl\Type\string()->coerce()
 *   s_bool($val)           — Strict bool coerce → Psl\Type\bool()->coerce()
 *
 * File I/O (bytes-compatible drop-in replacement):
 *   s_file($path)          — Safe file read     → Psl\File\read()
 *   s_write($p, $d, $app)  — Safe file write    → Psl\File\write()  [returns int bytes]
 *   s_append($path, $data) — Safe file append   → Psl\File\write(APPEND)
 *   s_fwrite($h, $data)    — Safe fwrite handle → fwrite() + throw on false
 *
 * Regex:
 *   s_match($pat, $sub)    — Bool regex match   → Psl\Regex\matches()
 *   s_regex($pat, $sub)    — First match groups → Psl\Regex\first_match()
 *   s_regex_all($p, $sub)  — All match groups   → Psl\Regex\every_match()
 *
 * Environment:
 *   s_env($key)            — Safe env var       → Psl\Env\get_var() or throw
 *
 * HTTP:
 *   s_get($url, $headers)         — Safe HTTP GET  → core\IO::get()
 *   s_post($url, $body, $headers) — Safe HTTP POST → core\IO::post()
 *
 * Array ↔ Collection bridge:
 *   arr_to_list($type, $items)    — Plain array → Vec
 *   list_to_arr($list)            — Vec → plain array
 *   arr_to_dict($type, $data)     — Plain assoc array → Dict
 *   dict_to_arr($dict)            — Dict → plain assoc array
 *
 * Vec / Dict functional shims:
 *   vec_filter($list, $fn)        — Psl\Vec\filter()
 *   vec_map($list, $fn)           — Psl\Vec\map()
 *   dict_filter($dict, $fn)       — Psl\Dict\filter()
 *   dict_map($dict, $fn)          — Psl\Dict\map()
 *   dict_merge(...$dicts)         — Psl\Dict\merge()
 */

use core\Net\Http\HttpResponse;
use Psl\Env;
use Psl\File;
use Psl\File\WriteMode;
use Psl\Json;
use Psl\Regex;
use Psl\Type;
use Psl\Vec;
use Psl\Dict;

// =============================================================================
// JSON
// =============================================================================

if (!function_exists('s_json')) {
    /**
     * Safely decode a JSON string.
     *
     * @param string $json        The JSON string
     * @param bool   $associative Return as associative array (default: true)
     *
     * @return mixed The decoded value
     *
     * @throws \Psl\Json\Exception on invalid JSON or empty string
     */
    function s_json(string $json, bool $associative = true): mixed
    {
        return Json\decode($json, $associative);
    }
}

if (!function_exists('s_enc')) {
    /**
     * Safely encode a value to JSON.
     *
     * @param mixed $value  The value to encode
     * @param bool  $pretty Pretty-print the JSON output (default: false)
     * @param int   $flags  Additional JSON flags
     *
     * @return string The JSON-encoded string
     *
     * @throws \Psl\Json\Exception if encoding fails
     */
    function s_enc(mixed $value, bool $pretty = false, int $flags = 0): string
    {
        return Json\encode($value, $pretty, $flags);
    }
}

// =============================================================================
// Type coercion — strict, no silent 0 / false / '' fallbacks
// =============================================================================

if (!function_exists('s_int')) {
    /**
     * Strictly coerce a value to int.
     * Accepts: int, float, numeric string, bool.
     * Rejects: non-numeric strings, arrays, objects, null.
     *
     * @param mixed $value
     *
     * @return int
     *
     * @throws \Psl\Type\Exception\CoercionException for non-coercible values
     */
    function s_int(mixed $value): int
    {
        return Type\int()->coerce($value);
    }
}

if (!function_exists('s_float')) {
    /**
     * Strictly coerce a value to float.
     * Accepts: float, int, numeric string.
     * Rejects: non-numeric strings, arrays, objects, null, bool.
     *
     * @param mixed $value
     *
     * @return float
     *
     * @throws \Psl\Type\Exception\CoercionException for non-coercible values
     */
    function s_float(mixed $value): float
    {
        return Type\float()->coerce($value);
    }
}

if (!function_exists('s_str')) {
    /**
     * Strictly coerce a value to string.
     * Accepts: string, int, float, Stringable objects.
     * Rejects: arrays, non-Stringable objects, null, bool.
     *
     * @param mixed $value
     *
     * @return string
     *
     * @throws \Psl\Type\Exception\CoercionException for non-coercible values
     */
    function s_str(mixed $value): string
    {
        return Type\string()->coerce($value);
    }
}

if (!function_exists('s_bool')) {
    /**
     * Strictly coerce a value to bool.
     * Accepts: bool, int (0/1), string ('true'/'false'/'1'/'0').
     * Rejects: other types.
     *
     * @param mixed $value
     *
     * @return bool
     *
     * @throws \Psl\Type\Exception\CoercionException for non-coercible values
     */
    function s_bool(mixed $value): bool
    {
        return Type\bool()->coerce($value);
    }
}

// =============================================================================
// File I/O — bytes-compatible drop-in replacement for file_get_contents / file_put_contents
// =============================================================================

if (!function_exists('s_file')) {
    /**
     * Safely read a file's contents.
     * Throws instead of silently returning false.
     *
     * @param string   $path   Absolute or relative path to file
     * @param int      $offset Byte offset to start reading from
     * @param int|null $length Maximum number of bytes to read
     *
     * @return string The file contents
     *
     * @throws \Psl\File\Exception\RuntimeException if file missing or unreadable
     */
    function s_file(string $path, int $offset = 0, ?int $length = null): string
    {
        return File\read($path, $offset, $length);
    }
}

if (!function_exists('s_write')) {
    /**
     * Safely write data to a file.
     * Throws instead of silently returning false.
     * Returns strlen($data) for drop-in compatibility with file_put_contents().
     *
     * @param string $path   File path
     * @param string $data   Data to write
     * @param bool   $append Append to file instead of overwriting (default: false)
     *
     * @return int Bytes written (strlen of $data)
     *
     * @throws \Psl\File\Exception\RuntimeException if write fails
     */
    function s_write(string $path, string $data, bool $append = false): int
    {
        $mode = $append ? WriteMode::Append : WriteMode::OpenOrCreate;
        File\write($path, $data, $mode);
        return strlen($data);
    }
}

if (!function_exists('s_append')) {
    /**
     * Safely append data to a file.
     * Shorthand for s_write($path, $data, append: true).
     *
     * @param string $path File path
     * @param string $data Data to append
     *
     * @return int Bytes written (strlen of $data)
     *
     * @throws \Psl\File\Exception\RuntimeException if write fails
     */
    function s_append(string $path, string $data): int
    {
        File\write($path, $data, WriteMode::Append);
        return strlen($data);
    }
}

if (!function_exists('s_fwrite')) {
    /**
     * Safely write data to an open file handle.
     * Throws instead of silently returning false.
     *
     * @param resource $handle An open file handle (from fopen())
     * @param string   $data   Data to write
     *
     * @return int Bytes written
     *
     * @throws \Psl\File\Exception\RuntimeException if fwrite fails
     */
    function s_fwrite(mixed $handle, string $data): int
    {
        if (!is_resource($handle)) {
            throw new \Psl\File\Exception\RuntimeException(
                's_fwrite: $handle must be an open file resource, got ' . get_debug_type($handle)
            );
        }
        $result = \fwrite($handle, $data);
        if ($result === false) {
            throw new \Psl\File\Exception\RuntimeException(
                's_fwrite: fwrite() failed — handle may be closed or the filesystem is full.'
            );
        }
        return $result;
    }
}

// =============================================================================
// Regex — backed by Psl\Regex
// =============================================================================

if (!function_exists('s_match')) {
    /**
     * Test whether a pattern matches the subject string.
     *
     * @param non-empty-string $pattern PCRE pattern (e.g. '/^\d+$/')
     * @param string           $subject String to test
     *
     * @return bool True if the pattern matches
     *
     * @throws \Psl\Regex\Exception\RuntimeException on invalid pattern
     */
    function s_match(string $pattern, string $subject): bool
    {
        return Regex\matches($subject, $pattern);
    }
}

if (!function_exists('s_regex')) {
    /**
     * Return the first regex match's capture groups as an array.
     * Returns null if there is no match (does not throw on no-match).
     *
     * @param non-empty-string $pattern PCRE pattern
     * @param string           $subject String to search
     *
     * @return array<array-key, string>|null Match groups, or null if no match
     *
     * @throws \Psl\Regex\Exception\RuntimeException on invalid pattern
     */
    function s_regex(string $pattern, string $subject): ?array
    {
        return Regex\first_match($subject, $pattern);
    }
}

if (!function_exists('s_regex_all')) {
    /**
     * Return all regex matches as a list of capture group arrays.
     * Returns an empty array if there are no matches.
     *
     * @param non-empty-string $pattern PCRE pattern
     * @param string           $subject String to search
     *
     * @return list<array<array-key, string>> All match groups
     *
     * @throws \Psl\Regex\Exception\RuntimeException on invalid pattern
     */
    function s_regex_all(string $pattern, string $subject): array
    {
        return Regex\every_match($subject, $pattern);
    }
}

// =============================================================================
// Environment
// =============================================================================

if (!function_exists('s_env')) {
    /**
     * Safely retrieve an environment variable.
     * Throws if the variable is not set, instead of returning null.
     *
     * @param string $key Environment variable name
     *
     * @return string The environment variable value
     *
     * @throws \RuntimeException if the variable is not set
     */
    function s_env(string $key): string
    {
        $value = Env\get_var($key);
        if ($value === null) {
            throw new \RuntimeException(
                sprintf(
                    's_env: environment variable "%s" is not set. '
                    . 'Ensure it is defined in your .env file or container configuration.',
                    $key
                )
            );
        }
        return $value;
    }
}

if (!function_exists('s_env_or')) {
    /**
     * Retrieve an environment variable, returning $default if not set.
     *
     * @param string $key     Environment variable name
     * @param string $default Fallback value if the variable is not set
     *
     * @return string
     */
    function s_env_or(string $key, string $default): string
    {
        return Env\get_var($key) ?? $default;
    }
}

// =============================================================================
// HTTP
// =============================================================================

if (!function_exists('s_get')) {
    /**
     * Perform a safe HTTP GET request.
     *
     * @param string                $url     The URL to request
     * @param array<string, string> $headers Optional headers
     *
     * @return HttpResponse
     *
     * @throws \core\Net\Http\HttpException on transport failure
     */
    function s_get(string $url, array $headers = []): HttpResponse
    {
        return \core\IO::get($url, $headers);
    }
}

if (!function_exists('s_post')) {
    /**
     * Perform a safe HTTP POST request.
     *
     * @param string                      $url     The URL to post to
     * @param array<string, mixed>|string $body    Request body
     * @param array<string, string>       $headers Optional headers
     *
     * @return HttpResponse
     *
     * @throws \core\Net\Http\HttpException on transport failure
     */
    function s_post(string $url, array|string $body = [], array $headers = []): HttpResponse
    {
        return \core\IO::post($url, $body, $headers);
    }
}

// =============================================================================
// Array ↔ Collection bridge — migrate plain PHP arrays to/from typed collections
// =============================================================================

if (!function_exists('arr_to_list')) {
    /**
     * Convert a plain PHP array to a typed Vec (ArrayList).
     *
     * Every element is validated against the declared type.
     * Throws InvalidArgumentException on the first mismatch.
     *
     * Example — migrating legacy code:
     *   Before: $ids = [1, 2, 3];
     *   After:  $ids = arr_to_list('int', [1, 2, 3]);
     *
     * @param string       $type  Declared element type (FQCN or primitive)
     * @param array<mixed> $items Plain PHP array (sequential or associative)
     *
     * @return \core\Vec<mixed>
     *
     * @throws \InvalidArgumentException on type mismatch
     */
    function arr_to_list(string $type, array $items): \core\Vec
    {
        return \core\Vec::fromArray($type, array_values($items));
    }
}

if (!function_exists('list_to_arr')) {
    /**
     * Convert a Vec / TypedCollection back to a plain PHP array.
     *
     * Use this when passing data to legacy code that expects arrays.
     *
     * @param \core\Internal\Array\TypedCollection<mixed> $list
     *
     * @return array<int, mixed>
     */
    function list_to_arr(\core\Internal\Array\TypedCollection $list): array
    {
        return $list->toArray();
    }
}

if (!function_exists('arr_to_dict')) {
    /**
     * Convert a plain PHP associative array to a typed Dict.
     *
     * Every value is validated against the declared type.
     * Non-string keys are cast to string.
     *
     * Example — migrating legacy code:
     *   Before: $cfg = ['host' => 'localhost', 'db' => 'myapp'];
     *   After:  $cfg = arr_to_dict('string', ['host' => 'localhost', 'db' => 'myapp']);
     *
     * @param string               $type Declared value type (FQCN or primitive)
     * @param array<string, mixed> $data Associative PHP array
     *
     * @return \core\Dict<mixed>
     *
     * @throws \InvalidArgumentException on type mismatch
     */
    function arr_to_dict(string $type, array $data): \core\Dict
    {
        return \core\Dict::fromArray($type, $data);
    }
}

if (!function_exists('dict_to_arr')) {
    /**
     * Convert a Dict back to a plain PHP associative array.
     *
     * Use this when passing data to legacy code that expects arrays.
     *
     * @param \core\Dict<mixed> $dict
     *
     * @return array<string, mixed>
     */
    function dict_to_arr(\core\Dict $dict): array
    {
        return $dict->toArray();
    }
}

// =============================================================================
// Vec / Dict functional shims — backed by Psl\Vec / Psl\Dict
// These provide a short global API for the most common list/dict operations
// without requiring `use Psl\Vec;` in every file.
// =============================================================================

if (!function_exists('vec_filter')) {
    /**
     * Filter a list (or array), returning only elements matching the predicate.
     * Result is always re-indexed as a list<T>.
     *
     * @template T
     * @param iterable<T>       $list      Any iterable
     * @param callable(T): bool $predicate Filter function
     *
     * @return list<T>
     */
    function vec_filter(iterable $list, callable $predicate): array
    {
        return Vec\filter($list, $predicate);
    }
}

if (!function_exists('vec_map')) {
    /**
     * Map a list (or array) to a new list using a transform function.
     * Result is always a list<T> (re-indexed).
     *
     * @template T
     * @template U
     * @param iterable<T>  $list      Any iterable
     * @param callable(T): U $transform Transform function
     *
     * @return list<U>
     */
    function vec_map(iterable $list, callable $transform): array
    {
        return Vec\map($list, $transform);
    }
}

if (!function_exists('dict_filter')) {
    /**
     * Filter an associative array, returning only entries matching the predicate.
     *
     * @template Tk of array-key
     * @template Tv
     * @param array<Tk, Tv>      $dict      Associative array
     * @param callable(Tv): bool $predicate Filter function (receives values)
     *
     * @return array<Tk, Tv>
     */
    function dict_filter(array $dict, callable $predicate): array
    {
        return Dict\filter($dict, $predicate);
    }
}

if (!function_exists('dict_map')) {
    /**
     * Map over an associative array's values, preserving keys.
     *
     * @template Tk of array-key
     * @template Tv
     * @template Tu
     * @param array<Tk, Tv>   $dict      Associative array
     * @param callable(Tv): Tu $transform Transform function
     *
     * @return array<Tk, Tu>
     */
    function dict_map(array $dict, callable $transform): array
    {
        return Dict\map($dict, $transform);
    }
}

if (!function_exists('dict_merge')) {
    /**
     * Merge two or more associative arrays. Later values win on key collision.
     * Backed by Psl\Dict\merge() for consistent behaviour.
     *
     * @param array<array-key, mixed> ...$dicts Associative arrays to merge
     *
     * @return array<array-key, mixed>
     */
    function dict_merge(array ...$dicts): array
    {
        if (count($dicts) === 0) {
            return [];
        }
        $result = array_shift($dicts);
        foreach ($dicts as $dict) {
            $result = Dict\merge($result, $dict);
        }
        return $result;
    }
}
