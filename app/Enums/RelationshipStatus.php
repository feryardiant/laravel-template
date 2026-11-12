<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The lifecycle of an organization relationship, derived from its dates.
 */
enum RelationshipStatus
{
    case Scheduled;

    case Active;

    case Expired;

    /**
     * Get the human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Scheduled => __('Scheduled'),
            self::Active => __('Active'),
            self::Expired => __('Expired'),
        };
    }
}
