<?php

declare(strict_types=1);

namespace core\Security\Safe;

/**
 * Thrown when preg_match(), preg_match_all(), or preg_replace() encounters a PCRE error.
 *
 * Replaces the default PHP behaviour of returning false/null silently on regex failure.
 */
final class RegexException extends \RuntimeException
{
}
