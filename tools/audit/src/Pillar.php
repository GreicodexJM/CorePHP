<?php

declare(strict_types=1);

namespace core\Audit;

/**
 * The three CorePHP readiness pillars a finding belongs to.
 */
enum Pillar: string
{
    case SAFE   = 'SAFE';
    case SECURE = 'SECURE';
    case STABLE = 'STABLE';

    /** Report ordering — most urgent pillar first. */
    public function order(): int
    {
        return match ($this) {
            self::SECURE => 0,
            self::STABLE => 1,
            self::SAFE   => 2,
        };
    }
}
