<?php

declare(strict_types=1);

namespace std\Tests;

use PHPUnit\Framework\TestCase;
use std\Any;
use std\StrictObject;

/**
 * @covers \std\Any
 * @uses   \std\StrictObject
 */
final class AnyTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Stub: Any is abstract
    // -------------------------------------------------------------------------

    private function makeAny(): Any
    {
        return new class extends Any {
            public string $name = 'test';
        };
    }

    // =========================================================================
    // Inheritance
    // =========================================================================

    public function testAnyExtendsStrictObject(): void
    {
        $obj = $this->makeAny();
        self::assertInstanceOf(StrictObject::class, $obj);
    }

    public function testAnyExtendsStrictObjectInheritedGuards(): void
    {
        $this->expectException(\RuntimeException::class);
        $obj = $this->makeAny();
        $_ = $obj->undeclaredProp;
    }

    // =========================================================================
    // __toString
    // =========================================================================

    public function testToStringContainsClassName(): void
    {
        $obj = $this->makeAny();
        $str = (string) $obj;
        // Class is an anonymous class; the string must contain '@' separator
        self::assertStringContainsString('@', $str);
    }

    public function testToStringContainsObjectId(): void
    {
        $obj = $this->makeAny();
        $id  = spl_object_id($obj);
        self::assertStringContainsString((string) $id, (string) $obj);
    }

    public function testTwoDistinctObjectsHaveDifferentStrings(): void
    {
        $a = $this->makeAny();
        $b = $this->makeAny();
        // Different objects → different IDs → different strings
        self::assertNotSame((string) $a, (string) $b);
    }

    // =========================================================================
    // Declared properties still work
    // =========================================================================

    public function testDeclaredPropertyReadable(): void
    {
        $obj = $this->makeAny();
        self::assertSame('test', $obj->name);
    }

    public function testDeclaredPropertyWritable(): void
    {
        $obj       = $this->makeAny();
        $obj->name = 'changed';
        self::assertSame('changed', $obj->name);
    }
}
