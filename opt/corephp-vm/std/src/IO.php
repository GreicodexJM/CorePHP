<?php

declare(strict_types=1);

namespace core;

use core\Net\Http\HttpClient;
use core\Net\Http\HttpResponse;
use core\Security\Safe\FileReadException;
use core\Security\Safe\FileWriteException;
use core\Security\Safe\JsonDecodeException;
use core\Security\Safe\JsonEncodeException;
use core\Security\Safe\Safe;

/**
 * core\IO — Safe I/O facade for file and HTTP operations.
 *
 * Provides the cleanest possible API for the most common I/O tasks.
 * Every method throws a typed exception on failure — never returns false.
 *
 * This is equivalent to Java's Files + HttpClient combined into one
 * easy-to-access static class.
 *
 * Usage (no `use` statement needed — loaded globally):
 *   // File operations
 *   $content = core\IO::read('config.json');         // FileReadException on failure
 *   $bytes   = core\IO::write('/tmp/out.txt', $data); // FileWriteException on failure
 *   $data    = core\IO::json('config.json');          // Reads + decodes JSON
 *
 *   // HTTP operations
 *   $response = core\IO::http()->get('https://api.example.com/users');
 *   $users    = core\IO::get('https://api.example.com/users')->json(true);
 *   $created  = core\IO::post('https://api.example.com/users', ['name'=>'Alice']);
 */
final class IO
{
    /**
     * Read a file and return its contents.
     *
     * @param string $path Absolute or relative path
     *
     * @return string The file contents
     *
     * @throws FileReadException if file does not exist or is unreadable
     */
    public static function read(string $path): string
    {
        return Safe::fileRead($path);
    }

    /**
     * Write data to a file and return the number of bytes written.
     *
     * @param string $path  File path
     * @param string $data  Data to write
     * @param bool   $append Append to existing file instead of overwriting
     *
     * @return int Bytes written
     *
     * @throws FileWriteException if write fails
     */
    public static function write(string $path, string $data, bool $append = false): int
    {
        return Safe::fileWrite($path, $data, $append ? FILE_APPEND : 0);
    }

    /**
     * Read a JSON file and decode it.
     *
     * @param string $path        Path to JSON file
     * @param bool   $associative Return associative array instead of object
     *
     * @return mixed The decoded JSON value
     *
     * @throws FileReadException    if file cannot be read
     * @throws JsonDecodeException  if file content is not valid JSON
     */
    public static function json(string $path, bool $associative = true): mixed
    {
        $contents = Safe::fileRead($path);
        return Safe::jsonDecode($contents, $associative);
    }

    /**
     * Write a value as JSON to a file.
     *
     * @param string $path  File path
     * @param mixed  $data  Data to encode and write
     *
     * @return int Bytes written
     *
     * @throws JsonEncodeException  if data cannot be encoded
     * @throws FileWriteException   if write fails
     */
    public static function writeJson(string $path, mixed $data): int
    {
        $json = Safe::jsonEncode($data, JSON_PRETTY_PRINT);
        return Safe::fileWrite($path, $json);
    }

    /**
     * Return a new HttpClient instance.
     *
     * @param int  $timeout      Request timeout in seconds
     * @param bool $strictStatus Throw on 4xx/5xx responses
     */
    public static function http(int $timeout = 30, bool $strictStatus = false): HttpClient
    {
        return new HttpClient(timeout: $timeout, strictStatus: $strictStatus);
    }

    /**
     * Perform a quick GET request.
     *
     * @param string                $url
     * @param array<string, string> $headers
     *
     * @throws \core\Net\Http\HttpException on transport failure
     */
    public static function get(string $url, array $headers = []): HttpResponse
    {
        return (new HttpClient())->get($url, $headers);
    }

    /**
     * Perform a quick POST request.
     *
     * @param string                      $url
     * @param array<string, mixed>|string $body
     * @param array<string, string>       $headers
     *
     * @throws \core\Net\Http\HttpException on transport failure
     */
    public static function post(string $url, array|string $body = [], array $headers = []): HttpResponse
    {
        return (new HttpClient())->post($url, $body, $headers);
    }

    /**
     * Check if a file or directory exists.
     */
    public static function exists(string $path): bool
    {
        return file_exists($path);
    }
}
