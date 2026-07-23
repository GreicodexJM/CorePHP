<?php

declare(strict_types=1);

namespace core\Audit\Rule;

use core\Audit\Finding;
use core\Audit\Pillar;
use core\Audit\Severity;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;

/**
 * SAFE — flags native functions that fail silently (return null/false/0) and
 * points at the throwing s_*() replacement.
 */
final class SilentFailureRule implements Rule
{
    /**
     * function => [suggested replacement, severity, why it's unsafe]
     *
     * @var array<string, array{0: string, 1: Severity, 2: string}>
     */
    private const FUNCTIONS = [
        'json_decode'       => ['s_json()',            Severity::MEDIUM, 'returns null on invalid JSON — the bug surfaces far from here'],
        'file_get_contents' => ['s_file()',            Severity::MEDIUM, 'returns false when the file is missing/unreadable'],
        'file_put_contents' => ['s_write()',           Severity::MEDIUM, 'returns false when the write fails'],
        'preg_replace'      => ['s_replace()',         Severity::MEDIUM, 'returns null on a PCRE error'],
        'base64_decode'     => ['s_b64()',             Severity::MEDIUM, 'returns false on invalid base64'],
        'curl_exec'         => ['s_get() / s_post()',  Severity::MEDIUM, 'returns false on transport failure'],
        'intval'            => ['s_int()',             Severity::LOW,    'returns 0 for non-numeric input'],
        'floatval'          => ['s_float()',           Severity::LOW,    'returns 0.0 for non-numeric input'],
    ];

    public function inspect(Node $node, string $file): array
    {
        if (!$node instanceof FuncCall || !$node->name instanceof Name) {
            return [];
        }

        $name = $node->name->toString();
        if (!isset(self::FUNCTIONS[$name])) {
            return [];
        }

        [$replacement, $severity, $why] = self::FUNCTIONS[$name];

        return [new Finding(
            Pillar::SAFE,
            $severity,
            'silent-failure',
            $file,
            $node->getStartLine(),
            sprintf('%s() %s.', $name, $why),
            sprintf('Use %s, which throws instead of failing silently.', $replacement),
        )];
    }
}
