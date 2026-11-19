<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/**
 * Thrown by `failContactDetailWrites()` so rollback tests can tell the injected
 * transaction failure apart from an earlier one, such as a staging error.
 */
final class ContactDetailWriteFailed extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('contact detail write failed');
    }
}
