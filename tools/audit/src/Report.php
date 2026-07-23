<?php

declare(strict_types=1);

namespace core\Audit;

/**
 * Renders findings as a grouped terminal report or JSON, and computes the CI
 * exit code (0 = clean, 1 = findings at or above the severity gate).
 */
final class Report
{
    /**
     * @param list<Finding> $findings
     */
    public function __construct(private readonly array $findings)
    {
    }

    /**
     * @return list<Finding>
     */
    private function gated(Severity $minSeverity): array
    {
        return array_values(array_filter(
            $this->findings,
            static fn (Finding $f): bool => $f->severity->weight() >= $minSeverity->weight(),
        ));
    }

    public function exitCode(Severity $minSeverity): int
    {
        return $this->gated($minSeverity) === [] ? 0 : 1;
    }

    public function json(Severity $minSeverity): string
    {
        $findings = array_map(static fn (Finding $f): array => $f->toArray(), $this->gated($minSeverity));

        return (string) json_encode([
            'summary'  => $this->counts($minSeverity),
            'findings' => $findings,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array{total: int, SECURE: int, STABLE: int, SAFE: int}
     */
    private function counts(Severity $minSeverity): array
    {
        $gated = $this->gated($minSeverity);
        return [
            'total'  => count($gated),
            'SECURE' => $this->countPillar($gated, Pillar::SECURE),
            'STABLE' => $this->countPillar($gated, Pillar::STABLE),
            'SAFE'   => $this->countPillar($gated, Pillar::SAFE),
        ];
    }

    /**
     * @param list<Finding> $findings
     */
    private function countPillar(array $findings, Pillar $pillar): int
    {
        return count(array_filter($findings, static fn (Finding $f): bool => $f->pillar === $pillar));
    }

    public function terminal(Severity $minSeverity, bool $color = true): string
    {
        $c = new class ($color) {
            public function __construct(private readonly bool $on)
            {
            }

            public function __invoke(string $code, string $text): string
            {
                return $this->on ? "\033[{$code}m{$text}\033[0m" : $text;
            }
        };

        $gated = $this->gated($minSeverity);
        $out   = "\n" . $c('1', 'CorePHP Audit') . " — SAFE / SECURE / STABLE readiness\n\n";

        if ($gated === []) {
            return $out . '  ' . $c('32', '✓ No issues found.') . "\n";
        }

        // Summary line.
        $counts = $this->counts($minSeverity);
        $out .= '  '
            . $c('31', "SECURE {$counts['SECURE']}") . '   '
            . $c('33', "STABLE {$counts['STABLE']}") . '   '
            . $c('36', "SAFE {$counts['SAFE']}") . "\n";

        // Group by pillar (most urgent first), then sort by severity desc, file, line.
        $byPillar = [];
        foreach ($gated as $f) {
            $byPillar[$f->pillar->value][] = $f;
        }
        uksort(
            $byPillar,
            static fn (string $a, string $b): int => Pillar::from($a)->order() <=> Pillar::from($b)->order(),
        );

        foreach ($byPillar as $pillarValue => $findings) {
            usort($findings, static function (Finding $a, Finding $b): int {
                return [$b->severity->weight(), $a->file, $a->line]
                    <=> [$a->severity->weight(), $b->file, $b->line];
            });

            $out .= "\n" . $c('1', $pillarValue) . ' (' . count($findings) . ")\n";
            foreach ($findings as $f) {
                $sevColor = match ($f->severity) {
                    Severity::HIGH   => '31',
                    Severity::MEDIUM => '33',
                    Severity::LOW    => '2',
                };
                $out .= sprintf(
                    "  %s  %s  %s\n      %s %s\n",
                    $c('36', $f->file . ':' . $f->line),
                    $c($sevColor, str_pad($f->severity->label(), 4)),
                    $f->message,
                    $c('2', '→'),
                    $f->fix,
                );
            }
        }

        $out .= "\n" . $c('1', $counts['total'] . ' finding(s).') . ' '
            . ($counts['total'] > 0 ? $c('31', 'Exit 1.') : '') . "\n";

        return $out;
    }
}
