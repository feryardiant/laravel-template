<?php

declare(strict_types=1);

namespace App\Support\Feature;

use App\Models\Organization;
use Laravel\Pennant\Feature;

final class ContactGender
{
    public const FEATURE = 'contact-gender';

    /**
     * Determine whether the tenant shows and collects gender for contacts.
     */
    public static function enabled(?Organization $organization): bool
    {
        return $organization !== null && Feature::for($organization)->active(self::FEATURE);
    }

    /**
     * Enable the contact gender field for the tenant.
     */
    public static function activate(Organization $organization): void
    {
        Feature::for($organization)->activate(self::FEATURE);
    }

    /**
     * Disable the contact gender field for the tenant.
     */
    public static function deactivate(Organization $organization): void
    {
        Feature::for($organization)->deactivate(self::FEATURE);
    }
}
