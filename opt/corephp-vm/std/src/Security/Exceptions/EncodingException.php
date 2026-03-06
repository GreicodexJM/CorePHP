<?php

declare(strict_types=1);

namespace core\Security\Exceptions;

/**
 * Thrown when an encoding/decoding operation fails.
 *
 * Currently covers:
 *   - base64_decode() with invalid input in strict mode
 */
final class EncodingException extends \RuntimeException
{
}
