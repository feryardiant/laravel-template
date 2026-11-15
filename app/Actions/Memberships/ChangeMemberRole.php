<?php

declare(strict_types=1);

namespace App\Actions\Memberships;

use App\Actions\Relationships\RelationshipTimeline;
use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Enums\OrganizationRelationshipType;
use App\Enums\RelationshipPeriodReason;
use App\Enums\Role;
use App\Events\Approvals\ApprovalCancelled;
use App\Events\Memberships\MemberRoleChanged;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\OrganizationRelationshipPeriod;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ChangeMemberRole
{
    public function __construct(
        private RelationshipTimeline $timeline,
    ) {
        // .
    }

    public function promote(Organization $organization, Person $person, User $actor): bool
    {
        return $this->change($organization, $person, $actor, Role::Member, Role::Admin);
    }

    public function demote(Organization $organization, Person $person, User $actor): bool
    {
        return $this->change($organization, $person, $actor, Role::Admin, Role::Member);
    }

    /**
     * Set a member's role within the organization.
     */
    public function setRole(Organization $organization, Person $person, Role $newRole, User $actor): bool
    {
        if ($newRole->isOwner()) {
            return false;
        }

        $currentRole = null;

        $changed = DB::transaction(function () use ($organization, $person, $newRole, $actor, &$currentRole): bool {
            $relationship = OrganizationRelationship::query()
                ->forOrganization($organization)
                ->whereStakeholder($person)
                ->where('type', OrganizationRelationshipType::Member)
                ->lockForUpdate()
                ->first();

            $currentRole = $relationship?->role;

            if ($currentRole === null
                || $currentRole === $newRole
                || ! $this->canAct($organization, $person, $actor, $newRole, $currentRole)) {
                return false;
            }

            $this->updateRole($organization, $person, $newRole, $actor, $relationship);

            $period = $this->openPeriod($organization, $person);

            event(new MemberRoleChanged($organization, $currentRole, $newRole, $person, $actor, $period));

            return true;
        });

        if (! $changed || $currentRole === null) {
            return false;
        }

        return true;
    }

    private function change(
        Organization $organization,
        Person $person,
        User $actor,
        Role $expected,
        Role $newRole,
    ): bool {
        if ($person->roleIn($organization) !== $expected) {
            return false;
        }

        return $this->setRole($organization, $person, $newRole, $actor);
    }

    private function canAct(Organization $organization, Person $person, User $actor, Role $newRole, Role $currentRole): bool
    {
        $actorRole = $actor->roleIn($organization);

        if ($actorRole === null || (! $actorRole->isOwner() && ! $actorRole->isAdmin())) {
            return false;
        }

        if ($person->user_id === $actor->getKey()) {
            return false;
        }

        if ($currentRole->isOwner() && ! $actorRole->isOwner()) {
            return false;
        }

        return ! $newRole->isOwner();
    }

    private function openPeriod(Organization $organization, Person $person): ?OrganizationRelationshipPeriod
    {
        return OrganizationRelationshipPeriod::query()
            ->where('organization_id', $organization->getKey())
            ->where('stakeholder_type', $person->getMorphClass())
            ->where('stakeholder_id', $person->getKey())
            ->whereNull('closed_at')
            ->latest('id')
            ->first();
    }

    private function updateRole(
        Organization $organization,
        Person $person,
        Role $role,
        User $actor,
        OrganizationRelationship $relationship,
    ): void {
        $this->timeline->close($relationship, RelationshipPeriodReason::RoleChanged, $actor);

        $relationship->update(['role' => $role]);

        $this->timeline->open($relationship->refresh(), today());

        // A role change invalidates an admin's pending request to leave.
        $pending = ApprovalRequest::query()
            ->pending()
            ->where('organization_id', $organization->getKey())
            ->where('type', ApprovalRequestType::MembershipLeave)
            ->where('requester_person_id', $person->getKey())
            ->get();

        foreach ($pending as $request) {
            $cancelled = ApprovalRequest::query()
                ->whereKey($request->getKey())
                ->where('status', ApprovalRequestStatus::Pending)
                ->update([
                    'status' => ApprovalRequestStatus::Cancelled,
                    'cancelled_at' => now(),
                ]);

            if ($cancelled === 0) {
                continue;
            }

            event(new ApprovalCancelled($request, $actor, 'role_changed'));
        }
    }
}
