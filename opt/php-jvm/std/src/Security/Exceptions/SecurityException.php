<?php

declare(strict_types=1);

namespace std\Security\Exceptions;

/**
 * Thrown when a permanently disabled security-sensitive function is called.
 *
 * Functions that are permanently prohibited:
 *   - unserialize() — RCE vector
 *   - eval() — covered by PHPStan rule (cannot be blocked in php.ini for language constructs)
 */
final class SecurityException extends \RuntimeException
{
}
