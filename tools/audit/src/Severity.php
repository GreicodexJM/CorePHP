<?php

declare(strict_types=1);

namespace core\Audit;

/**
 * Finding severity. `weight()` drives sorting and the `--min-severity` gate.
 */
enum Severity: string
{
    case LOW    = 'low';
    case MEDIUM = 'medium';
    case HIGH   = 'high';

    public function weight(): int
    {
        return match ($this) {
            self::LOW    => 1,
            self::MEDIUM => 2,
            self::HIGH   => 3,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::LOW    => 'LOW',
            self::MEDIUM => 'MED',
            self::HIGH   => 'HIGH',
        };
    }

    public static function fromString(string $value): self
    {
        return self::from(strtolower($value));
    }
}
