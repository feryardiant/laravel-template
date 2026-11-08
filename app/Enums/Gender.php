<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Only binary-type genders are supported here.
 */
enum Gender: string
{
    case Male = 'M';

    case Female = 'F';
}
