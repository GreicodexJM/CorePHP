<?php

declare(strict_types=1);

namespace std\Tests;

use PHPUnit\Framework\TestCase;
use std\IO;
use std\Security\Safe\FileReadException;
use std\Security\Safe\FileWriteException;
use std\Security\Safe\JsonDecodeException;

/**
 * @covers \std\IO
 * @uses   \std\Security\Safe\Safe
 * @uses   \std\Net\Http\HttpClient
 */
final class IOTest extends TestCase
{
    private string $tmpFile;
    private string $tmpJsonFile;

    protected function setUp(): void
    {
        $base              = sys_get_temp_dir() . '/corephp_io_test_' . uniqid();
        $this->tmpFile     = $base . '.txt';
        $this->tmpJsonFile = $base . '.json';
    }

    protected function tearDown(): void
    {
        foreach ([$this->tmpFile, $this->tmpJsonFile] as $f) {
            if (file_exists($f)) {
                unlink($f);
            }
        }
    }

    // =========================================================================
    // read()
    // =========================================================================

    public function testReadReturnsFileContents(): void
    {
        file_put_contents($this->tmpFile, 'hello from IO');
        self::assertSame('hello from IO', IO::read($this->tmpFile));
    }

    public function testReadThrowsOnMissingFile(): void
    {
        $this->expectException(FileReadException::class);
        IO::read('/no/such/file.txt');
    }

    // =========================================================================
    // write()
    // =========================================================================

    public function testWriteCreatesFile(): void
    {
        $bytes = IO::write($this->tmpFile, 'data');
        self::assertGreaterThan(0, $bytes);
        self::assertSame('data', file_get_contents($this->tmpFile));
    }

    public function testWriteAppendsWhenFlagTrue(): void
    {
        IO::write($this->tmpFile, 'first');
        IO::write($this->tmpFile, ' second', true);
        self::assertSame('first second', file_get_contents($this->tmpFile));
    }

    public function testWriteThrowsOnUnwritablePath(): void
    {
        $this->expectException(FileWriteException::class);
        IO::write('/root/unwritable_' . uniqid() . '.txt', 'data');
    }

    // =========================================================================
    // json()
    // =========================================================================

    public function testJsonReadsAndDecodesJsonFile(): void
    {
        file_put_contents($this->tmpJsonFile, '{"name":"Alice","age":30}');
        $data = IO::json($this->tmpJsonFile);
        self::assertSame(['name' => 'Alice', 'age' => 30], $data);
    }

    public function testJsonThrowsOnMissingFile(): void
    {
        $this->expectException(FileReadException::class);
        IO::json('/no/such/file.json');
    }

    public function testJsonThrowsOnInvalidJson(): void
    {
        file_put_contents($this->tmpJsonFile, 'not valid json');
        $this->expectException(JsonDecodeException::class);
        IO::json($this->tmpJsonFile);
    }

    // =========================================================================
    // writeJson()
    // =========================================================================

    public function testWriteJsonEncodesAndWritesFile(): void
    {
        $data  = ['host' => 'localhost', 'port' => 3306];
        $bytes = IO::writeJson($this->tmpJsonFile, $data);
        self::assertGreaterThan(0, $bytes);
        $decoded = json_decode(file_get_contents($this->tmpJsonFile), true);
        self::assertSame($data, $decoded);
    }

    // =========================================================================
    // exists()
    // =========================================================================

    public function testExistsReturnsTrueForExistingFile(): void
    {
        file_put_contents($this->tmpFile, 'content');
        self::assertTrue(IO::exists($this->tmpFile));
    }

    public function testExistsReturnsFalseForMissingFile(): void
    {
        self::assertFalse(IO::exists('/no/such/path.txt'));
    }

    public function testExistsReturnsTrueForDirectory(): void
    {
        self::assertTrue(IO::exists(sys_get_temp_dir()));
    }

    // =========================================================================
    // http() — factory only; no real network calls in unit tests
    // =========================================================================

    public function testHttpReturnsHttpClientInstance(): void
    {
        $client = IO::http();
        self::assertInstanceOf(\std\Net\Http\HttpClient::class, $client);
    }
}
