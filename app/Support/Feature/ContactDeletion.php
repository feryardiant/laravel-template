<?php

declare(strict_types=1);

namespace App\Support\Feature;

use App\Models\Organization;
use Laravel\Pennant\Feature;

final class ContactDeletion
{
    public const FEATURE = 'contact-deletion';

    /**
     * Determine whether the tenant allows deleting contacts.
     */
    public static function enabled(?Organization $organization): bool
    {
        return $organization !== null && Feature::for($organization)->active(self::FEATURE);
    }

    /**
     * Allow contact deletion for the tenant.
     */
    public static function activate(Organization $organization): void
    {
        Feature::for($organization)->activate(self::FEATURE);
    }

    /**
     * Block contact deletion for the tenant.
     */
    public static function deactivate(Organization $organization): void
    {
        Feature::for($organization)->deactivate(self::FEATURE);
    }
}
