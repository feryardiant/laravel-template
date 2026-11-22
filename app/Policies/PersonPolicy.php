<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Models\User;
use App\Policies\Concerns\AuthorizesContacts;
use App\Policies\Concerns\ResolvesMemberRole;
use App\Support\Feature\MemberDeletion;

class PersonPolicy
{
    use AuthorizesContacts, ResolvesMemberRole;

    /**
     * Determine whether the user may manage the member in the organization.
     *
     * Any existing membership row is manageable, including a scheduled or
     * expired one, so managers can clean up the rows the list already shows.
     * Managers may act on members, and only an owner may act on another owner.
     */
    public function manageMember(User $user, Person $person, Organization $organization): bool
    {
        if (! $this->isManager($user, $organization)) {
            return false;
        }

        $membership = OrganizationRelationship::query()
            ->forOrganization($organization)
            ->whereStakeholder($person, OrganizationRelationshipType::Member)
            ->first();

        if (! $membership instanceof OrganizationRelationship) {
            return false;
        }

        return $membership->role?->isOwner() !== true
            || $this->isOwner($user, $organization);
    }

    /**
     * Determine whether the user may change the member's role.
     */
    public function changeRole(User $user, Person $person, Organization $organization, Role $role): bool
    {
        return ! $role->isOwner()
            && ! $person->is($user->person)
            && $this->manageMember($user, $person, $organization);
    }

    /**
     * Determine whether the user may remove the member from the organization.
     */
    public function removeMember(User $user, Person $person, Organization $organization): bool
    {
        return ! $person->is($user->person)
            && MemberDeletion::enabled($organization)
            && $this->manageMember($user, $person, $organization);
    }
}
