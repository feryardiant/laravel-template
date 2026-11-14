<?php

declare(strict_types=1);

namespace App\Actions\Memberships;

use App\Actions\Relationships\RelationshipTimeline;
use App\Enums\ApprovalRequestStatus;
use App\Enums\OrganizationRelationshipType;
use App\Enums\RelationshipPeriodReason;
use App\Events\MemberRemoved;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

final class LeaveOrganization
{
    public function __construct(private RelationshipTimeline $timeline)
    {
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

        $left = DB::transaction(function () use ($person, $organization): bool {
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
                ->whereStakeholder($person)
                ->where('type', OrganizationRelationshipType::Member)
                ->first();

            if ($relationship !== null) {
                $this->timeline->close($relationship, RelationshipPeriodReason::Left);
            }

            $organization->members()->detach($person->getKey());

            ApprovalRequest::query()
                ->pending()
                ->where('organization_id', $organization->getKey())
                ->where('requester_person_id', $person->getKey())
                ->update([
                    'status' => ApprovalRequestStatus::Cancelled,
                    'cancelled_at' => now(),
                ]);

            return true;
        });

        if (! $left) {
            return false;
        }

        try {
            event(new MemberRemoved($organization, $person, null, ! $person->organizations()->exists()));
        } catch (Throwable $exception) {
            report($exception);
        }

        return true;
    }
}
