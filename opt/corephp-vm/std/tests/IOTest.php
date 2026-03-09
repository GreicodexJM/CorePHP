<?php

declare(strict_types=1);

namespace core\Tests;

use core\IO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psl\File\Exception\NotFoundException as FileNotFoundException;
use Psl\File\Exception\RuntimeException as FileException;
use Psl\Json\Exception\DecodeException as JsonDecodeException;

#[CoversClass(\core\IO::class)]
#[UsesClass(\core\Net\Http\HttpClient::class)]
final class IOTest extends TestCase
{
    private string $tmpFile;
    private string $tmpJsonFile;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/corephp_io_test_' . uniqid();
        $this->tmpFile = $base . '.txt';
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
        // PSL 4.x throws NotFoundException (extends InvalidArgumentException) for missing files.
        $this->expectException(FileNotFoundException::class);
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

    public function testWriteReturnsByteCount(): void
    {
        $data = 'hello world';
        $bytes = IO::write($this->tmpFile, $data);
        self::assertSame(strlen($data), $bytes);
    }

    public function testWriteAppendsWhenFlagTrue(): void
    {
        IO::write($this->tmpFile, 'first');
        IO::write($this->tmpFile, ' second', true);
        self::assertSame('first second', file_get_contents($this->tmpFile));
    }

    public function testWriteThrowsOnUnwritablePath(): void
    {
        $this->expectException(FileException::class);
        IO::write('/dev/null/cannot_write_here.txt', 'data');
    }

    // =========================================================================
    // append()
    // =========================================================================

    public function testAppendAddsToExistingFile(): void
    {
        IO::write($this->tmpFile, 'first');
        $bytes = IO::append($this->tmpFile, ' appended');
        self::assertSame(strlen(' appended'), $bytes);
        self::assertSame('first appended', file_get_contents($this->tmpFile));
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
        // PSL 4.x throws NotFoundException (extends InvalidArgumentException) for missing files.
        $this->expectException(FileNotFoundException::class);
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
        $data = ['host' => 'localhost', 'port' => 3306];
        $bytes = IO::writeJson($this->tmpJsonFile, $data);
        self::assertGreaterThan(0, $bytes);
        $decoded = json_decode(file_get_contents($this->tmpJsonFile), true);
        self::assertSame($data, $decoded);
    }

    public function testWriteJsonReturnsByteCount(): void
    {
        $data = ['key' => 'value'];
        $bytes = IO::writeJson($this->tmpJsonFile, $data);
        self::assertSame(filesize($this->tmpJsonFile), $bytes);
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
        self::assertInstanceOf(\core\Net\Http\HttpClient::class, $client);
    }
}
