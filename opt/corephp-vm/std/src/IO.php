<?php

declare(strict_types=1);

namespace core;

use Psl\File\WriteMode;
use Psl\Json;
use Psl\File;
use core\Net\Http\HttpClient;
use core\Net\Http\HttpResponse;

/**
 * core\IO — Safe I/O façade for file and HTTP operations.
 *
 * Every method delegates to the azjezz/psl standard library (Psl\File, Psl\Json)
 * which throws typed exceptions on every failure path — never returns false/null.
 *
 * PSL exceptions thrown (all extend \RuntimeException):
 *   Psl\File\Exception\RuntimeException  — file read/write failures
 *   Psl\Json\Exception                   — JSON encode/decode failures
 *
 * Usage (no `use` statement needed — loaded globally via bootstrap):
 *   $content  = core\IO::read('config.json');          // Psl\File\Exception on failure
 *   $bytes    = core\IO::write('/tmp/out.txt', $data); // Psl\File\Exception on failure
 *   $data     = core\IO::json('config.json');          // Reads + decodes JSON
 *   $response = core\IO::get('https://api.example.com/users');
 */
final class IO
{
    /**
     * Read a file and return its contents.
     *
     * @param string   $path   Absolute or relative path
     * @param int      $offset Byte offset to start reading from
     * @param int|null $length Maximum number of bytes to read
     *
     * @return string The file contents
     *
     * @throws \Psl\File\Exception\RuntimeException if file does not exist or is unreadable
     */
    public static function read(string $path, int $offset = 0, ?int $length = null): string
    {
        return File\read($path, $offset, $length);
    }

    /**
     * Write data to a file and return the number of bytes written.
     *
     * Returns the byte count (strlen of data written) for drop-in compatibility
     * with legacy code that expected file_put_contents() return values.
     *
     * @param string $path   File path
     * @param string $data   Data to write
     * @param bool   $append Append to existing file instead of overwriting
     *
     * @return int Bytes written (strlen of $data)
     *
     * @throws \Psl\File\Exception\RuntimeException if write fails
     */
    public static function write(string $path, string $data, bool $append = false): int
    {
        $mode = $append ? WriteMode::Append : WriteMode::OpenOrCreate;
        File\write($path, $data, $mode);
        return strlen($data);
    }

    /**
     * Append data to a file and return the number of bytes written.
     *
     * @param string $path File path
     * @param string $data Data to append
     *
     * @return int Bytes written (strlen of $data)
     *
     * @throws \Psl\File\Exception\RuntimeException if write fails
     */
    public static function append(string $path, string $data): int
    {
        File\write($path, $data, WriteMode::Append);
        return strlen($data);
    }

    /**
     * Read a JSON file and decode it.
     *
     * @param string $path        Path to JSON file
     * @param bool   $associative Return associative array instead of object
     *
     * @return mixed The decoded JSON value
     *
     * @throws \Psl\File\Exception\RuntimeException if file cannot be read
     * @throws \Psl\Json\Exception                  if file content is not valid JSON
     */
    public static function json(string $path, bool $associative = true): mixed
    {
        $contents = File\read($path);
        return Json\decode($contents, $associative);
    }

    /**
     * Write a value as JSON to a file.
     *
     * @param string $path   File path
     * @param mixed  $data   Data to encode and write
     * @param bool   $pretty Pretty-print the JSON output
     *
     * @return int Bytes written (strlen of encoded JSON)
     *
     * @throws \Psl\Json\Exception                  if data cannot be encoded
     * @throws \Psl\File\Exception\RuntimeException if write fails
     */
    public static function writeJson(string $path, mixed $data, bool $pretty = true): int
    {
        $json = Json\encode($data, $pretty);
        File\write($path, $json, WriteMode::OpenOrCreate);
        return strlen($json);
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
