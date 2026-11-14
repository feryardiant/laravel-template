<?php

declare(strict_types=1);

namespace App\Actions\Memberships;

use App\Enums\OrganizationRelationshipType;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SetDefaultOrganization
{
    /**
     * Make the given organization the user's default workspace.
     */
    public function handle(User $user, Organization $organization): bool
    {
        $person = $user->person;

        if ($person === null) {
            return false;
        }

        return DB::transaction(function () use ($person, $organization): bool {
            // Serialize with `CreateMember::attach()`, which resets the same
            // primary flag under a person lock.
            $person->newQuery()
                ->whereKey($person->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $person->memberships()->whereKey($organization->getKey())->exists()) {
                return false;
            }

            $person->organizationRelationships()->newPivotQuery()
                ->where('type', OrganizationRelationshipType::Member)
                ->update(['is_primary' => null]);

            OrganizationRelationship::query()
                ->forOrganization($organization)
                ->whereStakeholder($person)
                ->where('type', OrganizationRelationshipType::Member)
                ->update(['is_primary' => true]);

            return true;
        });
    }
}
