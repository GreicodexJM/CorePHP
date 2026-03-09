<?php

declare(strict_types=1);

namespace core\Tests;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;
use Psl\File\Exception\NotFoundException as FileNotFoundException;
use Psl\File\Exception\RuntimeException as FileException;
use Psl\Json\Exception\DecodeException as JsonDecodeException;
use Psl\Json\Exception\EncodeException as JsonEncodeException;
use Psl\Type\Exception\CoercionException;

/** Tests for all global PSL-backed function shims defined in functions.php. */
#[CoversFunction('s_json')]
#[CoversFunction('s_enc')]
#[CoversFunction('s_int')]
#[CoversFunction('s_float')]
#[CoversFunction('s_str')]
#[CoversFunction('s_bool')]
#[CoversFunction('s_file')]
#[CoversFunction('s_write')]
#[CoversFunction('s_append')]
#[CoversFunction('s_fwrite')]
#[CoversFunction('s_match')]
#[CoversFunction('s_regex')]
#[CoversFunction('s_regex_all')]
#[CoversFunction('s_env')]
#[CoversFunction('s_env_or')]
#[CoversFunction('arr_to_list')]
#[CoversFunction('list_to_arr')]
#[CoversFunction('arr_to_dict')]
#[CoversFunction('dict_to_arr')]
#[CoversFunction('vec_filter')]
#[CoversFunction('vec_map')]
#[CoversFunction('dict_filter')]
#[CoversFunction('dict_map')]
#[CoversFunction('dict_merge')]
final class FunctionShimsTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir() . '/corephp_shims_test_' . uniqid() . '.txt';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    // =========================================================================
    // s_json() — Psl\Json\decode
    // =========================================================================

    public function testSJsonDecodesValidJson(): void
    {
        $result = s_json('{"name":"Alice","age":30}');
        self::assertSame(['name' => 'Alice', 'age' => 30], $result);
    }

    public function testSJsonReturnsObjectWhenAssocFalse(): void
    {
        $result = s_json('{"name":"Alice"}', false);
        self::assertInstanceOf(\stdClass::class, $result);
        self::assertSame('Alice', $result->name);
    }

    public function testSJsonDecodesArray(): void
    {
        $result = s_json('[1,2,3]');
        self::assertSame([1, 2, 3], $result);
    }

    public function testSJsonThrowsOnInvalidJson(): void
    {
        $this->expectException(JsonDecodeException::class);
        s_json('{not valid json}');
    }

    public function testSJsonThrowsOnEmptyString(): void
    {
        $this->expectException(JsonDecodeException::class);
        s_json('');
    }

    // =========================================================================
    // s_enc() — Psl\Json\encode
    // =========================================================================

    public function testSEncEncodesArray(): void
    {
        $result = s_enc(['name' => 'Bob']);
        self::assertSame('{"name":"Bob"}', $result);
    }

    public function testSEncWithPrettyPrint(): void
    {
        $result = s_enc(['a' => 1], true);
        self::assertStringContainsString("\n", $result);
    }

    public function testSEncThrowsOnUnencodableValue(): void
    {
        $this->expectException(JsonEncodeException::class);
        s_enc(['value' => INF]);
    }

    // =========================================================================
    // s_int() — Psl\Type\int()->coerce()
    // =========================================================================

    public function testSIntConvertsNumericString(): void
    {
        self::assertSame(42, s_int('42'));
    }

    public function testSIntConvertsInt(): void
    {
        self::assertSame(7, s_int(7));
    }

    public function testSIntConvertsWholeFloat(): void
    {
        // PSL 4.x int()->coerce() only accepts whole floats (e.g. 3.0 → 3).
        // Non-whole floats like 3.9 throw CoercionException.
        self::assertSame(3, s_int(3.0));
    }

    public function testSIntThrowsOnNonNumericString(): void
    {
        $this->expectException(CoercionException::class);
        s_int('not-a-number');
    }

    public function testSIntThrowsOnNull(): void
    {
        $this->expectException(CoercionException::class);
        s_int(null);
    }

    public function testSIntThrowsOnArray(): void
    {
        $this->expectException(CoercionException::class);
        s_int([]);
    }

    // =========================================================================
    // s_float() — Psl\Type\float()->coerce()
    // =========================================================================

    public function testSFloatConvertsNumericString(): void
    {
        self::assertSame(3.14, s_float('3.14'));
    }

    public function testSFloatConvertsInt(): void
    {
        self::assertSame(5.0, s_float(5));
    }

    public function testSFloatThrowsOnNonNumericString(): void
    {
        $this->expectException(CoercionException::class);
        s_float('abc');
    }

    // =========================================================================
    // s_str() — Psl\Type\string()->coerce()
    // =========================================================================

    public function testSStrReturnsString(): void
    {
        self::assertSame('hello', s_str('hello'));
    }

    public function testSStrConvertsInt(): void
    {
        self::assertSame('42', s_str(42));
    }

    public function testSStrThrowsOnNull(): void
    {
        $this->expectException(CoercionException::class);
        s_str(null);
    }

    public function testSStrThrowsOnArray(): void
    {
        $this->expectException(CoercionException::class);
        s_str([]);
    }

    // =========================================================================
    // s_bool() — Psl\Type\bool()->coerce()
    // =========================================================================

    public function testSBoolReturnsTrueForTrue(): void
    {
        self::assertTrue(s_bool(true));
    }

    public function testSBoolReturnsFalseForFalse(): void
    {
        self::assertFalse(s_bool(false));
    }

    public function testSBoolCoercesOneToTrue(): void
    {
        self::assertTrue(s_bool(1));
    }

    public function testSBoolCoercesZeroToFalse(): void
    {
        self::assertFalse(s_bool(0));
    }

    // =========================================================================
    // s_file() — Psl\File\read()
    // =========================================================================

    public function testSFileReturnsFileContents(): void
    {
        file_put_contents($this->tmpFile, 'hello world');
        self::assertSame('hello world', s_file($this->tmpFile));
    }

    public function testSFileThrowsOnMissingFile(): void
    {
        // PSL 4.x throws NotFoundException (extends InvalidArgumentException) for missing files.
        $this->expectException(FileNotFoundException::class);
        s_file('/nonexistent/path/file.txt');
    }

    // =========================================================================
    // s_write() — Psl\File\write()
    // =========================================================================

    public function testSWriteCreatesFileWithContent(): void
    {
        $bytes = s_write($this->tmpFile, 'test content');
        self::assertSame(strlen('test content'), $bytes);
        self::assertSame('test content', file_get_contents($this->tmpFile));
    }

    public function testSWriteAppendsWhenFlagSet(): void
    {
        s_write($this->tmpFile, 'first');
        s_write($this->tmpFile, ' second', true);
        self::assertSame('first second', file_get_contents($this->tmpFile));
    }

    public function testSWriteThrowsOnUnwritablePath(): void
    {
        $this->expectException(FileException::class);
        s_write('/dev/null/cannot_write_here.txt', 'data');
    }

    // =========================================================================
    // s_append()
    // =========================================================================

    public function testSAppendAppendsToFile(): void
    {
        s_write($this->tmpFile, 'base');
        $bytes = s_append($this->tmpFile, '_appended');
        self::assertSame(strlen('_appended'), $bytes);
        self::assertSame('base_appended', file_get_contents($this->tmpFile));
    }

    // =========================================================================
    // s_fwrite()
    // =========================================================================

    public function testSFwriteWritesToHandle(): void
    {
        $handle = fopen($this->tmpFile, 'w');
        self::assertNotFalse($handle);
        $bytes = s_fwrite($handle, 'written via handle');
        fclose($handle);
        self::assertSame(strlen('written via handle'), $bytes);
        self::assertSame('written via handle', file_get_contents($this->tmpFile));
    }

    public function testSFwriteThrowsOnNonResource(): void
    {
        $this->expectException(FileException::class);
        s_fwrite('not-a-handle', 'data');
    }

    // =========================================================================
    // s_match() — Psl\Regex\matches()
    // =========================================================================

    public function testSMatchReturnsTrueOnMatch(): void
    {
        self::assertTrue(s_match('/^\d+$/', '12345'));
    }

    public function testSMatchReturnsFalseOnNoMatch(): void
    {
        self::assertFalse(s_match('/^\d+$/', 'abc'));
    }

    // =========================================================================
    // s_regex() — Psl\Regex\first_match()
    // =========================================================================

    public function testSRegexReturnsMatchGroups(): void
    {
        $result = s_regex('/(\d+)-(\d+)/', 'range: 10-99');
        self::assertIsArray($result);
        self::assertArrayHasKey(1, $result);
        self::assertSame('10', $result[1]);
        self::assertSame('99', $result[2]);
    }

    public function testSRegexReturnsNullOnNoMatch(): void
    {
        $result = s_regex('/^\d+$/', 'no numbers here');
        self::assertNull($result);
    }

    // =========================================================================
    // s_regex_all() — Psl\Regex\every_match()
    // =========================================================================

    public function testSRegexAllReturnsAllMatches(): void
    {
        $matches = s_regex_all('/\d+/', 'a1 b22 c333');
        self::assertCount(3, $matches);
    }

    public function testSRegexAllReturnsEmptyArrayOnNoMatch(): void
    {
        $matches = s_regex_all('/\d+/', 'no digits here');
        self::assertSame([], $matches);
    }

    // =========================================================================
    // s_env() / s_env_or()
    // =========================================================================

    public function testSEnvReturnsSetVariable(): void
    {
        putenv('COREPHP_TEST_VAR=hello_test');
        self::assertSame('hello_test', s_env('COREPHP_TEST_VAR'));
        putenv('COREPHP_TEST_VAR');
    }

    public function testSEnvThrowsOnUnsetVariable(): void
    {
        putenv('COREPHP_UNSET_VAR');
        $this->expectException(\RuntimeException::class);
        s_env('COREPHP_UNSET_VAR');
    }

    public function testSEnvOrReturnsDefaultOnUnsetVariable(): void
    {
        putenv('COREPHP_UNSET_VAR');
        self::assertSame('fallback', s_env_or('COREPHP_UNSET_VAR', 'fallback'));
    }

    public function testSEnvOrReturnsValueWhenSet(): void
    {
        putenv('COREPHP_TEST_VAR=value');
        self::assertSame('value', s_env_or('COREPHP_TEST_VAR', 'default'));
        putenv('COREPHP_TEST_VAR');
    }

    // =========================================================================
    // arr_to_list() / list_to_arr()
    // =========================================================================

    public function testArrToListCreatesTypedVec(): void
    {
        $vec = arr_to_list('int', [1, 2, 3]);
        self::assertInstanceOf(\core\Vec::class, $vec);
        self::assertSame([1, 2, 3], $vec->toArray());
    }

    public function testListToArrConvertsToPlainArray(): void
    {
        $vec = \core\Vec::fromArray('string', ['a', 'b']);
        self::assertSame(['a', 'b'], list_to_arr($vec));
    }

    // =========================================================================
    // arr_to_dict() / dict_to_arr()
    // =========================================================================

    public function testArrToDictCreatesTypedDict(): void
    {
        $dict = arr_to_dict('string', ['host' => 'localhost', 'db' => 'app']);
        self::assertInstanceOf(\core\Dict::class, $dict);
        self::assertSame('localhost', $dict->get('host'));
    }

    public function testDictToArrConvertsToPlainArray(): void
    {
        $dict = \core\Dict::fromArray('string', ['a' => 'A', 'b' => 'B']);
        self::assertSame(['a' => 'A', 'b' => 'B'], dict_to_arr($dict));
    }

    // =========================================================================
    // vec_filter() / vec_map()
    // =========================================================================

    public function testVecFilterFiltersElements(): void
    {
        $result = vec_filter([1, 2, 3, 4, 5], fn (int $n) => $n > 3);
        self::assertSame([4, 5], $result);
    }

    public function testVecMapTransformsElements(): void
    {
        $result = vec_map([1, 2, 3], fn (int $n) => $n * 10);
        self::assertSame([10, 20, 30], $result);
    }

    // =========================================================================
    // dict_filter() / dict_map() / dict_merge()
    // =========================================================================

    public function testDictFilterFiltersValues(): void
    {
        $result = dict_filter(['a' => 1, 'b' => 5, 'c' => 2], fn (int $v) => $v > 2);
        self::assertSame(['b' => 5], $result);
    }

    public function testDictMapTransformsValues(): void
    {
        $result = dict_map(['a' => 1, 'b' => 2], fn (int $v) => $v * 100);
        self::assertSame(['a' => 100, 'b' => 200], $result);
    }

    public function testDictMergeWithTwoArrays(): void
    {
        $result = dict_merge(['a' => 1, 'b' => 2], ['b' => 99, 'c' => 3]);
        self::assertSame(['a' => 1, 'b' => 99, 'c' => 3], $result);
    }

    public function testDictMergeWithThreeArrays(): void
    {
        $result = dict_merge(['a' => 1], ['b' => 2], ['c' => 3]);
        self::assertSame(['a' => 1, 'b' => 2, 'c' => 3], $result);
    }

    public function testDictMergeWithNoArgsReturnsEmptyArray(): void
    {
        self::assertSame([], dict_merge());
    }
}
