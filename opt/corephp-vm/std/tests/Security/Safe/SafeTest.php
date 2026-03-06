<?php

declare(strict_types=1);

namespace core\Tests\Security\Safe;

use PHPUnit\Framework\TestCase;
use core\Security\Safe\FileReadException;
use core\Security\Safe\FileWriteException;
use core\Security\Safe\JsonDecodeException;
use core\Security\Safe\JsonEncodeException;
use core\Security\Safe\Safe;
use core\Security\Safe\TypeCoercionException;

/**
 * @covers \core\Security\Safe\Safe
 */
final class SafeTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir() . '/corephp_safe_test_' . uniqid() . '.txt';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    // =========================================================================
    // jsonDecode()
    // =========================================================================

    public function testJsonDecodeReturnsAssocArray(): void
    {
        $result = Safe::jsonDecode('{"name":"Alice","age":30}', true);
        self::assertSame(['name' => 'Alice', 'age' => 30], $result);
    }

    public function testJsonDecodeReturnsObjectWhenAssocFalse(): void
    {
        $result = Safe::jsonDecode('{"name":"Alice"}', false);
        self::assertInstanceOf(\stdClass::class, $result);
        self::assertSame('Alice', $result->name);
    }

    public function testJsonDecodeHandlesArray(): void
    {
        $result = Safe::jsonDecode('[1,2,3]');
        self::assertSame([1, 2, 3], $result);
    }

    public function testJsonDecodeThrowsOnInvalidJson(): void
    {
        $this->expectException(JsonDecodeException::class);
        Safe::jsonDecode('{not valid json}');
    }

    public function testJsonDecodeThrowsOnEmptyString(): void
    {
        $this->expectException(JsonDecodeException::class);
        Safe::jsonDecode('');
    }

    public function testJsonDecodeExceptionContainsInput(): void
    {
        try {
            Safe::jsonDecode('bad input');
            self::fail('Expected JsonDecodeException');
        } catch (JsonDecodeException $e) {
            self::assertStringContainsString('bad input', $e->getMessage());
        }
    }

    // =========================================================================
    // jsonEncode()
    // =========================================================================

    public function testJsonEncodeEncodesArray(): void
    {
        $result = Safe::jsonEncode(['name' => 'Bob']);
        self::assertSame('{"name":"Bob"}', $result);
    }

    public function testJsonEncodeWithPrettyPrint(): void
    {
        $result = Safe::jsonEncode(['a' => 1], JSON_PRETTY_PRINT);
        self::assertStringContainsString("\n", $result);
    }

    public function testJsonEncodeThrowsOnUnencodableValue(): void
    {
        $this->expectException(JsonEncodeException::class);
        // INF cannot be encoded to JSON
        Safe::jsonEncode(['value' => INF]);
    }

    // =========================================================================
    // toInt()
    // =========================================================================

    public function testToIntConvertsNumericString(): void
    {
        self::assertSame(42, Safe::toInt('42'));
    }

    public function testToIntConvertsInt(): void
    {
        self::assertSame(7, Safe::toInt(7));
    }

    public function testToIntConvertsFloat(): void
    {
        self::assertSame(3, Safe::toInt(3.9));
    }

    public function testToIntThrowsOnNonNumericString(): void
    {
        $this->expectException(TypeCoercionException::class);
        Safe::toInt('not-a-number');
    }

    public function testToIntThrowsOnNull(): void
    {
        $this->expectException(TypeCoercionException::class);
        Safe::toInt(null);
    }

    public function testToIntThrowsOnArray(): void
    {
        $this->expectException(TypeCoercionException::class);
        Safe::toInt([]);
    }

    // =========================================================================
    // toFloat()
    // =========================================================================

    public function testToFloatConvertsNumericString(): void
    {
        self::assertSame(3.14, Safe::toFloat('3.14'));
    }

    public function testToFloatConvertsInt(): void
    {
        self::assertSame(5.0, Safe::toFloat(5));
    }

    public function testToFloatThrowsOnNonNumericString(): void
    {
        $this->expectException(TypeCoercionException::class);
        Safe::toFloat('abc');
    }

    // =========================================================================
    // fileRead()
    // =========================================================================

    public function testFileReadReturnsFileContents(): void
    {
        file_put_contents($this->tmpFile, 'hello world');
        self::assertSame('hello world', Safe::fileRead($this->tmpFile));
    }

    public function testFileReadThrowsOnMissingFile(): void
    {
        $this->expectException(FileReadException::class);
        Safe::fileRead('/nonexistent/path/file.txt');
    }

    public function testFileReadExceptionContainsPath(): void
    {
        $path = '/nonexistent/file.txt';
        try {
            Safe::fileRead($path);
            self::fail('Expected FileReadException');
        } catch (FileReadException $e) {
            self::assertStringContainsString($path, $e->getMessage());
        }
    }

    // =========================================================================
    // fileWrite()
    // =========================================================================

    public function testFileWriteCreatesFileWithContent(): void
    {
        $bytes = Safe::fileWrite($this->tmpFile, 'test content');
        self::assertGreaterThan(0, $bytes);
        self::assertSame('test content', file_get_contents($this->tmpFile));
    }

    public function testFileWriteAppendsWhenFlagSet(): void
    {
        Safe::fileWrite($this->tmpFile, 'first');
        Safe::fileWrite($this->tmpFile, ' second', FILE_APPEND);
        self::assertSame('first second', file_get_contents($this->tmpFile));
    }

    public function testFileWriteThrowsOnUnwritablePath(): void
    {
        // /dev/null is a character device — no file can be created inside it
        $this->expectException(FileWriteException::class);
        Safe::fileWrite('/dev/null/cannot_write_here.txt', 'data');
    }
}
