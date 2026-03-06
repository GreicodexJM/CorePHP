<?php

declare(strict_types=1);

namespace core\Tests\Net\Http;

use PHPUnit\Framework\TestCase;
use core\Net\Http\HttpClient;
use core\Net\Http\HttpException;
use core\Net\Http\HttpResponse;

/**
 * @covers \core\Net\Http\HttpClient
 * @covers \core\Net\Http\HttpResponse
 * @covers \core\Net\Http\HttpException
 *
 * NOTE: Integration tests (tagged @group integration) require a network connection
 * and hit https://httpbun.com (a public echo API). They are skipped automatically
 * when no network is available.
 *
 * Run unit-only tests:    vendor/bin/phpunit --exclude-group integration
 * Run integration tests:  vendor/bin/phpunit --group integration
 */
final class HttpClientTest extends TestCase
{
    // =========================================================================
    // HttpClient constructor validation — no network needed
    // =========================================================================

    public function testConstructorAcceptsValidTimeout(): void
    {
        $client = new HttpClient(timeout: 10);
        self::assertInstanceOf(HttpClient::class, $client);
    }

    public function testConstructorRejectsNegativeTimeout(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HttpClient(timeout: -1);
    }

    public function testConstructorRejectsZeroTimeout(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HttpClient(timeout: 0);
    }

    // =========================================================================
    // Empty URL guard — no network needed
    // =========================================================================

    public function testGetThrowsOnEmptyUrl(): void
    {
        $this->expectException(HttpException::class);
        (new HttpClient())->get('');
    }

    public function testPostThrowsOnEmptyUrl(): void
    {
        $this->expectException(HttpException::class);
        (new HttpClient())->post('');
    }

    // =========================================================================
    // HttpResponse — unit tests (no network)
    //
    // HttpResponse constructor signature:
    //   __construct(int $statusCode, string $body, array $headers, string $url, float $totalTime)
    // =========================================================================

    private function makeResponse(int $status, string $body = '', array $headers = []): HttpResponse
    {
        return new HttpResponse($status, $body, $headers, 'https://example.com', 0.05);
    }

    public function testStatusCode(): void
    {
        self::assertSame(200, $this->makeResponse(200)->statusCode());
        self::assertSame(404, $this->makeResponse(404)->statusCode());
    }

    public function testBody(): void
    {
        $r = $this->makeResponse(200, 'hello body');
        self::assertSame('hello body', $r->body());
    }

    public function testUrl(): void
    {
        $r = new HttpResponse(200, '', [], 'https://redirected.com', 0.1);
        self::assertSame('https://redirected.com', $r->url());
    }

    public function testTotalTime(): void
    {
        $r = new HttpResponse(200, '', [], '', 1.23);
        self::assertSame(1.23, $r->totalTime());
    }

    public function testIsOkOnlyFor200(): void
    {
        self::assertTrue($this->makeResponse(200)->isOk());
        self::assertFalse($this->makeResponse(201)->isOk());
        self::assertFalse($this->makeResponse(404)->isOk());
    }

    public function testIsSuccess(): void
    {
        foreach ([200, 201, 204, 206, 299] as $code) {
            self::assertTrue($this->makeResponse($code)->isSuccess(), "$code should be success");
        }
        foreach ([300, 400, 404, 500] as $code) {
            self::assertFalse($this->makeResponse($code)->isSuccess(), "$code should not be success");
        }
    }

    public function testIsClientError(): void
    {
        self::assertTrue($this->makeResponse(404)->isClientError());
        self::assertTrue($this->makeResponse(422)->isClientError());
        self::assertFalse($this->makeResponse(200)->isClientError());
        self::assertFalse($this->makeResponse(500)->isClientError());
    }

    public function testIsServerError(): void
    {
        self::assertTrue($this->makeResponse(500)->isServerError());
        self::assertTrue($this->makeResponse(503)->isServerError());
        self::assertFalse($this->makeResponse(200)->isServerError());
        self::assertFalse($this->makeResponse(404)->isServerError());
    }

    public function testJsonDecodesBodyAsArray(): void
    {
        $r    = $this->makeResponse(200, '{"name":"Alice","age":30}');
        $data = $r->json(true);
        self::assertSame(['name' => 'Alice', 'age' => 30], $data);
    }

    public function testJsonDecodesBodyAsObject(): void
    {
        $r    = $this->makeResponse(200, '{"name":"Alice"}');
        $data = $r->json(false);
        self::assertInstanceOf(\stdClass::class, $data);
        self::assertSame('Alice', $data->name);
    }

    public function testJsonThrowsOnInvalidBody(): void
    {
        $this->expectException(\core\Security\Safe\JsonDecodeException::class);
        $this->makeResponse(200, 'not json')->json(true);
    }

    public function testHeaderReturnsNullForMissingHeader(): void
    {
        $r = $this->makeResponse(200, '', ['x-present' => 'yes']);
        self::assertNull($r->header('x-missing'));
    }

    public function testHeaderReturnsPresentHeader(): void
    {
        $r = $this->makeResponse(200, '', ['x-custom' => 'my-value']);
        self::assertSame('my-value', $r->header('x-custom'));
    }

    public function testHeaderIsCaseInsensitive(): void
    {
        $r = $this->makeResponse(200, '', ['content-type' => 'application/json']);
        self::assertSame('application/json', $r->header('Content-Type'));
    }

    public function testRequireHeaderThrowsOnMissing(): void
    {
        $this->expectException(HttpException::class);
        $this->makeResponse(200)->requireHeader('x-missing');
    }

    public function testRequireHeaderReturnsPresentHeader(): void
    {
        $r = $this->makeResponse(200, '', ['authorization' => 'Bearer token']);
        self::assertSame('Bearer token', $r->requireHeader('authorization'));
    }

    public function testHeadersReturnsAllHeaders(): void
    {
        $headers = ['a' => '1', 'b' => '2'];
        $r       = $this->makeResponse(200, '', $headers);
        self::assertSame($headers, $r->headers());
    }

    // =========================================================================
    // Integration tests — require network + httpbun.com
    // =========================================================================

    /**
     * @group integration
     */
    public function testGetRequestReturnsSuccessfulResponse(): void
    {
        if (!$this->isNetworkAvailable()) {
            self::markTestSkipped('No network available.');
        }

        $response = (new HttpClient(timeout: 10))->get('https://httpbun.com/get');
        self::assertTrue($response->isSuccess(), 'Expected 2xx from httpbun.com/get');
        self::assertNotEmpty($response->body());
    }

    /**
     * @group integration
     */
    public function testPostRequestSendsJsonBody(): void
    {
        if (!$this->isNetworkAvailable()) {
            self::markTestSkipped('No network available.');
        }

        $response = (new HttpClient(timeout: 10))->post('https://httpbun.com/post', ['hello' => 'world']);
        self::assertTrue($response->isSuccess());
        $data = $response->json(true);
        self::assertArrayHasKey('json', $data);
    }

    /**
     * @group integration
     */
    public function testStrictStatusThrowsOn404(): void
    {
        if (!$this->isNetworkAvailable()) {
            self::markTestSkipped('No network available.');
        }

        $this->expectException(HttpException::class);
        (new HttpClient(timeout: 10, strictStatus: true))->get('https://httpbun.com/status/404');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function isNetworkAvailable(): bool
    {
        $handle = @fsockopen('httpbun.com', 443, $errno, $errstr, 3);
        if ($handle) {
            fclose($handle);
            return true;
        }
        return false;
    }
}
