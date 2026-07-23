<?php

declare(strict_types=1);

namespace core\Audit\Rule;

use core\Audit\Finding;
use core\Audit\Pillar;
use core\Audit\Severity;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;

/**
 * STABLE — flags mutation of process-global runtime state. In a persistent
 * worker the process outlives the request, so a change made for one request
 * silently leaks into every request the worker handles afterwards.
 */
final class GlobalRuntimeMutationRule implements Rule
{
    /**
     * Functions that mutate process-global runtime config. `error_reporting` and
     * `mb_internal_encoding` are getters when called with no arguments, so those
     * are only flagged when arguments are present (see below).
     *
     * @var list<string>
     */
    private const MUTATORS = [
        'ini_set',
        'putenv',
        'setlocale',
        'date_default_timezone_set',
        'error_reporting',
        'mb_internal_encoding',
    ];

    public function inspect(Node $node, string $file): array
    {
        if ($node instanceof FuncCall && $node->name instanceof Name) {
            $name = $node->name->toString();
            if (in_array($name, self::MUTATORS, true) && $node->args !== []) {
                return [new Finding(
                    Pillar::STABLE,
                    Severity::MEDIUM,
                    'global-runtime-mutation',
                    $file,
                    $node->getStartLine(),
                    sprintf('%s() mutates process-global runtime config that persists across requests in a persistent worker.', $name),
                    'Set this once at bootstrap; a change here leaks into every later request the worker handles.',
                )];
            }
        }

        // Writes to $GLOBALS create cross-request state.
        if ($node instanceof Assign
            && $node->var instanceof ArrayDimFetch
            && $node->var->var instanceof Variable
            && $node->var->var->name === 'GLOBALS'
        ) {
            return [new Finding(
                Pillar::STABLE,
                Severity::MEDIUM,
                'globals-write',
                $file,
                $node->getStartLine(),
                'writing to $GLOBALS creates state that persists across requests in a persistent worker.',
                'Pass dependencies explicitly instead of using $GLOBALS.',
            )];
        }

        return [];
    }
}
