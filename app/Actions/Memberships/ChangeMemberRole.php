<?php

declare(strict_types=1);

namespace App\Actions\Memberships;

use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Events\MemberRoleChanged;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Models\User;

final class ChangeMemberRole
{
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

        $currentRole = $person->roleIn($organization);

        if ($currentRole === null || $currentRole === $newRole) {
            return false;
        }

        if (! $this->canAct($organization, $person, $actor, $newRole)) {
            return false;
        }

        $this->updateRole($organization, $person, $newRole);

        event(new MemberRoleChanged($organization, $person, $actor, $currentRole, $newRole));

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

    private function canAct(Organization $organization, Person $person, User $actor, Role $newRole): bool
    {
        $actorRole = $actor->roleIn($organization);

        if ($actorRole === null || (! $actorRole->isOwner() && ! $actorRole->isAdmin())) {
            return false;
        }

        if ($person->user_id === $actor->getKey()) {
            return false;
        }

        $targetRole = $person->roleIn($organization);

        if ($targetRole?->isOwner() && ! $actorRole->isOwner()) {
            return false;
        }

        return ! $newRole->isOwner();
    }

    private function updateRole(Organization $organization, Person $person, Role $role): void
    {
        OrganizationRelationship::query()
            ->forOrganization($organization)
            ->whereStakeholder($person)
            ->where('type', OrganizationRelationshipType::Member)
            ->update(['role' => $role]);

        // A role change invalidates an admin's pending request to leave.
        ApprovalRequest::query()
            ->pending()
            ->where('organization_id', $organization->getKey())
            ->where('type', ApprovalRequestType::MembershipLeave)
            ->where('requester_person_id', $person->getKey())
            ->update([
                'status' => ApprovalRequestStatus::Cancelled,
                'cancelled_at' => now(),
            ]);
    }
}
