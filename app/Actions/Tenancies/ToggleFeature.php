<?php

declare(strict_types=1);

namespace App\Actions\Tenancies;

use App\Enums\Feature;
use App\Events\FeatureToggled;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Laravel\Pennant\Feature as PennantFeature;

/**
 * Toggles an organization feature flag and records the audit event.
 */
final class ToggleFeature
{
    /**
     * Toggle an organization feature and record the audit event.
     */
    public function handle(Organization $organization, Feature $feature, bool $enabled): bool
    {
        if ($feature->isRootOnly() && ! $organization->isRootTenant()) {
            throw new InvalidArgumentException("The {$feature->value} feature can only be toggled on the root tenant.");
        }

        if ($feature->enabled($organization) === $enabled) {
            return false;
        }

        /** @var User $actor */
        $actor = Auth::user();

        DB::transaction(function () use ($organization, $feature, $enabled, $actor): void {
            if ($enabled) {
                $this->activate($organization, $feature);
            } else {
                $this->deactivate($organization, $feature);
            }

            event(new FeatureToggled($organization, $actor, $feature->value, $enabled));
        });

        return true;
    }

    /**
     * Enable the feature without recording the audit event.
     */
    public function activate(Organization $organization, Feature $feature): void
    {
        PennantFeature::for($organization)->activate($feature);
    }

    /**
     * Disable the feature without recording the audit event.
     */
    public function deactivate(Organization $organization, Feature $feature): void
    {
        PennantFeature::for($organization)->deactivate($feature);
    }
}
