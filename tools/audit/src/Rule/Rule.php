<?php

declare(strict_types=1);

namespace core\Audit\Rule;

use core\Audit\Finding;
use PhpParser\Node;

/**
 * A rule inspects one AST node and returns any findings it produces.
 */
interface Rule
{
    /**
     * @return list<Finding>
     */
    public function inspect(Node $node, string $file): array;
}
