<?php

declare(strict_types=1);

namespace core\Tests\Internal\Array;

use PHPUnit\Framework\TestCase;
use core\Internal\Array\TypedCollection;

/**
 * @covers \core\Internal\Array\TypedCollection
 */
final class TypedCollectionTest extends TestCase
{
    // =========================================================================
    // Constructor
    // =========================================================================

    public function testConstructorAcceptsNonEmptyType(): void
    {
        $col = new TypedCollection('string');
        self::assertSame('string', $col->getType());
    }

    public function testConstructorRejectsEmptyType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TypedCollection('');
    }

    // =========================================================================
    // add() — primitive types
    // =========================================================================

    public function testAddAcceptsCorrectPrimitive(): void
    {
        $col = new TypedCollection('int');
        $col->add(1);
        $col->add(2);
        self::assertSame(2, count($col));
    }

    public function testAddRejectsWrongPrimitive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $col = new TypedCollection('int');
        $col->add('not-an-int');
    }

    public function testAddAcceptsStringPrimitive(): void
    {
        $col = new TypedCollection('string');
        $col->add('hello');
        self::assertSame(1, count($col));
    }

    public function testAddAcceptsFloatPrimitive(): void
    {
        $col = new TypedCollection('float');
        $col->add(3.14);
        self::assertSame(1, count($col));
    }

    public function testAddAcceptsBoolPrimitive(): void
    {
        $col = new TypedCollection('bool');
        $col->add(true);
        $col->add(false);
        self::assertSame(2, count($col));
    }

    // =========================================================================
    // add() — class types
    // =========================================================================

    public function testAddAcceptsObjectOfDeclaredClass(): void
    {
        $col = new TypedCollection(\stdClass::class);
        $col->add(new \stdClass());
        self::assertSame(1, count($col));
    }

    public function testAddRejectsObjectOfWrongClass(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $col = new TypedCollection(\stdClass::class);
        $col->add(new \DateTime());
    }

    // =========================================================================
    // Static factories
    // =========================================================================

    public function testFromArrayCreatesCollectionWithAllElements(): void
    {
        $col = TypedCollection::fromArray('int', [10, 20, 30]);
        self::assertSame(3, count($col));
        self::assertSame(10, $col->get(0));
        self::assertSame(30, $col->get(2));
    }

    public function testFromArrayThrowsOnTypeMismatch(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TypedCollection::fromArray('int', [1, 2, 'three']);
    }

    public function testFromIterableAcceptsGenerator(): void
    {
        $gen = static function (): \Generator {
            yield 'a';
            yield 'b';
            yield 'c';
        };

        $col = TypedCollection::fromIterable('string', $gen());
        self::assertSame(3, count($col));
        self::assertSame('a', $col->get(0));
    }

    public function testFromIterableAcceptsAnotherCollection(): void
    {
        $source = TypedCollection::fromArray('int', [1, 2, 3]);
        $copy   = TypedCollection::fromIterable('int', $source);
        self::assertSame($source->toArray(), $copy->toArray());
    }

    // =========================================================================
    // merge()
    // =========================================================================

    public function testMergeReturnsCombinedCollection(): void
    {
        $a = TypedCollection::fromArray('int', [1, 2]);
        $b = TypedCollection::fromArray('int', [3, 4]);
        $c = $a->merge($b);
        self::assertSame([1, 2, 3, 4], $c->toArray());
    }

    public function testMergeDoesNotMutateOriginals(): void
    {
        $a = TypedCollection::fromArray('int', [1, 2]);
        $b = TypedCollection::fromArray('int', [3, 4]);
        $a->merge($b);
        self::assertSame(2, count($a));
        self::assertSame(2, count($b));
    }

    public function testMergeThrowsOnTypeMismatch(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $a = TypedCollection::fromArray('int', [1]);
        $b = TypedCollection::fromArray('string', ['x']);
        $a->merge($b);
    }

    // =========================================================================
    // first() / last() / get()
    // =========================================================================

    public function testFirstReturnsFirstElement(): void
    {
        $col = TypedCollection::fromArray('int', [10, 20, 30]);
        self::assertSame(10, $col->first());
    }

    public function testFirstThrowsOnEmpty(): void
    {
        $this->expectException(\OutOfBoundsException::class);
        (new TypedCollection('int'))->first();
    }

    public function testLastReturnsLastElement(): void
    {
        $col = TypedCollection::fromArray('int', [10, 20, 30]);
        self::assertSame(30, $col->last());
    }

    public function testLastThrowsOnEmpty(): void
    {
        $this->expectException(\OutOfBoundsException::class);
        (new TypedCollection('int'))->last();
    }

    public function testGetReturnsElementByIndex(): void
    {
        $col = TypedCollection::fromArray('string', ['a', 'b', 'c']);
        self::assertSame('b', $col->get(1));
    }

    public function testGetThrowsOnOutOfRange(): void
    {
        $this->expectException(\OutOfBoundsException::class);
        TypedCollection::fromArray('int', [1])->get(5);
    }

    // =========================================================================
    // contains() / isEmpty() / isNotEmpty()
    // =========================================================================

    public function testContainsReturnsTrueForPresentItem(): void
    {
        $col = TypedCollection::fromArray('int', [1, 2, 3]);
        self::assertTrue($col->contains(2));
    }

    public function testContainsReturnsFalseForAbsentItem(): void
    {
        $col = TypedCollection::fromArray('int', [1, 2, 3]);
        self::assertFalse($col->contains(99));
    }

    public function testIsEmptyReturnsTrueForEmpty(): void
    {
        self::assertTrue((new TypedCollection('int'))->isEmpty());
    }

    public function testIsEmptyReturnsFalseForNonEmpty(): void
    {
        $col = TypedCollection::fromArray('int', [1]);
        self::assertFalse($col->isEmpty());
    }

    public function testIsNotEmptyReturnsTrueForNonEmpty(): void
    {
        self::assertTrue(TypedCollection::fromArray('int', [1])->isNotEmpty());
    }

    // =========================================================================
    // filter() / map() / reduce() / each()
    // =========================================================================

    public function testFilterReturnsMatchingItems(): void
    {
        $col      = TypedCollection::fromArray('int', [1, 2, 3, 4, 5]);
        $evens    = $col->filter(static fn(int $n): bool => $n % 2 === 0);
        self::assertSame([2, 4], $evens->toArray());
    }

    public function testMapTransformsItems(): void
    {
        $col     = TypedCollection::fromArray('int', [1, 2, 3]);
        $doubled = $col->map(static fn(int $n): int => $n * 2, 'int');
        self::assertSame([2, 4, 6], $doubled->toArray());
    }

    public function testReduceAccumulatesValue(): void
    {
        $col = TypedCollection::fromArray('int', [1, 2, 3, 4]);
        $sum = $col->reduce(static fn(int $carry, int $item): int => $carry + $item, 0);
        self::assertSame(10, $sum);
    }

    public function testEachIteratesAllItems(): void
    {
        $col     = TypedCollection::fromArray('int', [10, 20, 30]);
        $visited = [];
        $col->each(static function (int $item, int $index) use (&$visited): void {
            $visited[$index] = $item;
        });
        self::assertSame([10, 20, 30], $visited);
    }

    // =========================================================================
    // ArrayAccess
    // =========================================================================

    public function testArrayAccessOffsetSet(): void
    {
        $col    = new TypedCollection('int');
        $col[]  = 42;
        self::assertSame(42, $col[0]);
    }

    public function testArrayAccessOffsetUnsetReindexes(): void
    {
        $col = TypedCollection::fromArray('int', [1, 2, 3]);
        unset($col[1]);
        self::assertSame([1, 3], $col->toArray());
    }

    public function testArrayAccessOffsetGetThrowsOnMissing(): void
    {
        $this->expectException(\OutOfBoundsException::class);
        $col = new TypedCollection('int');
        $_ = $col[0];
    }

    // =========================================================================
    // Iterator
    // =========================================================================

    public function testForeachIteratesAllItems(): void
    {
        $col    = TypedCollection::fromArray('int', [1, 2, 3]);
        $result = [];
        foreach ($col as $k => $v) {
            $result[$k] = $v;
        }
        self::assertSame([1, 2, 3], $result);
    }

    // =========================================================================
    // toArray()
    // =========================================================================

    public function testToArrayReturnsPlainArray(): void
    {
        $col = TypedCollection::fromArray('string', ['a', 'b']);
        self::assertSame(['a', 'b'], $col->toArray());
    }

    // =========================================================================
    // PSL-backed: reverse()
    // =========================================================================

    public function testReverseReturnsReversedCollection(): void
    {
        $col      = TypedCollection::fromArray('int', [1, 2, 3]);
        $reversed = $col->reverse();
        self::assertSame([3, 2, 1], $reversed->toArray());
    }

    public function testReverseDoesNotMutateOriginal(): void
    {
        $col = TypedCollection::fromArray('int', [1, 2, 3]);
        $col->reverse();
        self::assertSame([1, 2, 3], $col->toArray());
    }

    public function testReverseReturnsSameType(): void
    {
        $col = TypedCollection::fromArray('int', [1, 2, 3]);
        self::assertInstanceOf(TypedCollection::class, $col->reverse());
    }

    // =========================================================================
    // PSL-backed: slice()
    // =========================================================================

    public function testSliceReturnsSubset(): void
    {
        $col   = TypedCollection::fromArray('int', [10, 20, 30, 40, 50]);
        $slice = $col->slice(1, 3);
        self::assertSame([20, 30, 40], $slice->toArray());
    }

    public function testSliceToEndWhenLengthNull(): void
    {
        $col   = TypedCollection::fromArray('int', [1, 2, 3, 4]);
        $slice = $col->slice(2);
        self::assertSame([3, 4], $slice->toArray());
    }

    public function testSliceDoesNotMutateOriginal(): void
    {
        $col = TypedCollection::fromArray('int', [1, 2, 3, 4]);
        $col->slice(0, 2);
        self::assertSame(4, count($col));
    }

    // =========================================================================
    // PSL-backed: chunk()
    // =========================================================================

    public function testChunkSplitsCollection(): void
    {
        $col    = TypedCollection::fromArray('int', [1, 2, 3, 4, 5]);
        $chunks = $col->chunk(2);
        self::assertCount(3, $chunks);
        self::assertSame([1, 2], $chunks[0]->toArray());
        self::assertSame([3, 4], $chunks[1]->toArray());
        self::assertSame([5],    $chunks[2]->toArray());
    }

    public function testChunkReturnsTypedCollections(): void
    {
        $col    = TypedCollection::fromArray('string', ['a', 'b', 'c']);
        $chunks = $col->chunk(2);
        self::assertInstanceOf(TypedCollection::class, $chunks[0]);
    }

    public function testChunkThrowsOnSizeZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TypedCollection::fromArray('int', [1])->chunk(0);
    }

    // =========================================================================
    // PSL-backed: sort()
    // =========================================================================

    public function testSortReturnsSortedCollection(): void
    {
        $col    = TypedCollection::fromArray('int', [3, 1, 4, 1, 5, 9, 2, 6]);
        $sorted = $col->sort(fn(int $a, int $b) => $a <=> $b);
        self::assertSame([1, 1, 2, 3, 4, 5, 6, 9], $sorted->toArray());
    }

    public function testSortDescending(): void
    {
        $col    = TypedCollection::fromArray('int', [3, 1, 2]);
        $sorted = $col->sort(fn(int $a, int $b) => $b <=> $a);
        self::assertSame([3, 2, 1], $sorted->toArray());
    }

    public function testSortDoesNotMutateOriginal(): void
    {
        $col = TypedCollection::fromArray('int', [3, 1, 2]);
        $col->sort(fn($a, $b) => $a <=> $b);
        self::assertSame([3, 1, 2], $col->toArray());
    }

    // =========================================================================
    // PSL-backed: unique()
    // =========================================================================

    public function testUniqueRemovesDuplicates(): void
    {
        $col    = TypedCollection::fromArray('int', [1, 2, 2, 3, 3, 3]);
        $unique = $col->unique();
        self::assertSame([1, 2, 3], $unique->toArray());
    }

    public function testUniqueDoesNotMutateOriginal(): void
    {
        $col = TypedCollection::fromArray('int', [1, 1, 2]);
        $col->unique();
        self::assertSame(3, count($col));
    }
}
