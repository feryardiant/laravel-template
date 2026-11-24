<?php

declare(strict_types=1);

namespace App\Actions\Tenancies;

use App\Events\FeatureToggled;
use App\Models\Organization;
use App\Models\User;
use App\Support\Feature\ContactDeletion;
use App\Support\Feature\ContactGender;
use App\Support\Feature\MemberDeletion;
use App\Support\Feature\ProfileSelfEdit;
use App\Support\Feature\Registration;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;

/**
 * Toggles an organization feature flag and records the audit event.
 */
final class ToggleFeature
{
    /**
     * Toggle an organization feature and record the audit event.
     *
     * @param  class-string<Registration|ProfileSelfEdit|ContactGender|MemberDeletion|ContactDeletion>  $flag
     */
    public function handle(Organization $organization, string $flag, bool $enabled, ?User $actor = null): bool
    {
        if ($this->enabled($organization, $flag) === $enabled) {
            return false;
        }

        DB::transaction(function () use ($organization, $flag, $enabled, $actor): void {
            if ($enabled) {
                $flag::activate($organization);
            } else {
                $flag::deactivate($organization);
            }

            event(new FeatureToggled($organization, $actor, $flag::FEATURE, $enabled));
        });

        return true;
    }

    /**
     * @param  class-string<Registration|ProfileSelfEdit|ContactGender|MemberDeletion|ContactDeletion>  $flag
     */
    private function enabled(Organization $organization, string $flag): bool
    {
        return (bool) Feature::for($organization)->active($flag::FEATURE);
    }
}
