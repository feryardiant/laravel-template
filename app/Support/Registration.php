<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Organization;
use Laravel\Pennant\Feature;

final class Registration
{
    public const FEATURE = 'registration';

    /**
     * Determine whether the root tenant has enabled public registration.
     */
    public static function enabled(): bool
    {
        $root = Organization::query()->rootTenant()->first();

        return $root !== null && Feature::for($root)->active(self::FEATURE);
    }

    /**
     * Enable public registration for the root tenant.
     */
    public static function activate(Organization $root): void
    {
        Feature::for($root)->activate(self::FEATURE);
    }

    /**
     * Disable public registration for the root tenant.
     */
    public static function deactivate(Organization $root): void
    {
        Feature::for($root)->deactivate(self::FEATURE);
    }
}
