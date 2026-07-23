<?php

declare(strict_types=1);

namespace core\Audit\Tests;

use core\Audit\Auditor;
use core\Audit\Finding;
use core\Audit\Pillar;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Auditor::class)]
final class AuditorTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/fixtures';

    /**
     * @param list<Finding> $findings
     * @return list<string> the `rule` of each finding for the given pillar
     */
    private function rules(array $findings, Pillar $pillar): array
    {
        $rules = [];
        foreach ($findings as $f) {
            if ($f->pillar === $pillar) {
                $rules[] = $f->rule;
            }
        }
        sort($rules);
        return $rules;
    }

    public function testDetectsSafeSilentFailures(): void
    {
        $findings = (new Auditor())->auditFile(self::FIXTURES . '/safe_issues.php');
        self::assertSame(
            ['silent-failure', 'silent-failure', 'silent-failure'],
            $this->rules($findings, Pillar::SAFE),
        );
    }

    public function testDetectsSecurePrimitives(): void
    {
        $findings = (new Auditor())->auditFile(self::FIXTURES . '/secure_issues.php');
        self::assertSame(
            ['assert', 'eval', 'exec', 'unserialize'],
            $this->rules($findings, Pillar::SECURE),
        );
    }

    public function testDetectsStableWorkerFootguns(): void
    {
        $findings = (new Auditor())->auditFile(self::FIXTURES . '/stable_issues.php');
        self::assertSame(
            ['global-state', 'static-property', 'static-variable', 'worker-exit'],
            $this->rules($findings, Pillar::STABLE),
        );
    }

    public function testCleanFileProducesNoFindings(): void
    {
        $findings = (new Auditor())->auditFile(self::FIXTURES . '/clean.php');
        self::assertSame([], $findings);
    }

    public function testWorkerExitIsHighSeverity(): void
    {
        $findings = (new Auditor())->auditFile(self::FIXTURES . '/stable_issues.php');
        $exit = array_values(array_filter($findings, static fn (Finding $f): bool => $f->rule === 'worker-exit'));
        self::assertCount(1, $exit);
        self::assertSame('HIGH', $exit[0]->severity->label());
    }

    public function testDirectoryScanCoversAllFixtures(): void
    {
        $findings = (new Auditor())->audit(self::FIXTURES);
        // 3 SAFE + 4 SECURE + 4 STABLE across the fixtures, 0 from clean.php.
        self::assertCount(11, $findings);
    }

    public function testDirectoryScanSkipsVendor(): void
    {
        $tmp = sys_get_temp_dir() . '/corephp_audit_' . uniqid('', true);
        mkdir($tmp . '/vendor/pkg', 0o777, true);
        file_put_contents($tmp . '/app.php', "<?php\nunserialize(\$x);\n");
        file_put_contents($tmp . '/vendor/pkg/lib.php', "<?php\neval(\$y);\n");

        try {
            $findings = (new Auditor())->audit($tmp);
            // Only app.php is scanned; the vendor/ eval must be ignored.
            self::assertCount(1, $findings);
            self::assertSame('unserialize', $findings[0]->rule);
        } finally {
            @unlink($tmp . '/vendor/pkg/lib.php');
            @unlink($tmp . '/app.php');
            @rmdir($tmp . '/vendor/pkg');
            @rmdir($tmp . '/vendor');
            @rmdir($tmp);
        }
    }
}
