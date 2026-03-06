<?php

declare(strict_types=1);

namespace core\Tests;

use PHPUnit\Framework\TestCase;
use core\Internal\Array\TypedCollection;
use core\Vec;

/**
 * @covers \core\Vec
 * @uses   \core\Internal\Array\TypedCollection
 */
final class VecTest extends TestCase
{
    public function testVecIsInstanceOfTypedCollection(): void
    {
        $vec = new Vec('int');
        self::assertInstanceOf(TypedCollection::class, $vec);
    }

    public function testVecFromArrayReturnsVecInstance(): void
    {
        $vec = Vec::fromArray('int', [1, 2, 3]);
        self::assertInstanceOf(Vec::class, $vec);
    }

    public function testVecFromIterableReturnsVecInstance(): void
    {
        $gen = static function (): \Generator {
            yield 1;
            yield 2;
        };
        $vec = Vec::fromIterable('int', $gen());
        self::assertInstanceOf(Vec::class, $vec);
    }

    public function testVecMergeReturnsVecInstance(): void
    {
        $a = Vec::fromArray('int', [1, 2]);
        $b = Vec::fromArray('int', [3, 4]);
        $c = $a->merge($b);
        self::assertInstanceOf(Vec::class, $c);
        self::assertSame([1, 2, 3, 4], $c->toArray());
    }

    public function testVecFilterReturnsVecInstance(): void
    {
        $vec      = Vec::fromArray('int', [1, 2, 3, 4]);
        $filtered = $vec->filter(static fn(int $n): bool => $n > 2);
        self::assertInstanceOf(Vec::class, $filtered);
        self::assertSame([3, 4], $filtered->toArray());
    }

    public function testVecMapReturnsVecInstance(): void
    {
        $vec    = Vec::fromArray('int', [1, 2]);
        $mapped = $vec->map(static fn(int $n): int => $n * 10, 'int');
        self::assertInstanceOf(Vec::class, $mapped);
        self::assertSame([10, 20], $mapped->toArray());
    }

    public function testVecAdd(): void
    {
        $vec = new Vec('string');
        $vec->add('hello');
        $vec->add('world');
        self::assertSame(2, count($vec));
        self::assertSame('hello', $vec->first());
        self::assertSame('world', $vec->last());
    }

    public function testVecArrayAccess(): void
    {
        $vec   = new Vec('int');
        $vec[] = 10;
        $vec[] = 20;
        self::assertSame(10, $vec[0]);
        self::assertSame(20, $vec[1]);
    }

    public function testVecForeach(): void
    {
        $vec    = Vec::fromArray('string', ['a', 'b', 'c']);
        $result = [];
        foreach ($vec as $item) {
            $result[] = $item;
        }
        self::assertSame(['a', 'b', 'c'], $result);
    }

    public function testVecToArrayRoundTrip(): void
    {
        $original = [1, 2, 3];
        $vec      = Vec::fromArray('int', $original);
        self::assertSame($original, $vec->toArray());
    }

    public function testVecContains(): void
    {
        $vec = Vec::fromArray('int', [10, 20, 30]);
        self::assertTrue($vec->contains(20));
        self::assertFalse($vec->contains(99));
    }

    public function testVecIsEmpty(): void
    {
        self::assertTrue((new Vec('int'))->isEmpty());
        self::assertFalse(Vec::fromArray('int', [1])->isEmpty());
    }
}
