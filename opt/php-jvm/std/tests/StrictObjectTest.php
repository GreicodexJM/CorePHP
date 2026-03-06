<?php

declare(strict_types=1);

namespace std\Tests;

use PHPUnit\Framework\TestCase;
use std\StrictObject;

/**
 * @covers \std\StrictObject
 */
final class StrictObjectTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Concrete stub for testing (StrictObject is abstract)
    // -------------------------------------------------------------------------

    private function makeStrictObject(): object
    {
        return new class extends StrictObject {
            public string $declared = 'hello';
        };
    }

    // =========================================================================
    // __get — undefined property read
    // =========================================================================

    public function testGetOnDeclaredPropertyWorks(): void
    {
        $obj = $this->makeStrictObject();
        self::assertSame('hello', $obj->declared);
    }

    public function testGetOnUndefinedPropertyThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/read undefined property/');
        $obj = $this->makeStrictObject();
        $_ = $obj->typo;
    }

    public function testGetExceptionMessageContainsPropertyName(): void
    {
        $obj = $this->makeStrictObject();
        try {
            $_ = $obj->missingProp;
            self::fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('missingProp', $e->getMessage());
        }
    }

    // =========================================================================
    // __set — undefined property write
    // =========================================================================

    public function testSetOnDeclaredPropertyWorks(): void
    {
        $obj = $this->makeStrictObject();
        $obj->declared = 'world';
        self::assertSame('world', $obj->declared);
    }

    public function testSetOnUndefinedPropertyThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/write undefined property/');
        $obj = $this->makeStrictObject();
        $obj->newProp = 'value';
    }

    public function testSetExceptionMessageContainsPropertyName(): void
    {
        $obj = $this->makeStrictObject();
        try {
            $obj->dynamicProp = 'val';
            self::fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('dynamicProp', $e->getMessage());
        }
    }

    // =========================================================================
    // __isset — undefined property check
    // =========================================================================

    public function testIssetOnDeclaredPropertyWorks(): void
    {
        $obj = $this->makeStrictObject();
        // This should not throw — declared property
        self::assertTrue(isset($obj->declared));
    }

    public function testIssetOnUndefinedPropertyThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/isset\(\)/');
        $obj = $this->makeStrictObject();
        $_ = isset($obj->undeclaredProp);
    }

    // =========================================================================
    // __unset — undefined property unset
    // =========================================================================

    public function testUnsetOnUndefinedPropertyThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/unset\(\)/');
        $obj = $this->makeStrictObject();
        unset($obj->undeclaredProp);
    }
}
