<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Kind of communication record attached to an entity.
 */
enum ContactDetailType: string
{
    case Email = 'email';

    case Phone = 'phone';
}
