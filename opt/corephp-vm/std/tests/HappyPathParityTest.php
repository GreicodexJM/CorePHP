<?php

declare(strict_types=1);

namespace core\Tests;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Happy-path parity: on VALID input, every s_*() safe replacement returns a
 * result identical to the native PHP function it replaces. Only the FAILURE path
 * differs (native returns null/false/0 silently; s_*() throws). This is the
 * "drop-in — behaves exactly the same on the happy path" guarantee, enforced.
 *
 * Not covered here (intentionally): s_bool() is a STRICT typed coercion, not the
 * loose PHP (bool) cast (e.g. (bool)"false" === true, but s_bool("false") === false),
 * so it has no meaningful native-parity baseline. Its behaviour is covered in
 * FunctionShimsTest.
 */
#[CoversFunction('s_json')]
#[CoversFunction('s_enc')]
#[CoversFunction('s_file')]
#[CoversFunction('s_write')]
#[CoversFunction('s_append')]
#[CoversFunction('s_int')]
#[CoversFunction('s_float')]
#[CoversFunction('s_str')]
#[CoversFunction('s_match')]
#[CoversFunction('s_regex')]
#[CoversFunction('s_regex_all')]
#[CoversFunction('s_replace')]
#[CoversFunction('s_b64')]
final class HappyPathParityTest extends TestCase
{
    /** @var list<string> */
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $f) {
            if (is_file($f)) {
                unlink($f);
            }
        }
        $this->tmpFiles = [];
    }

    private function tmp(string $suffix = ''): string
    {
        $path = sys_get_temp_dir() . '/corephp_parity_' . uniqid('', true) . $suffix;
        $this->tmpFiles[] = $path;
        return $path;
    }

    // -------------------------------------------------------------------------
    // JSON
    // -------------------------------------------------------------------------

    public function testSJsonMatchesNativeDecode(): void
    {
        $json = '{"name":"Alice","tags":[1,2,3],"active":true,"meta":{"ratio":1.5}}';
        self::assertSame(json_decode($json, true), s_json($json));
    }

    public function testSEncMatchesNativeEncode(): void
    {
        $value = ['name' => 'Bob', 'tags' => [1, 2, 3], 'active' => false, 'ratio' => 0.25];
        self::assertSame(json_encode($value), s_enc($value));
    }

    // -------------------------------------------------------------------------
    // File I/O
    // -------------------------------------------------------------------------

    public function testSFileMatchesNativeRead(): void
    {
        $path = $this->tmp('.txt');
        file_put_contents($path, "line 1\nunicode ✓ café\nend");
        self::assertSame(file_get_contents($path), s_file($path));
    }

    public function testSWriteMatchesNativeWrite(): void
    {
        $data = "payload ✓ 1234\nsecond line";
        $native = $this->tmp('.n');
        $safe = $this->tmp('.s');

        $nativeBytes = file_put_contents($native, $data);
        $safeBytes = s_write($safe, $data);

        self::assertSame($nativeBytes, $safeBytes, 'byte count parity');
        self::assertSame(file_get_contents($native), file_get_contents($safe), 'content parity');
    }

    public function testSAppendMatchesNativeAppend(): void
    {
        $native = $this->tmp('.n');
        $safe = $this->tmp('.s');
        file_put_contents($native, 'head');
        file_put_contents($safe, 'head');

        $nativeBytes = file_put_contents($native, '-tail', FILE_APPEND);
        $safeBytes = s_append($safe, '-tail');

        self::assertSame($nativeBytes, $safeBytes, 'appended byte count parity');
        self::assertSame(file_get_contents($native), file_get_contents($safe), 'content parity');
    }

    // -------------------------------------------------------------------------
    // Type coercion (valid input only)
    // -------------------------------------------------------------------------

    #[DataProvider('cleanIntProvider')]
    public function testSIntMatchesNativeIntval(int|string|float $input): void
    {
        self::assertSame((int) $input, s_int($input));
    }

    /** @return list<array{int|string|float}> */
    public static function cleanIntProvider(): array
    {
        return [['42'], ['0'], ['-7'], [100], [0], [3.0], ['2500']];
    }

    #[DataProvider('cleanFloatProvider')]
    public function testSFloatMatchesNativeFloatval(int|string $input): void
    {
        self::assertSame((float) $input, s_float($input));
    }

    /** @return list<array{int|string}> */
    public static function cleanFloatProvider(): array
    {
        return [['3.14'], ['0.0'], ['-2.5'], [7], ['100'], ['0']];
    }

    #[DataProvider('cleanStrProvider')]
    public function testSStrMatchesNativeStrval(int|float|string $input): void
    {
        self::assertSame((string) $input, s_str($input));
    }

    /** @return list<array{int|float|string}> */
    public static function cleanStrProvider(): array
    {
        return [['hello'], [42], [3.14], [''], [-99], ['already a string']];
    }

    // -------------------------------------------------------------------------
    // Regex
    // -------------------------------------------------------------------------

    public function testSMatchMatchesNativePregMatch(): void
    {
        $pattern = '/\d+/';
        $subject = 'abc123def';
        self::assertSame(preg_match($pattern, $subject) === 1, s_match($pattern, $subject));
    }

    public function testSReplaceMatchesNativePregReplace(): void
    {
        $pattern = '/\s+/';
        $subject = 'a b  c   d';
        self::assertSame(
            preg_replace($pattern, '-', $subject),
            s_replace($pattern, '-', $subject),
        );
    }

    // -------------------------------------------------------------------------
    // Encoding
    // -------------------------------------------------------------------------

    public function testSB64MatchesNativeDecode(): void
    {
        $encoded = base64_encode("CorePHP ✓ binary\x00\xff\x10");
        self::assertSame(base64_decode($encoded, true), s_b64($encoded));
    }
}
