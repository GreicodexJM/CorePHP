<?php

declare(strict_types=1);

namespace core\Engine;

use core\Security\Exceptions\EncodingException;
use core\Security\Exceptions\SecurityException;
use core\Security\Safe\FileReadException;
use core\Security\Safe\FileWriteException;
use core\Security\Safe\JsonDecodeException;
use core\Security\Safe\JsonEncodeException;
use core\Security\Safe\RegexException;
use core\Security\Safe\TypeCoercionException;

/**
 * FunctionOverrider — runkit7 Native Function Override Registry
 *
 * Replaces PHP's built-in functions that fail silently (returning false/null/0)
 * with versions that throw typed, named exceptions on any failure.
 *
 * This class uses runkit7_function_redefine() which requires:
 *   - runkit7 PECL extension installed
 *   - runkit.internal_override = 1 in php.ini
 *
 * install() is idempotent — safe to call multiple times (e.g., on worker restart).
 *
 * @see /opt/corephp-vm/bootstrap.php — calls FunctionOverrider::install() at boot
 */
final class FunctionOverrider
{
    /**
     * Tracks which functions have already been overridden.
     * Prevents runkit7 errors on duplicate redefinition.
     *
     * @var array<string, bool>
     */
    private static array $installed = [];

    /**
     * Install all function overrides.
     * Called once at process startup by bootstrap.php.
     */
    public static function install(): void
    {
        self::overrideJsonDecode();
        self::overrideJsonEncode();
        self::overrideFileGetContents();
        self::overrideFilePutContents();
        self::overrideIntval();
        self::overrideFloatval();
        self::overridePregMatch();
        self::overridePregMatchAll();
        self::overridePregReplace();
        self::overrideCurlExec();
        self::overrideBase64Decode();
        self::overrideUnserializeGuard();
    }

    // -------------------------------------------------------------------------
    // JSON
    // -------------------------------------------------------------------------

    private static function overrideJsonDecode(): void
    {
        if (self::isInstalled('json_decode')) {
            return;
        }
        /** @phpstan-ignore-next-line */
        runkit7_function_redefine(
            'json_decode',
            'string $json, bool $associative = false, int $depth = 512, int $flags = 0',
            '
            $flags = $flags | JSON_THROW_ON_ERROR;
            try {
                return \json_decode($json, $associative, $depth, $flags);
            } catch (\JsonException $e) {
                throw new \core\Security\Safe\JsonDecodeException(
                    "json_decode failed: " . $e->getMessage() . " — input: " . substr($json, 0, 100),
                    (int) $e->getCode(),
                    $e
                );
            }
            '
        );
        self::markInstalled('json_decode');
    }

    private static function overrideJsonEncode(): void
    {
        if (self::isInstalled('json_encode')) {
            return;
        }
        /** @phpstan-ignore-next-line */
        runkit7_function_redefine(
            'json_encode',
            'mixed $value, int $flags = 0, int $depth = 512',
            '
            $flags = $flags | JSON_THROW_ON_ERROR;
            try {
                $result = \json_encode($value, $flags, $depth);
                if ($result === false) {
                    throw new \core\Security\Safe\JsonEncodeException(
                        "json_encode returned false for value of type: " . get_debug_type($value)
                    );
                }
                return $result;
            } catch (\JsonException $e) {
                throw new \core\Security\Safe\JsonEncodeException(
                    "json_encode failed: " . $e->getMessage(),
                    (int) $e->getCode(),
                    $e
                );
            }
            '
        );
        self::markInstalled('json_encode');
    }

    // -------------------------------------------------------------------------
    // File I/O
    // -------------------------------------------------------------------------

    private static function overrideFileGetContents(): void
    {
        if (self::isInstalled('file_get_contents')) {
            return;
        }
        /** @phpstan-ignore-next-line */
        runkit7_function_redefine(
            'file_get_contents',
            'string $filename, bool $use_include_path = false, $context = null, int $offset = 0, ?int $length = null',
            '
            if (!file_exists($filename)) {
                throw new \core\Security\Safe\FileReadException(
                    "file_get_contents: file does not exist: " . $filename
                );
            }
            if (!is_readable($filename)) {
                throw new \core\Security\Safe\FileReadException(
                    "file_get_contents: file is not readable: " . $filename
                );
            }
            $result = \file_get_contents($filename, $use_include_path, $context, $offset, $length);
            if ($result === false) {
                throw new \core\Security\Safe\FileReadException(
                    "file_get_contents: failed to read file: " . $filename
                );
            }
            return $result;
            '
        );
        self::markInstalled('file_get_contents');
    }

    private static function overrideFilePutContents(): void
    {
        if (self::isInstalled('file_put_contents')) {
            return;
        }
        /** @phpstan-ignore-next-line */
        runkit7_function_redefine(
            'file_put_contents',
            'string $filename, mixed $data, int $flags = 0, $context = null',
            '
            $result = \file_put_contents($filename, $data, $flags, $context);
            if ($result === false) {
                throw new \core\Security\Safe\FileWriteException(
                    "file_put_contents: failed to write to file: " . $filename
                );
            }
            return $result;
            '
        );
        self::markInstalled('file_put_contents');
    }

    // -------------------------------------------------------------------------
    // Type coercion
    // -------------------------------------------------------------------------

    private static function overrideIntval(): void
    {
        if (self::isInstalled('intval')) {
            return;
        }
        /** @phpstan-ignore-next-line */
        runkit7_function_redefine(
            'intval',
            'mixed $value, int $base = 10',
            '
            if (is_int($value)) {
                return $value;
            }
            if (is_float($value)) {
                return (int) $value;
            }
            if (is_string($value) && is_numeric($value)) {
                return (int) $value;
            }
            if (is_bool($value)) {
                return (int) $value;
            }
            throw new \core\Security\Safe\TypeCoercionException(
                "intval: cannot safely coerce value of type " . get_debug_type($value) . " to int. "
                . "Value: " . var_export($value, true)
            );
            '
        );
        self::markInstalled('intval');
    }

    private static function overrideFloatval(): void
    {
        if (self::isInstalled('floatval')) {
            return;
        }
        /** @phpstan-ignore-next-line */
        runkit7_function_redefine(
            'floatval',
            'mixed $value',
            '
            if (is_float($value) || is_int($value)) {
                return (float) $value;
            }
            if (is_string($value) && is_numeric($value)) {
                return (float) $value;
            }
            throw new \core\Security\Safe\TypeCoercionException(
                "floatval: cannot safely coerce value of type " . get_debug_type($value) . " to float. "
                . "Value: " . var_export($value, true)
            );
            '
        );
        self::markInstalled('floatval');
    }

    // -------------------------------------------------------------------------
    // Regex
    // -------------------------------------------------------------------------

    private static function overridePregMatch(): void
    {
        if (self::isInstalled('preg_match')) {
            return;
        }
        /** @phpstan-ignore-next-line */
        runkit7_function_redefine(
            'preg_match',
            'string $pattern, string $subject, array &$matches = [], int $flags = 0, int $offset = 0',
            '
            $result = \preg_match($pattern, $subject, $matches, $flags, $offset);
            if ($result === false) {
                throw new \core\Security\Safe\RegexException(
                    "preg_match failed with pattern: " . $pattern
                    . " — error: " . array_flip(get_defined_constants(true)["pcre"])[preg_last_error()]
                );
            }
            return $result;
            '
        );
        self::markInstalled('preg_match');
    }

    private static function overridePregMatchAll(): void
    {
        if (self::isInstalled('preg_match_all')) {
            return;
        }
        /** @phpstan-ignore-next-line */
        runkit7_function_redefine(
            'preg_match_all',
            'string $pattern, string $subject, array &$matches = [], int $flags = 0, int $offset = 0',
            '
            $result = \preg_match_all($pattern, $subject, $matches, $flags, $offset);
            if ($result === false) {
                throw new \core\Security\Safe\RegexException(
                    "preg_match_all failed with pattern: " . $pattern
                );
            }
            return $result;
            '
        );
        self::markInstalled('preg_match_all');
    }

    private static function overridePregReplace(): void
    {
        if (self::isInstalled('preg_replace')) {
            return;
        }
        /** @phpstan-ignore-next-line */
        runkit7_function_redefine(
            'preg_replace',
            'string|array $pattern, string|array $replacement, string|array $subject, int $limit = -1, int &$count = 0',
            '
            $result = \preg_replace($pattern, $replacement, $subject, $limit, $count);
            if ($result === null) {
                throw new \core\Security\Safe\RegexException(
                    "preg_replace returned null — PCRE error for pattern: " . (is_array($pattern) ? implode(", ", $pattern) : $pattern)
                );
            }
            return $result;
            '
        );
        self::markInstalled('preg_replace');
    }

    // -------------------------------------------------------------------------
    // curl
    // -------------------------------------------------------------------------

    private static function overrideCurlExec(): void
    {
        if (self::isInstalled('curl_exec')) {
            return;
        }
        /** @phpstan-ignore-next-line */
        runkit7_function_redefine(
            'curl_exec',
            '\CurlHandle $handle',
            '
            $result = \curl_exec($handle);
            if ($result === false) {
                $errno = curl_errno($handle);
                $error = curl_error($handle);
                throw new \core\Net\Http\HttpException(
                    "curl_exec failed — error [{$errno}]: {$error}",
                    $errno
                );
            }
            return $result;
            '
        );
        self::markInstalled('curl_exec');
    }

    // -------------------------------------------------------------------------
    // Encoding
    // -------------------------------------------------------------------------

    private static function overrideBase64Decode(): void
    {
        if (self::isInstalled('base64_decode')) {
            return;
        }
        /** @phpstan-ignore-next-line */
        runkit7_function_redefine(
            'base64_decode',
            'string $string, bool $strict = true',
            '
            $result = \base64_decode($string, $strict);
            if ($result === false) {
                throw new \core\Security\Exceptions\EncodingException(
                    "base64_decode failed — invalid base64 input (strict mode). Input length: " . strlen($string)
                );
            }
            return $result;
            '
        );
        self::markInstalled('base64_decode');
    }

    // -------------------------------------------------------------------------
    // Security blocks (unserialize disabled in php.ini disable_functions,
    // but we add a runtime guard here for belt-and-suspenders)
    // -------------------------------------------------------------------------

    private static function overrideUnserializeGuard(): void
    {
        if (self::isInstalled('unserialize')) {
            return;
        }
        // unserialize should be in disable_functions in php.ini.
        // If somehow not disabled, this override provides runtime protection.
        // Note: if disable_functions includes unserialize, runkit7 cannot redefine it.
        // This is a no-op guard — it will silently skip if not possible.
        try {
            /** @phpstan-ignore-next-line */
            runkit7_function_redefine(
                'unserialize',
                'string $data, array $options = []',
                '
                throw new \core\Security\Exceptions\SecurityException(
                    "unserialize() is permanently disabled in CorePHP. "
                    . "Use JSON, MessagePack, or a typed DTO instead."
                );
                '
            );
            self::markInstalled('unserialize');
        } catch (\Throwable) {
            // unserialize is in disable_functions — already blocked at engine level
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private static function isInstalled(string $functionName): bool
    {
        return self::$installed[$functionName] ?? false;
    }

    private static function markInstalled(string $functionName): void
    {
        self::$installed[$functionName] = true;
    }
}
