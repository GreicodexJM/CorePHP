<?php

declare(strict_types=1);

namespace core\Net\Http;

/**
 * Thrown when an HTTP request fails at the transport or protocol level.
 *
 * This covers:
 *   - curl errors (connection refused, timeout, DNS failure, etc.)
 *   - HTTP 4xx / 5xx responses (when strict mode is enabled on HttpClient)
 *
 * The exception code maps to the curl errno for transport errors,
 * or the HTTP status code for HTTP-level errors.
 */
final class HttpException extends \RuntimeException
{
    /**
     * Create an HttpException for a transport-level curl failure.
     *
     * @param int    $curlErrno The curl error number
     * @param string $curlError The curl error message
     */
    public static function fromCurlError(int $curlErrno, string $curlError): self
    {
        return new self(
            sprintf('HTTP transport error [curl errno %d]: %s', $curlErrno, $curlError),
            $curlErrno
        );
    }

    /**
     * Create an HttpException for an HTTP-level error response (4xx / 5xx).
     *
     * @param int    $statusCode The HTTP status code
     * @param string $url        The requested URL
     * @param string $body       The response body (first 500 chars)
     */
    public static function fromHttpStatus(int $statusCode, string $url, string $body = ''): self
    {
        return new self(
            sprintf(
                'HTTP request to %s returned error status %d. Body: %s',
                $url,
                $statusCode,
                substr($body, 0, 500)
            ),
            $statusCode
        );
    }
}
