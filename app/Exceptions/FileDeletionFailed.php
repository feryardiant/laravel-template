<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a stored file could not be deleted, so the caller can roll back
 * the database change that would otherwise orphan it.
 */
final class FileDeletionFailed extends RuntimeException
{
    // .
}
