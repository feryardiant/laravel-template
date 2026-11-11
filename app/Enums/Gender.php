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

    /**
     * Get the human-readable label for the gender.
     */
    public function label(): string
    {
        return match ($this) {
            self::Male => __('Male'),
            self::Female => __('Female'),
        };
    }
}
