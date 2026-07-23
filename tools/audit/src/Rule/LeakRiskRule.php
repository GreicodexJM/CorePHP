<?php

declare(strict_types=1);

namespace core\Audit\Rule;

use core\Audit\Finding;
use core\Audit\Pillar;
use core\Audit\Severity;
use PhpParser\Node;
use PhpParser\Node\Expr\Exit_;
use PhpParser\Node\Stmt\Global_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Static_;

/**
 * STABLE — flags patterns that misbehave in a long-running (persistent) worker,
 * where the process outlives a single request.
 */
final class LeakRiskRule implements Rule
{
    public function inspect(Node $node, string $file): array
    {
        // exit()/die() ends the whole worker process, not just this request.
        if ($node instanceof Exit_) {
            return [new Finding(
                Pillar::STABLE,
                Severity::HIGH,
                'worker-exit',
                $file,
                $node->getStartLine(),
                'exit()/die() terminates the entire persistent worker, not just this request — RoadRunner must respawn it.',
                'Return a response (or throw) instead of exit()/die().',
            )];
        }

        // `global` state persists across every request the worker handles.
        if ($node instanceof Global_) {
            return [new Finding(
                Pillar::STABLE,
                Severity::MEDIUM,
                'global-state',
                $file,
                $node->getStartLine(),
                'global state leaks across requests in a persistent worker.',
                'Pass dependencies explicitly instead of reaching for globals.',
            )];
        }

        // Static class properties accumulate across requests.
        if ($node instanceof Property && $node->isStatic()) {
            return [new Finding(
                Pillar::STABLE,
                Severity::MEDIUM,
                'static-property',
                $file,
                $node->getStartLine(),
                'static property keeps its value across requests in a persistent worker — state bleeds between users.',
                'Avoid mutable static state, or reset it per request.',
            )];
        }

        // Function-level static variables persist for the worker's lifetime.
        if ($node instanceof Static_) {
            return [new Finding(
                Pillar::STABLE,
                Severity::MEDIUM,
                'static-variable',
                $file,
                $node->getStartLine(),
                'static variable persists across requests in a persistent worker.',
                'Avoid static request-scoped state, or reset it explicitly.',
            )];
        }

        return [];
    }
}
