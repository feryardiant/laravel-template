<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why a relationship period was closed.
 */
enum RelationshipPeriodReason: string
{
    case Renewed = 'renewed';

    case RoleChanged = 'role_changed';

    case Removed = 'removed';

    case Left = 'left';

    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::Renewed => __('Renewed'),
            self::RoleChanged => __('Role changed'),
            self::Removed => __('Removed'),
            self::Left => __('Left'),
            self::Deleted => __('Deleted'),
        };
    }
}
