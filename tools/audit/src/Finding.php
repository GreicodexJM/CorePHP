<?php

declare(strict_types=1);

namespace core\Audit;

/**
 * A single audit finding — one issue, at one place, with the fix.
 */
final class Finding
{
    public function __construct(
        public readonly Pillar $pillar,
        public readonly Severity $severity,
        public readonly string $rule,
        public readonly string $file,
        public readonly int $line,
        public readonly string $message,
        public readonly string $fix,
    ) {
    }

    /**
     * @return array{pillar: string, severity: string, rule: string, file: string, line: int, message: string, fix: string}
     */
    public function toArray(): array
    {
        return [
            'pillar'   => $this->pillar->value,
            'severity' => $this->severity->value,
            'rule'     => $this->rule,
            'file'     => $this->file,
            'line'     => $this->line,
            'message'  => $this->message,
            'fix'      => $this->fix,
        ];
    }
}
