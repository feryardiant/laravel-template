<?php

declare(strict_types=1);

namespace App\Support\Feature;

use App\Models\Organization;
use Laravel\Pennant\Feature;

final class ProfileSelfEdit
{
    public const FEATURE = 'profile-self-edit';

    /**
     * Determine whether the tenant lets org-owned people edit their profile.
     */
    public static function enabled(?Organization $organization): bool
    {
        return $organization !== null && Feature::for($organization)->active(self::FEATURE);
    }

    /**
     * Allow org-owned people to edit their own profile for the tenant.
     */
    public static function activate(Organization $organization): void
    {
        Feature::for($organization)->activate(self::FEATURE);
    }

    /**
     * Keep org-owned profile fields under manager control for the tenant.
     */
    public static function deactivate(Organization $organization): void
    {
        Feature::for($organization)->deactivate(self::FEATURE);
    }
}
