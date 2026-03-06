<?php

declare(strict_types=1);

/**
 * CorePHP (PHP-JVM) — Global Safety Function Shims
 *
 * These are globally available functions (no `use` or namespace required)
 * that provide the shortest possible API for the most common safe operations.
 *
 * They are thin wrappers around the std\Security\Safe and std\IO classes.
 * All throw typed exceptions on failure — never return false/null/0.
 *
 * Think of them as the "primitives" of CorePHP — always available, always safe.
 *
 * Available functions:
 *   s_json($str)      — Safe json_decode (throws JsonDecodeException)
 *   s_enc($val)       — Safe json_encode (throws JsonEncodeException)
 *   s_int($val)       — Safe intval (throws TypeCoercionException)
 *   s_float($val)     — Safe floatval (throws TypeCoercionException)
 *   s_file($path)     — Safe file_get_contents (throws FileReadException)
 *   s_write($p, $d)   — Safe file_put_contents (throws FileWriteException)
 *   s_get($url)       — Safe HTTP GET (throws HttpException)
 *   s_post($url, $b)  — Safe HTTP POST (throws HttpException)
 */

use std\Net\Http\HttpResponse;
use std\Security\Safe\Safe;

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
     * @throws \std\Security\Safe\JsonDecodeException on invalid JSON
     */
    function s_json(string $json, bool $associative = true): mixed
    {
        return Safe::jsonDecode($json, $associative);
    }
}

if (!function_exists('s_enc')) {
    /**
     * Safely encode a value to JSON.
     *
     * @param mixed $value The value to encode
     * @param int   $flags JSON flags (JSON_PRETTY_PRINT, etc.)
     *
     * @return string The JSON-encoded string
     *
     * @throws \std\Security\Safe\JsonEncodeException if encoding fails
     */
    function s_enc(mixed $value, int $flags = 0): string
    {
        return Safe::jsonEncode($value, $flags);
    }
}

// =============================================================================
// Type coercion
// =============================================================================

if (!function_exists('s_int')) {
    /**
     * Strictly coerce a value to int.
     * Throws instead of silently returning 0 for non-numeric input.
     *
     * @param mixed $value
     *
     * @return int
     *
     * @throws \std\Security\Safe\TypeCoercionException for non-numeric values
     */
    function s_int(mixed $value): int
    {
        return Safe::toInt($value);
    }
}

if (!function_exists('s_float')) {
    /**
     * Strictly coerce a value to float.
     * Throws instead of silently returning 0.0 for non-numeric input.
     *
     * @param mixed $value
     *
     * @return float
     *
     * @throws \std\Security\Safe\TypeCoercionException for non-numeric values
     */
    function s_float(mixed $value): float
    {
        return Safe::toFloat($value);
    }
}

// =============================================================================
// File I/O
// =============================================================================

if (!function_exists('s_file')) {
    /**
     * Safely read a file's contents.
     * Throws instead of silently returning false.
     *
     * @param string $path Absolute or relative path to file
     *
     * @return string The file contents
     *
     * @throws \std\Security\Safe\FileReadException if file missing or unreadable
     */
    function s_file(string $path): string
    {
        return Safe::fileRead($path);
    }
}

if (!function_exists('s_write')) {
    /**
     * Safely write data to a file.
     * Throws instead of silently returning false.
     *
     * @param string $path   File path
     * @param string $data   Data to write
     * @param bool   $append Append to file instead of overwriting
     *
     * @return int Bytes written
     *
     * @throws \std\Security\Safe\FileWriteException if write fails
     */
    function s_write(string $path, string $data, bool $append = false): int
    {
        return Safe::fileWrite($path, $data, $append ? FILE_APPEND : 0);
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
     * @throws \std\Net\Http\HttpException on transport failure
     */
    function s_get(string $url, array $headers = []): HttpResponse
    {
        return \std\IO::get($url, $headers);
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
     * @throws \std\Net\Http\HttpException on transport failure
     */
    function s_post(string $url, array|string $body = [], array $headers = []): HttpResponse
    {
        return \std\IO::post($url, $body, $headers);
    }
}

// =============================================================================
// Compatibility bridge — convert between plain PHP arrays and std collections
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
     * @return \std\Vec<mixed>
     *
     * @throws \InvalidArgumentException on type mismatch
     */
    function arr_to_list(string $type, array $items): \std\Vec
    {
        return \std\Vec::fromArray($type, array_values($items));
    }
}

if (!function_exists('list_to_arr')) {
    /**
     * Convert a Vec / TypedCollection back to a plain PHP array.
     *
     * Use this when passing data to legacy code that expects arrays.
     *
     * @param \std\Internal\Array\TypedCollection<mixed> $list
     *
     * @return array<int, mixed>
     */
    function list_to_arr(\std\Internal\Array\TypedCollection $list): array
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
     * @return \std\Dict<mixed>
     *
     * @throws \InvalidArgumentException on type mismatch
     */
    function arr_to_dict(string $type, array $data): \std\Dict
    {
        return \std\Dict::fromArray($type, $data);
    }
}

if (!function_exists('dict_to_arr')) {
    /**
     * Convert a Dict back to a plain PHP associative array.
     *
     * Use this when passing data to legacy code that expects arrays.
     *
     * @param \std\Dict<mixed> $dict
     *
     * @return array<string, mixed>
     */
    function dict_to_arr(\std\Dict $dict): array
    {
        return $dict->toArray();
    }
}
