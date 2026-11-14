<?php

declare(strict_types=1);

namespace App\Support\Feature;

use App\Models\Organization;
use Laravel\Pennant\Feature;

final class MemberDeletion
{
    public const FEATURE = 'member-deletion';

    /**
     * Determine whether the tenant allows removing members.
     */
    public static function enabled(?Organization $organization): bool
    {
        return $organization !== null && Feature::for($organization)->active(self::FEATURE);
    }

    /**
     * Allow member removal for the tenant.
     */
    public static function activate(Organization $organization): void
    {
        Feature::for($organization)->activate(self::FEATURE);
    }

    /**
     * Block member removal for the tenant.
     */
    public static function deactivate(Organization $organization): void
    {
        Feature::for($organization)->deactivate(self::FEATURE);
    }
}
