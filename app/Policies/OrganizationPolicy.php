<?php

declare(strict_types=1);

namespace App\Policies;

use App\Actions\Organizations\ListSubsidiaries;
use App\Models\Organization;
use App\Models\User;
use App\Policies\Concerns\AuthorizesContacts;
use App\Policies\Concerns\ResolvesMemberRole;
use App\Support\Feature\ContactDeletion;

class OrganizationPolicy
{
    use AuthorizesContacts, ResolvesMemberRole;

    /**
     * Determine whether the user may update the organization settings.
     */
    public function update(User $user, Organization $organization): bool
    {
        return $this->isManager($user, $organization);
    }

    /**
     * Determine whether the user may manage the organization members.
     */
    public function manageMembers(User $user, Organization $organization): bool
    {
        return $this->isManager($user, $organization);
    }

    /**
     * Determine whether the user may toggle the organization features.
     */
    public function manageFeatures(User $user, Organization $organization): bool
    {
        return $this->isManager($user, $organization);
    }

    /**
     * Determine whether the user may view the root tenant's subsidiaries.
     */
    public function viewSubsidiaries(User $user, Organization $organization): bool
    {
        return $organization->isRootTenant() && $this->isManager($user, $organization);
    }

    /**
     * Determine whether the user may create an organization under the parent.
     */
    public function createSubsidiary(User $user, Organization $organization): bool
    {
        return $this->isOwner($user, $organization);
    }

    /**
     * Determine whether the user may read the organization audit log.
     */
    public function viewAudit(User $user, Organization $organization): bool
    {
        return $this->isManager($user, $organization);
    }

    /**
     * Determine whether the user may read the organization approval history.
     */
    public function viewApprovals(User $user, Organization $organization): bool
    {
        return $this->isOwner($user, $organization);
    }

    /**
     * Determine whether the user may transfer ownership of the organization.
     */
    public function transferOwnership(User $user, Organization $organization): bool
    {
        return $this->isOwner($user, $organization);
    }

    /**
     * Determine whether the user may delete the organization.
     *
     * A sole owner may delete their organization; a root owner may also delete
     * a subsidiary in the root tenant's tree. The root tenant is never deletable.
     */
    public function delete(User $user, Organization $organization): bool
    {
        if ($organization->isRootTenant()) {
            return false;
        }

        if ($this->isOwner($user, $organization) && $organization->activeOwnerCount() === 1) {
            return true;
        }

        $root = Organization::query()->rootTenant()->first();

        if ($root === null || ! $this->isOwner($user, $root)) {
            return false;
        }

        return app(ListSubsidiaries::class)
            ->handle($root)
            ->contains(fn (array $row): bool => $row['organization']->is($organization));
    }

    /**
     * Determine whether the user may create contacts for the organization.
     */
    public function createContact(User $user, Organization $organization): bool
    {
        return $this->isManager($user, $organization);
    }

    /**
     * Determine whether the user may delete any contact of the organization.
     */
    public function deleteAnyContact(User $user, Organization $organization): bool
    {
        return ContactDeletion::enabled($organization)
            && $this->isManager($user, $organization);
    }
}
