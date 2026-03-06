<?php

declare(strict_types=1);

namespace std\Security\Safe;

/**
 * Thrown when file_put_contents() fails to write to a file.
 *
 * Replaces the default PHP behaviour of returning false silently.
 */
final class FileWriteException extends \RuntimeException
{
}
