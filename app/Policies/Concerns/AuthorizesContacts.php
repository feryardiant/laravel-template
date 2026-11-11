<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Enums\Role;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Models\User;
use App\Support\CurrentOrganization;

/**
 * Contact abilities shared by the Organization and Person policies.
 *
 * Contacts are a domain concept over organization_relationships, so the policy
 * is bound to the existing stakeholder models.
 */
trait AuthorizesContacts
{
    public function viewAnyContact(User $user): bool
    {
        return true;
    }

    public function createContact(User $user): bool
    {
        return $this->managesContacts($user);
    }

    public function viewContact(User $user, Organization|Person $entity): bool
    {
        return $this->isCounterpartyOfCurrentOrganization($user, $entity);
    }

    public function updateContact(User $user, Organization|Person $entity): bool
    {
        return $this->managesContacts($user)
            && $this->isCounterpartyOfCurrentOrganization($user, $entity);
    }

    public function deleteContact(User $user, Organization|Person $entity): bool
    {
        return $this->updateContact($user, $entity);
    }

    public function deleteAnyContact(User $user): bool
    {
        return $this->managesContacts($user);
    }

    protected function managesContacts(User $user): bool
    {
        $role = $this->currentRole($user);

        return $role === Role::Owner || $role === Role::Admin;
    }

    protected function isCounterpartyOfCurrentOrganization(User $user, Organization|Person $entity): bool
    {
        $organization = app(CurrentOrganization::class)->get($user);

        if ($organization === null || $user->roleIn($organization) === null) {
            return false;
        }

        return OrganizationRelationship::query()
            ->forOrganization($organization)
            ->external()
            ->where('stakeholder_type', $entity->getMorphClass())
            ->where('stakeholder_id', $entity->getKey())
            ->exists();
    }

    protected function currentRole(User $user): ?Role
    {
        $organization = app(CurrentOrganization::class)->get($user);

        return $organization === null ? null : $user->roleIn($organization);
    }
}
