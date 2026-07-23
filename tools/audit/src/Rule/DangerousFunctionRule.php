<?php

declare(strict_types=1);

namespace core\Audit\Rule;

use core\Audit\Finding;
use core\Audit\Pillar;
use core\Audit\Severity;
use PhpParser\Node;
use PhpParser\Node\Expr\Eval_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;

/**
 * SECURE — flags dangerous primitives: arbitrary code execution, shell access,
 * object injection, and variable-injection helpers.
 */
final class DangerousFunctionRule implements Rule
{
    /** @var array<string, string> function => fix */
    private const DANGEROUS = [
        'unserialize'     => 'Use s_json()/JSON or a typed DTO — unserialize() enables object-injection attacks.',
        'exec'            => 'Avoid shell execution; if unavoidable, validate input and use escapeshellarg().',
        'shell_exec'      => 'Avoid shell execution; if unavoidable, validate input and use escapeshellarg().',
        'system'          => 'Avoid shell execution; if unavoidable, validate input and use escapeshellarg().',
        'passthru'        => 'Avoid shell execution; if unavoidable, validate input and use escapeshellarg().',
        'popen'           => 'Avoid shell execution; if unavoidable, validate input and use escapeshellarg().',
        'proc_open'       => 'Avoid spawning processes; if unavoidable, validate input rigorously.',
        'create_function' => 'Use a real closure — create_function() eval()s its body.',
        'extract'         => 'Assign variables explicitly — extract() injects array keys into local scope.',
    ];

    public function inspect(Node $node, string $file): array
    {
        // eval() is a language construct, not a function call.
        if ($node instanceof Eval_) {
            return [$this->finding(
                'eval',
                $file,
                $node->getStartLine(),
                'eval() executes arbitrary PHP — a critical code-injection risk.',
                'Remove eval(); express the logic in real code.',
            )];
        }

        if (!$node instanceof FuncCall || !$node->name instanceof Name) {
            return [];
        }

        $name = $node->name->toString();

        // assert('code') runs its string argument as PHP; assert(bool) is fine.
        if ($name === 'assert'
            && isset($node->args[0])
            && $node->args[0] instanceof Node\Arg
            && $node->args[0]->value instanceof String_
        ) {
            return [$this->finding(
                'assert',
                $file,
                $node->getStartLine(),
                'assert() with a string argument executes it as PHP code.',
                'Pass a boolean expression to assert(), never a string.',
            )];
        }

        if (!isset(self::DANGEROUS[$name])) {
            return [];
        }

        return [$this->finding(
            $name,
            $file,
            $node->getStartLine(),
            sprintf('%s() is a dangerous primitive.', $name),
            self::DANGEROUS[$name],
        )];
    }

    private function finding(string $rule, string $file, int $line, string $message, string $fix): Finding
    {
        return new Finding(Pillar::SECURE, Severity::HIGH, $rule, $file, $line, $message, $fix);
    }
}
