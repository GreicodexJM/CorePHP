<?php

declare(strict_types=1);

namespace core\Security\Safe;

/**
 * Thrown when a type coercion (intval, floatval) cannot be performed safely.
 *
 * Replaces the default PHP behaviour of silently returning 0 for non-numeric values.
 */
final class TypeCoercionException extends \InvalidArgumentException
{
}
