<?php

declare(strict_types=1);

namespace core\Security\Safe;

/**
 * Thrown when json_decode() fails or receives invalid JSON input.
 *
 * Replaces the default PHP behaviour of returning null silently.
 */
final class JsonDecodeException extends \InvalidArgumentException
{
}
