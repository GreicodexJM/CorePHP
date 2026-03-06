<?php

declare(strict_types=1);

namespace std\Security\Safe;

/**
 * Thrown when json_encode() fails or cannot serialize the given value.
 *
 * Replaces the default PHP behaviour of returning false silently.
 */
final class JsonEncodeException extends \InvalidArgumentException
{
}
