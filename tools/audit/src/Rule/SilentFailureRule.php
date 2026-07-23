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
 * SAFE — flags native functions that fail silently (return null/false/0) instead
 * of throwing. Where CorePHP ships a drop-in replacement, it points at the s_*()
 * shim; otherwise it says to handle the failure return explicitly.
 */
final class SilentFailureRule implements Rule
{
    /**
     * function => [severity, why it's unsafe, the fix]
     *
     * @var array<string, array{0: Severity, 1: string, 2: string}>
     */
    private const FUNCTIONS = [
        // Have a drop-in s_*() replacement.
        'json_decode'           => [Severity::MEDIUM, 'returns null on invalid JSON — the bug surfaces far from here', 'Use s_json(), which throws instead of returning null.'],
        'file_get_contents'     => [Severity::MEDIUM, 'returns false when the file is missing/unreadable',             'Use s_file(), which throws instead of returning false.'],
        'file_put_contents'     => [Severity::MEDIUM, 'returns false when the write fails',                            'Use s_write(), which throws instead of returning false.'],
        'preg_replace'          => [Severity::MEDIUM, 'returns null on a PCRE error',                                  'Use s_replace(), which throws on a PCRE error.'],
        'base64_decode'         => [Severity::MEDIUM, 'returns false on invalid base64',                              'Use s_b64(), which throws on invalid input.'],
        'curl_exec'             => [Severity::MEDIUM, 'returns false on transport failure',                          'Use s_get()/s_post(), which throw on transport failure.'],
        'intval'                => [Severity::LOW,    'returns 0 for non-numeric input',                             'Use s_int(), which throws for non-numeric input.'],
        'floatval'              => [Severity::LOW,    'returns 0.0 for non-numeric input',                           'Use s_float(), which throws for non-numeric input.'],
        // No direct shim — handle the failure return explicitly.
        'stream_get_contents'   => [Severity::MEDIUM, 'returns false on read failure',                               'Check the return for false and throw, or read via a safe wrapper.'],
        'simplexml_load_string' => [Severity::MEDIUM, 'returns false on malformed XML',                              'Check the return for false and throw on a parse failure.'],
        'simplexml_load_file'   => [Severity::MEDIUM, 'returns false on malformed or missing XML',                   'Check the return for false and throw on a parse failure.'],
        'strtotime'             => [Severity::LOW,    'returns false on an unparseable date string',                 'Validate the return, or parse into a typed DateTimeImmutable.'],
        'getenv'                => [Severity::LOW,    'returns false when the variable is unset',                    'Use s_env()/s_env_or() for a clear default or a clear failure.'],
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

        [$severity, $why, $fix] = self::FUNCTIONS[$name];

        return [new Finding(
            Pillar::SAFE,
            $severity,
            'silent-failure',
            $file,
            $node->getStartLine(),
            sprintf('%s() %s.', $name, $why),
            $fix,
        )];
    }
}
