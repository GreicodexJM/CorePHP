<?php

declare(strict_types=1);

namespace std\Security\Safe;

/**
 * Safe — Static helpers for PHP functions that fail silently by default.
 *
 * This class provides a clean, explicit API for callers that want to use
 * the Safe wrappers directly (instead of relying on the runkit7 override).
 *
 * All methods throw named, typed exceptions on any failure.
 * None of them ever return false, null, or 0 as an error indicator.
 *
 * Usage:
 *   $data  = Safe::jsonDecode($payload);       // throws JsonDecodeException
 *   $id    = Safe::toInt($request['user_id']); // throws TypeCoercionException
 *   $body  = Safe::fileRead('/etc/config');    // throws FileReadException
 *   $json  = Safe::jsonEncode($object);        // throws JsonEncodeException
 *   $n     = Safe::toFloat('3.14');            // throws TypeCoercionException
 *   $bytes = Safe::fileWrite('/tmp/out', $s);  // throws FileWriteException
 */
final class Safe
{
    /**
     * Decode a JSON string and return the result.
     *
     * @param string $json          The JSON string to decode
     * @param bool   $associative   Return associative array instead of object
     * @param int    $depth         Maximum nesting depth
     *
     * @return mixed The decoded value (never null on error)
     *
     * @throws JsonDecodeException if the JSON is invalid or malformed
     */
    public static function jsonDecode(
        string $json,
        bool   $associative = false,
        int    $depth = 512
    ): mixed {
        if (trim($json) === '') {
            throw new JsonDecodeException(
                'Safe::jsonDecode received an empty string. '
                . 'Expected a valid JSON payload.'
            );
        }

        try {
            $result = \json_decode($json, $associative, $depth, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new JsonDecodeException(
                sprintf(
                    'Safe::jsonDecode failed: %s — input (first 200 chars): %s',
                    $e->getMessage(),
                    substr($json, 0, 200)
                ),
                (int) $e->getCode(),
                $e
            );
        }

        return $result;
    }

    /**
     * Encode a value to a JSON string.
     *
     * @param mixed $value The value to encode
     * @param int   $flags JSON encoding flags
     * @param int   $depth Maximum nesting depth
     *
     * @return string The JSON-encoded string (never false)
     *
     * @throws JsonEncodeException if the value cannot be serialized
     */
    public static function jsonEncode(
        mixed $value,
        int   $flags = 0,
        int   $depth = 512
    ): string {
        try {
            $result = \json_encode($value, $flags | JSON_THROW_ON_ERROR, $depth);
        } catch (\JsonException $e) {
            throw new JsonEncodeException(
                sprintf(
                    'Safe::jsonEncode failed for value of type %s: %s',
                    get_debug_type($value),
                    $e->getMessage()
                ),
                (int) $e->getCode(),
                $e
            );
        }

        if ($result === false) {
            throw new JsonEncodeException(
                sprintf(
                    'Safe::jsonEncode returned false for value of type: %s',
                    get_debug_type($value)
                )
            );
        }

        return $result;
    }

    /**
     * Coerce a value to int strictly — no silent 0 for non-numeric strings.
     *
     * Accepts: int, float, numeric string, bool
     * Rejects: non-numeric strings, arrays, objects, null
     *
     * @param mixed $value The value to coerce
     *
     * @return int The integer value
     *
     * @throws TypeCoercionException if the value cannot be safely coerced to int
     */
    public static function toInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_bool($value)) {
            return (int) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        throw new TypeCoercionException(
            sprintf(
                'Safe::toInt cannot coerce value of type %s to int. '
                . 'Only int, float, bool, and numeric strings are accepted. '
                . 'Value: %s',
                get_debug_type($value),
                var_export($value, true)
            )
        );
    }

    /**
     * Coerce a value to float strictly.
     *
     * Accepts: float, int, numeric string
     * Rejects: non-numeric strings, arrays, objects, null, bool
     *
     * @param mixed $value The value to coerce
     *
     * @return float The float value
     *
     * @throws TypeCoercionException if the value cannot be safely coerced to float
     */
    public static function toFloat(mixed $value): float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        throw new TypeCoercionException(
            sprintf(
                'Safe::toFloat cannot coerce value of type %s to float. '
                . 'Only float, int, and numeric strings are accepted. '
                . 'Value: %s',
                get_debug_type($value),
                var_export($value, true)
            )
        );
    }

    /**
     * Read a file and return its contents as a string.
     *
     * @param string $path Absolute or relative path to the file
     *
     * @return string The file contents (never false)
     *
     * @throws FileReadException if the file does not exist, is unreadable, or read fails
     */
    public static function fileRead(string $path): string
    {
        if (!file_exists($path)) {
            throw new FileReadException(
                sprintf('Safe::fileRead — file does not exist: %s', $path)
            );
        }

        if (!is_readable($path)) {
            throw new FileReadException(
                sprintf('Safe::fileRead — file is not readable: %s', $path)
            );
        }

        $contents = \file_get_contents($path);

        if ($contents === false) {
            throw new FileReadException(
                sprintf('Safe::fileRead — failed to read file: %s', $path)
            );
        }

        return $contents;
    }

    /**
     * Write data to a file and return the number of bytes written.
     *
     * @param string $path  Absolute or relative path to the file
     * @param string $data  The data to write
     * @param int    $flags FILE_APPEND, LOCK_EX, etc.
     *
     * @return int Number of bytes written (never false)
     *
     * @throws FileWriteException if the write fails
     */
    public static function fileWrite(string $path, string $data, int $flags = 0): int
    {
        $result = \file_put_contents($path, $data, $flags);

        if ($result === false) {
            throw new FileWriteException(
                sprintf('Safe::fileWrite — failed to write to file: %s', $path)
            );
        }

        return $result;
    }
}
