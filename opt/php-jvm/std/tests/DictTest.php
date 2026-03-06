<?php

declare(strict_types=1);

namespace std\Tests;

use PHPUnit\Framework\TestCase;
use std\Dict;

/**
 * @covers \std\Dict
 */
final class DictTest extends TestCase
{
    // =========================================================================
    // Constructor
    // =========================================================================

    public function testConstructorAcceptsNonEmptyType(): void
    {
        $dict = new Dict('string');
        self::assertSame('string', $dict->getType());
    }

    public function testConstructorRejectsEmptyType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Dict('');
    }

    // =========================================================================
    // set() / get()
    // =========================================================================

    public function testSetAndGetString(): void
    {
        $dict = new Dict('string');
        $dict->set('host', 'localhost');
        self::assertSame('localhost', $dict->get('host'));
    }

    public function testSetRejectsWrongType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $dict = new Dict('string');
        $dict->set('port', 3306);
    }

    public function testGetThrowsOnMissingKey(): void
    {
        $this->expectException(\OutOfBoundsException::class);
        $dict = new Dict('string');
        $dict->get('missing');
    }

    public function testGetThrowsMessageContainsAvailableKeys(): void
    {
        $dict = Dict::fromArray('string', ['a' => 'alpha', 'b' => 'beta']);
        try {
            $dict->get('missing');
            self::fail('Expected OutOfBoundsException');
        } catch (\OutOfBoundsException $e) {
            self::assertStringContainsString('a', $e->getMessage());
            self::assertStringContainsString('b', $e->getMessage());
        }
    }

    // =========================================================================
    // getOrDefault()
    // =========================================================================

    public function testGetOrDefaultReturnsPresentValue(): void
    {
        $dict = Dict::fromArray('string', ['key' => 'value']);
        self::assertSame('value', $dict->getOrDefault('key', 'fallback'));
    }

    public function testGetOrDefaultReturnsFallbackForMissingKey(): void
    {
        $dict = new Dict('string');
        self::assertSame('fallback', $dict->getOrDefault('missing', 'fallback'));
    }

    // =========================================================================
    // has() / remove()
    // =========================================================================

    public function testHasReturnsTrueForExistingKey(): void
    {
        $dict = Dict::fromArray('int', ['a' => 1]);
        self::assertTrue($dict->has('a'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        self::assertFalse((new Dict('int'))->has('x'));
    }

    public function testRemoveDeletesKey(): void
    {
        $dict = Dict::fromArray('string', ['a' => 'alpha', 'b' => 'beta']);
        $dict->remove('a');
        self::assertFalse($dict->has('a'));
        self::assertTrue($dict->has('b'));
    }

    // =========================================================================
    // keys() / values()
    // =========================================================================

    public function testKeysReturnsAllKeys(): void
    {
        $dict = Dict::fromArray('string', ['x' => 'X', 'y' => 'Y']);
        self::assertSame(['x', 'y'], $dict->keys());
    }

    public function testValuesReturnsAllValues(): void
    {
        $dict = Dict::fromArray('string', ['x' => 'X', 'y' => 'Y']);
        self::assertSame(['X', 'Y'], $dict->values());
    }

    // =========================================================================
    // isEmpty() / isNotEmpty()
    // =========================================================================

    public function testIsEmptyOnNewDict(): void
    {
        self::assertTrue((new Dict('string'))->isEmpty());
    }

    public function testIsNotEmptyAfterSet(): void
    {
        $dict = new Dict('string');
        $dict->set('k', 'v');
        self::assertTrue($dict->isNotEmpty());
    }

    // =========================================================================
    // fromArray() / fromObject()
    // =========================================================================

    public function testFromArrayCreatesDict(): void
    {
        $dict = Dict::fromArray('string', ['a' => 'alpha', 'b' => 'beta']);
        self::assertSame(2, count($dict));
        self::assertSame('alpha', $dict->get('a'));
    }

    public function testFromArrayCastsNonStringKeys(): void
    {
        $dict = Dict::fromArray('string', [0 => 'zero', 1 => 'one']);
        self::assertTrue($dict->has('0'));
        self::assertTrue($dict->has('1'));
    }

    public function testFromArrayThrowsOnTypeMismatch(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Dict::fromArray('string', ['a' => 'ok', 'b' => 42]);
    }

    public function testFromObjectCreatesDict(): void
    {
        $obj    = json_decode('{"host":"localhost","port":"3306"}');
        $dict   = Dict::fromObject('string', $obj);
        self::assertSame('localhost', $dict->get('host'));
        self::assertSame('3306', $dict->get('port'));
    }

    // =========================================================================
    // merge()
    // =========================================================================

    public function testMergeCombinesTwoDicts(): void
    {
        $a = Dict::fromArray('string', ['x' => 'X']);
        $b = Dict::fromArray('string', ['y' => 'Y']);
        $c = $a->merge($b);
        self::assertSame('X', $c->get('x'));
        self::assertSame('Y', $c->get('y'));
    }

    public function testMergeLaterValuesWinOnCollision(): void
    {
        $a = Dict::fromArray('string', ['k' => 'original']);
        $b = Dict::fromArray('string', ['k' => 'updated']);
        $c = $a->merge($b);
        self::assertSame('updated', $c->get('k'));
    }

    public function testMergeDoesNotMutateOriginals(): void
    {
        $a = Dict::fromArray('string', ['x' => 'X']);
        $b = Dict::fromArray('string', ['y' => 'Y']);
        $a->merge($b);
        self::assertFalse($a->has('y'));
        self::assertFalse($b->has('x'));
    }

    public function testMergeThrowsOnTypeMismatch(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $a = Dict::fromArray('string', ['a' => 'alpha']);
        $b = Dict::fromArray('int', ['b' => 1]);
        $a->merge($b);
    }

    // =========================================================================
    // only() / except()
    // =========================================================================

    public function testOnlyReturnsSubsetOfKeys(): void
    {
        $dict   = Dict::fromArray('string', ['a' => 'A', 'b' => 'B', 'c' => 'C']);
        $subset = $dict->only('a', 'c');
        self::assertSame(['a', 'c'], $subset->keys());
        self::assertFalse($subset->has('b'));
    }

    public function testOnlySilentlySkipsMissingKeys(): void
    {
        $dict   = Dict::fromArray('string', ['a' => 'A']);
        $subset = $dict->only('a', 'nonexistent');
        self::assertSame(['a'], $subset->keys());
    }

    public function testExceptReturnsAllExceptSpecified(): void
    {
        $dict     = Dict::fromArray('string', ['a' => 'A', 'b' => 'B', 'c' => 'C']);
        $filtered = $dict->except('b');
        self::assertTrue($filtered->has('a'));
        self::assertFalse($filtered->has('b'));
        self::assertTrue($filtered->has('c'));
    }

    // =========================================================================
    // ArrayAccess
    // =========================================================================

    public function testArrayAccessSet(): void
    {
        $dict         = new Dict('string');
        $dict['host'] = 'localhost';
        self::assertSame('localhost', $dict['host']);
    }

    public function testArrayAccessUnset(): void
    {
        $dict = Dict::fromArray('string', ['a' => 'A', 'b' => 'B']);
        unset($dict['a']);
        self::assertFalse($dict->has('a'));
    }

    public function testArrayAccessOffsetExists(): void
    {
        $dict = Dict::fromArray('string', ['a' => 'A']);
        self::assertTrue(isset($dict['a']));
        self::assertFalse(isset($dict['z']));
    }

    // =========================================================================
    // IteratorAggregate (foreach)
    // =========================================================================

    public function testForeachIteratesKeyValuePairs(): void
    {
        $dict   = Dict::fromArray('string', ['a' => 'A', 'b' => 'B']);
        $result = [];
        foreach ($dict as $key => $value) {
            $result[$key] = $value;
        }
        self::assertSame(['a' => 'A', 'b' => 'B'], $result);
    }

    // =========================================================================
    // Countable
    // =========================================================================

    public function testCountReturnsNumberOfEntries(): void
    {
        $dict = Dict::fromArray('string', ['a' => 'A', 'b' => 'B', 'c' => 'C']);
        self::assertSame(3, count($dict));
    }

    // =========================================================================
    // toArray()
    // =========================================================================

    public function testToArrayReturnsAssocArray(): void
    {
        $data = ['x' => 'X', 'y' => 'Y'];
        $dict = Dict::fromArray('string', $data);
        self::assertSame($data, $dict->toArray());
    }
}
