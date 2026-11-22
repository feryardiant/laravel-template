<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Models\User;
use App\Support\Feature\ContactDeletion;

/**
 * Contact abilities shared by the Organization and Person policies.
 *
 * Contacts are a domain concept over organization_relationships, so the policy
 * is bound to the existing stakeholder models.
 */
trait AuthorizesContacts
{
    public function viewContact(User $user, Organization|Person $entity, Organization $organization): bool
    {
        return $this->isCounterparty($user, $entity, $organization);
    }

    public function updateContact(User $user, Organization|Person $entity, Organization $organization): bool
    {
        return ! $entity->trashed()
            && $this->isManager($user, $organization)
            && $this->isCounterparty($user, $entity, $organization);
    }

    public function deleteContact(User $user, Organization|Person $entity, Organization $organization): bool
    {
        return ContactDeletion::enabled($organization)
            && $this->isManager($user, $organization)
            && $this->isCounterparty($user, $entity, $organization);
    }

    protected function isCounterparty(User $user, Organization|Person $entity, Organization $organization): bool
    {
        if ($this->memberRole($user, $organization) === null) {
            return false;
        }

        return OrganizationRelationship::query()
            ->forOrganization($organization)
            ->external()
            ->whereStakeholder($entity)
            ->exists();
    }
}
