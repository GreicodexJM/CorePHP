<?php

declare(strict_types=1);

namespace core\Audit;

use core\Audit\Rule\DangerousFunctionRule;
use core\Audit\Rule\LeakRiskRule;
use core\Audit\Rule\Rule;
use core\Audit\Rule\SilentFailureRule;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Scans a path of PHP files, runs the rule set over each AST, and collects
 * findings across the three pillars (SAFE / SECURE / STABLE).
 */
final class Auditor
{
    private readonly Parser $parser;

    /** @var list<Rule> */
    private readonly array $rules;

    /** Directory names skipped during discovery. */
    private const SKIP_DIRS = ['vendor', 'node_modules', '.git', '.svn'];

    /**
     * @param list<Rule>|null $rules Defaults to the full starter rule set.
     */
    public function __construct(?array $rules = null)
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
        $this->rules  = $rules ?? [
            new DangerousFunctionRule(),
            new LeakRiskRule(),
            new SilentFailureRule(),
        ];
    }

    /**
     * Audit a file or directory.
     *
     * @return list<Finding>
     */
    public function audit(string $path): array
    {
        $findings = [];
        foreach ($this->phpFiles($path) as $file) {
            foreach ($this->auditFile($file) as $finding) {
                $findings[] = $finding;
            }
        }
        return $findings;
    }

    /**
     * @return list<Finding>
     */
    public function auditFile(string $file): array
    {
        $code = @file_get_contents($file);
        if ($code === false) {
            return [];
        }

        try {
            $ast = $this->parser->parse($code);
        } catch (\PhpParser\Error) {
            return []; // Unparseable file — skip rather than crash the whole run.
        }

        if ($ast === null) {
            return [];
        }

        $rules   = $this->rules;
        $visitor = new class ($rules, $file) extends NodeVisitorAbstract {
            /** @var list<Finding> */
            public array $findings = [];

            /** @param list<Rule> $rules */
            public function __construct(private readonly array $rules, private readonly string $file)
            {
            }

            public function enterNode(Node $node): null
            {
                foreach ($this->rules as $rule) {
                    foreach ($rule->inspect($node, $this->file) as $finding) {
                        $this->findings[] = $finding;
                    }
                }
                return null;
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $suppressed = $this->suppressedLines($code);

        return array_values(array_filter(
            $visitor->findings,
            static fn (Finding $f): bool => !isset($suppressed[$f->line]),
        ));
    }

    /**
     * Lines suppressed by an inline `corephp-audit-ignore` marker. The marker
     * suppresses findings on its own line (trailing comment) and the next line
     * (comment placed on the line above the code).
     *
     * @return array<int, true>
     */
    private function suppressedLines(string $code): array
    {
        $lines      = explode("\n", str_replace(["\r\n", "\r"], "\n", $code));
        $suppressed = [];
        foreach ($lines as $index => $line) {
            if (str_contains($line, 'corephp-audit-ignore')) {
                $lineNo                  = $index + 1;
                $suppressed[$lineNo]     = true;
                $suppressed[$lineNo + 1] = true;
            }
        }
        return $suppressed;
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $path): array
    {
        if (is_file($path)) {
            return str_ends_with($path, '.php') ? [$path] : [];
        }

        if (!is_dir($path)) {
            return [];
        }

        $files    = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                static function (\SplFileInfo $current): bool {
                    return !($current->isDir() && in_array($current->getFilename(), self::SKIP_DIRS, true));
                },
            ),
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);
        return $files;
    }
}
