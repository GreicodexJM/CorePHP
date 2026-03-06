<?php

declare(strict_types=1);

namespace std\Net\Http;

/**
 * HttpResponse — Immutable value object representing an HTTP response.
 *
 * Constructed from a raw curl response. All properties are read-only.
 * Provides type-safe accessors — no raw array manipulation by callers.
 */
final class HttpResponse
{
    /**
     * @param int                   $statusCode  HTTP status code (e.g. 200, 404)
     * @param string                $body        The raw response body
     * @param array<string, string> $headers     Response headers (name => value, lowercased names)
     * @param string                $url         The final URL after redirects
     * @param float                 $totalTime   Total request time in seconds
     */
    public function __construct(
        private readonly int    $statusCode,
        private readonly string $body,
        private readonly array  $headers,
        private readonly string $url,
        private readonly float  $totalTime,
    ) {
    }

    /**
     * Return the HTTP status code.
     */
    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Return the raw response body as a string.
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * Decode and return the response body as a JSON object/array.
     *
     * @param bool $associative Return as associative array instead of object
     *
     * @throws \std\Security\Safe\JsonDecodeException if body is not valid JSON
     */
    public function json(bool $associative = false): mixed
    {
        return \std\Security\Safe\Safe::jsonDecode($this->body, $associative);
    }

    /**
     * Return all response headers.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Return a specific response header by name (case-insensitive).
     *
     * @throws HttpException if the header is not present
     */
    public function header(string $name): string
    {
        $key = strtolower($name);
        if (!isset($this->headers[$key])) {
            throw new HttpException(
                sprintf('Response header "%s" is not present in this response.', $name)
            );
        }
        return $this->headers[$key];
    }

    /**
     * Return the final URL after any redirects.
     */
    public function url(): string
    {
        return $this->url;
    }

    /**
     * Return the total request time in seconds.
     */
    public function totalTime(): float
    {
        return $this->totalTime;
    }

    /**
     * Returns true if the status code indicates success (2xx).
     */
    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Returns true if the status code indicates a client error (4xx).
     */
    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    /**
     * Returns true if the status code indicates a server error (5xx).
     */
    public function isServerError(): bool
    {
        return $this->statusCode >= 500;
    }
}
