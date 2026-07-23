<?php

declare(strict_types=1);

namespace core\Audit\Tests;

use core\Audit\Finding;
use core\Audit\Pillar;
use core\Audit\Report;
use core\Audit\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Report::class)]
final class ReportTest extends TestCase
{
    private function finding(Pillar $pillar, Severity $severity, string $rule = 'r'): Finding
    {
        return new Finding($pillar, $severity, $rule, 'app.php', 10, 'msg', 'fix');
    }

    public function testExitCodeZeroWhenNoFindings(): void
    {
        $report = new Report([]);
        self::assertSame(0, $report->exitCode(Severity::LOW));
    }

    public function testExitCodeOneWhenFindings(): void
    {
        $report = new Report([$this->finding(Pillar::SAFE, Severity::LOW)]);
        self::assertSame(1, $report->exitCode(Severity::LOW));
    }

    public function testMinSeverityGateFiltersLowerSeverities(): void
    {
        $report = new Report([
            $this->finding(Pillar::SAFE, Severity::LOW),
            $this->finding(Pillar::SECURE, Severity::HIGH),
        ]);

        // Gate at HIGH: the LOW finding is excluded, so it must not affect exit.
        self::assertSame(1, $report->exitCode(Severity::HIGH));

        // Only-LOW findings gated at HIGH => clean.
        $lowOnly = new Report([$this->finding(Pillar::SAFE, Severity::LOW)]);
        self::assertSame(0, $lowOnly->exitCode(Severity::HIGH));
    }

    public function testJsonContainsSummaryAndFindings(): void
    {
        $report = new Report([
            $this->finding(Pillar::SECURE, Severity::HIGH, 'eval'),
            $this->finding(Pillar::SAFE, Severity::MEDIUM, 'silent-failure'),
        ]);

        $decoded = json_decode($report->json(Severity::LOW), true);
        self::assertIsArray($decoded);
        self::assertSame(2, $decoded['summary']['total']);
        self::assertSame(1, $decoded['summary']['SECURE']);
        self::assertSame(1, $decoded['summary']['SAFE']);
        self::assertCount(2, $decoded['findings']);
    }

    public function testTerminalReportIsCleanWhenNoFindings(): void
    {
        $report = new Report([]);
        self::assertStringContainsString('No issues found', $report->terminal(Severity::LOW, color: false));
    }

    public function testTerminalReportListsFileAndFix(): void
    {
        $report = new Report([$this->finding(Pillar::SECURE, Severity::HIGH, 'eval')]);
        $text   = $report->terminal(Severity::LOW, color: false);
        self::assertStringContainsString('app.php:10', $text);
        self::assertStringContainsString('SECURE', $text);
        self::assertStringContainsString('fix', $text);
    }
}
