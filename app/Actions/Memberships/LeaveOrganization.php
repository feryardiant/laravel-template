<?php

declare(strict_types=1);

namespace App\Actions\Memberships;

use App\Actions\Relationships\RelationshipTimeline;
use App\Enums\OrganizationRelationshipType;
use App\Enums\RelationshipPeriodReason;
use App\Events\Memberships\MemberLeft;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class LeaveOrganization
{
    public function __construct(
        private RelationshipTimeline $timeline,
    ) {
        // .
    }

    /**
     * Remove the user's own membership from the organization.
     */
    public function handle(User $user, Organization $organization): bool
    {
        $person = $user->person;

        if ($person === null) {
            return false;
        }

        return DB::transaction(function () use ($person, $organization, $user): bool {
            $organization = Organization::query()->whereKey($organization->getKey())->lockForUpdate()->first();

            if ($organization === null) {
                return false;
            }

            $role = $person->roleIn($organization);

            // Admins leave through `RequestMembershipLeave`, which needs an
            // owner's approval; only owners and members detach immediately.
            if ($role === null || $role->isAdmin() || ($role->isOwner() && $organization->activeOwnerCount() === 1)) {
                return false;
            }

            $relationship = OrganizationRelationship::query()
                ->forOrganization($organization)
                ->whereStakeholder($person, OrganizationRelationshipType::Member)
                ->first();

            $period = $relationship !== null
                ? $this->timeline->close($relationship, RelationshipPeriodReason::Left, $user)
                : null;

            $organization->members()->detach($person->getKey());

            event(new MemberLeft($organization, $user, $person, 'self', period: $period));

            return true;
        });
    }
}
