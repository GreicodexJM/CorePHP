<?php

declare(strict_types=1);

namespace core\Net\Http;

/**
 * HttpClient — A curl wrapper that turns ALL HTTP errors into typed exceptions.
 *
 * Replaces raw curl_* calls which return false/null silently on failure.
 * Every failure path throws HttpException with a descriptive message.
 *
 * Features:
 *   - GET and POST requests
 *   - Custom headers support
 *   - Response parsed into immutable HttpResponse value object
 *   - Strict mode: optionally throws HttpException on 4xx/5xx responses
 *   - Configurable timeout
 *
 * Usage:
 *   $client = new HttpClient();
 *   $response = $client->get('https://api.example.com/users');
 *   $users = $response->json(associative: true);
 *
 *   $response = $client->post('https://api.example.com/users', ['name' => 'Alice']);
 *   if ($response->isSuccess()) { ... }
 */
final class HttpClient
{
    private const DEFAULT_TIMEOUT       = 30;
    private const DEFAULT_CONNECT_TIMEOUT = 10;
    private const USER_AGENT            = 'CorePHP-HttpClient/1.0 (PHP-JVM)';

    /**
     * @param int                   $timeout        Maximum total execution time in seconds
     * @param int                   $connectTimeout Connection timeout in seconds
     * @param bool                  $strictStatus   If true, throw HttpException on 4xx/5xx responses
     * @param bool                  $followRedirects If true, follow HTTP redirects
     * @param int                   $maxRedirects   Maximum number of redirects to follow
     * @param array<string, string> $defaultHeaders  Headers sent with every request (merged before per-request headers)
     */
    public function __construct(
        private readonly int   $timeout        = self::DEFAULT_TIMEOUT,
        private readonly int   $connectTimeout = self::DEFAULT_CONNECT_TIMEOUT,
        private readonly bool  $strictStatus   = false,
        private readonly bool  $followRedirects = true,
        private readonly int   $maxRedirects   = 5,
        private readonly array $defaultHeaders  = [],
    ) {
        if ($this->timeout <= 0) {
            throw new \InvalidArgumentException(
                sprintf('HttpClient: timeout must be a positive integer, got %d.', $this->timeout)
            );
        }
        if ($this->connectTimeout <= 0) {
            throw new \InvalidArgumentException(
                sprintf('HttpClient: connectTimeout must be a positive integer, got %d.', $this->connectTimeout)
            );
        }
    }

    /**
     * Perform an HTTP GET request.
     *
     * @param string                $url     The URL to request
     * @param array<string, string> $headers Additional HTTP headers
     *
     * @throws HttpException on curl error or (if strictStatus) on 4xx/5xx
     */
    public function get(string $url, array $headers = []): HttpResponse
    {
        return $this->execute($url, 'GET', null, $headers);
    }

    /**
     * Perform an HTTP POST request.
     *
     * @param string                     $url     The URL to request
     * @param array<string, mixed>|string $body    Request body (array = JSON, string = raw)
     * @param array<string, string>      $headers Additional HTTP headers
     *
     * @throws HttpException on curl error or (if strictStatus) on 4xx/5xx
     */
    public function post(string $url, array|string $body = [], array $headers = []): HttpResponse
    {
        return $this->execute($url, 'POST', $body, $headers);
    }

    /**
     * Perform an HTTP PUT request.
     *
     * @param string                     $url
     * @param array<string, mixed>|string $body
     * @param array<string, string>      $headers
     *
     * @throws HttpException
     */
    public function put(string $url, array|string $body = [], array $headers = []): HttpResponse
    {
        return $this->execute($url, 'PUT', $body, $headers);
    }

    /**
     * Perform an HTTP PATCH request.
     *
     * @param string                      $url
     * @param array<string, mixed>|string $body
     * @param array<string, string>       $headers
     *
     * @throws HttpException
     */
    public function patch(string $url, array|string $body = [], array $headers = []): HttpResponse
    {
        return $this->execute($url, 'PATCH', $body, $headers);
    }

    /**
     * Perform an HTTP DELETE request.
     *
     * @param string                $url
     * @param array<string, string> $headers
     *
     * @throws HttpException
     */
    public function delete(string $url, array $headers = []): HttpResponse
    {
        return $this->execute($url, 'DELETE', null, $headers);
    }

    // -------------------------------------------------------------------------
    // Core execution
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed>|string|null $body
     * @param array<string, string>            $headers
     */
    private function execute(
        string             $url,
        string             $method,
        array|string|null  $body,
        array              $headers
    ): HttpResponse {
        if (trim($url) === '') {
            throw new HttpException('HttpClient: URL cannot be empty.');
        }

        $handle = curl_init();

        if ($handle === false) {
            throw new HttpException('HttpClient: curl_init() failed — curl not available.');
        }

        try {
            $this->configureCurl($handle, $url, $method, $body, $headers);

            // Capture response headers
            $responseHeaders = [];
            curl_setopt($handle, CURLOPT_HEADERFUNCTION, static function ($ch, string $headerLine) use (&$responseHeaders): int {
                $trimmed = trim($headerLine);
                if (str_contains($trimmed, ':')) {
                    [$name, $value] = explode(':', $trimmed, 2);
                    $responseHeaders[strtolower(trim($name))] = trim($value);
                }
                return strlen($headerLine);
            });

            // Execute — curl_exec is overridden by FunctionOverrider to throw HttpException on false
            $rawBody = \curl_exec($handle);

            if ($rawBody === false) {
                $errno = curl_errno($handle);
                $error = curl_error($handle);
                throw HttpException::fromCurlError($errno, $error);
            }

            $statusCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
            $finalUrl   = (string) curl_getinfo($handle, CURLINFO_EFFECTIVE_URL);
            $totalTime  = (float) curl_getinfo($handle, CURLINFO_TOTAL_TIME);

            /** @var string $rawBody */
            $response = new HttpResponse(
                $statusCode,
                $rawBody,
                $responseHeaders,
                $finalUrl,
                $totalTime
            );

            if ($this->strictStatus && ($response->isClientError() || $response->isServerError())) {
                throw HttpException::fromHttpStatus($statusCode, $url, $rawBody);
            }

            return $response;

        } finally {
            curl_close($handle);
        }
    }

    /**
     * Configure curl options on the given handle.
     *
     * @param \CurlHandle                      $handle
     * @param array<string, mixed>|string|null $body
     * @param array<string, string>            $extraHeaders
     */
    private function configureCurl(
        \CurlHandle        $handle,
        string             $url,
        string             $method,
        array|string|null  $body,
        array              $extraHeaders
    ): void {
        // URL is already validated as non-empty in execute(); assert here for PHPStan
        assert($url !== '', 'HttpClient: URL must be non-empty at this point.');

        curl_setopt_array($handle, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_USERAGENT      => self::USER_AGENT,
            CURLOPT_FOLLOWLOCATION => $this->followRedirects,
            CURLOPT_MAXREDIRS      => $this->maxRedirects,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING       => '',
        ]);

        // Header priority (lowest → highest): base defaults, per-client defaults, per-request
        $headers = array_merge(
            ['accept' => 'application/json'],
            array_change_key_case($this->defaultHeaders, CASE_LOWER),
            array_change_key_case($extraHeaders, CASE_LOWER)
        );

        // Prepare body and set method
        if ($body !== null) {
            if (is_array($body)) {
                $encodedBody = \Psl\Json\encode($body);
                $headers['content-type'] = 'application/json';
                curl_setopt($handle, CURLOPT_POSTFIELDS, $encodedBody);
            } else {
                // @phpstan-ignore-next-line argument.type (raw string body may be empty for valid POST requests)
                curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
            }
        }

        match ($method) {
            'GET'    => curl_setopt($handle, CURLOPT_HTTPGET, true),
            'POST'   => curl_setopt($handle, CURLOPT_POST, true),
            'PUT'    => curl_setopt($handle, CURLOPT_CUSTOMREQUEST, 'PUT'),
            'DELETE' => curl_setopt($handle, CURLOPT_CUSTOMREQUEST, 'DELETE'),
            default  => curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method),
        };

        // Format headers for curl
        $formattedHeaders = [];
        foreach ($headers as $name => $value) {
            $formattedHeaders[] = sprintf('%s: %s', $name, $value);
        }

        curl_setopt($handle, CURLOPT_HTTPHEADER, $formattedHeaders);
    }
}
