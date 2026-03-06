<?php

declare(strict_types=1);

namespace core\Security\Safe;

/**
 * Thrown when file_get_contents() fails, the file does not exist, or is not readable.
 *
 * Replaces the default PHP behaviour of returning false silently.
 */
final class FileReadException extends \RuntimeException
{
}
