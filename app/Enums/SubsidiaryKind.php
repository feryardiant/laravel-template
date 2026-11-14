<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The structural role a child organization plays under its parent.
 *
 * This is descriptive only: every kind keeps its own workspace, members and
 * contacts, and it never grants access to the parent or its other children.
 */
enum SubsidiaryKind: string
{
    /**
     * A separate legal entity owned by the parent.
     */
    case Subsidiary = 'subsidiary';

    /**
     * The same legal entity operating from another location.
     */
    case Branch = 'branch';

    /**
     * An internal division or product line of the parent.
     */
    case Division = 'division';

    /**
     * An internal management grouping of the parent.
     */
    case BusinessUnit = 'business_unit';

    /**
     * Get the human-readable label for the kind.
     */
    public function label(): string
    {
        return match ($this) {
            self::Subsidiary => __('Subsidiary'),
            self::Branch => __('Branch'),
            self::Division => __('Division'),
            self::BusinessUnit => __('Business unit'),
        };
    }
}
