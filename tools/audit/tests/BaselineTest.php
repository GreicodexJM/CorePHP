<?php

declare(strict_types=1);

namespace core\Audit\Tests;

use core\Audit\Auditor;
use core\Audit\Baseline;
use core\Audit\Finding;
use core\Audit\Pillar;
use core\Audit\Severity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Baseline::class)]
final class BaselineTest extends TestCase
{
    /** @var list<string> */
    private array $tmp = [];

    protected function tearDown(): void
    {
        foreach ($this->tmp as $f) {
            if (is_file($f)) {
                unlink($f);
            }
        }
        $this->tmp = [];
    }

    /** @param list<Finding> $findings */
    private function baselineFileFor(array $findings): string
    {
        $path = tempnam(sys_get_temp_dir(), 'corephp_baseline_');
        self::assertIsString($path);
        file_put_contents($path, Baseline::generate($findings));
        $this->tmp[] = $path;
        return $path;
    }

    private function finding(string $rule, string $file, string $message): Finding
    {
        return new Finding(Pillar::SECURE, Severity::HIGH, $rule, $file, 5, $message, 'fix');
    }

    public function testRoundTripSuppressesAllBaselinedFindings(): void
    {
        $findings = (new Auditor())->auditFile(__DIR__ . '/fixtures/secure_issues.php');
        self::assertNotEmpty($findings);

        $baseline = Baseline::fromFile($this->baselineFileFor($findings));

        self::assertSame([], $baseline->filter($findings));
    }

    public function testNewOccurrenceOfSameIssueIsNotSuppressed(): void
    {
        $one      = $this->finding('eval', 'a.php', 'eval() executes arbitrary PHP.');
        $baseline = Baseline::fromFile($this->baselineFileFor([$one]));

        // Two identical findings, baseline budget is 1 → one stays (the new one).
        $result = $baseline->filter([$one, $one]);

        self::assertCount(1, $result);
        self::assertSame('eval', $result[0]->rule);
    }

    public function testUnbaselinedFindingPassesThrough(): void
    {
        $baselined = $this->finding('eval', 'a.php', 'eval() executes arbitrary PHP.');
        $other     = $this->finding('unserialize', 'b.php', 'unserialize() is a dangerous primitive.');

        $baseline = Baseline::fromFile($this->baselineFileFor([$baselined]));
        $result   = $baseline->filter([$baselined, $other]);

        self::assertCount(1, $result);
        self::assertSame('unserialize', $result[0]->rule);
    }

    public function testMissingBaselineFileThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        Baseline::fromFile('/no/such/corephp-baseline.json');
    }

    public function testMalformedBaselineFileThrows(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'corephp_baseline_');
        self::assertIsString($path);
        file_put_contents($path, '{"not":"a baseline"}');
        $this->tmp[] = $path;

        $this->expectException(\RuntimeException::class);
        Baseline::fromFile($path);
    }
}
