<?php

declare(strict_types=1);

// Fixture with no issues — the auditor must report zero findings here.

final class Calculator
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    public function multiply(int $a, int $b): int
    {
        return $a * $b;
    }
}
