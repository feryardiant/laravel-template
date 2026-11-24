<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The languages the interface is translated into.
 */
enum Locale: string
{
    case English = 'en';
    case Indonesian = 'id';

    /**
     * The human-readable label for the locale.
     */
    public function label(): string
    {
        return match ($this) {
            self::English => __('English'),
            self::Indonesian => __('Bahasa Indonesia'),
        };
    }
}
