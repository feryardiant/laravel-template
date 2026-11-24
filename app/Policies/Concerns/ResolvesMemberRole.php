<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;

/**
 * Resolve the actor's role inside an organization for policy checks.
 */
trait ResolvesMemberRole
{
    /**
     * Resolve the actor's role inside the organization, if any.
     */
    protected function memberRole(User $user, Organization $organization): ?Role
    {
        return $user->roleIn($organization);
    }

    /**
     * Determine whether the actor is an owner or admin of the organization.
     */
    protected function isManager(User $user, Organization $organization): bool
    {
        return $this->memberRole($user, $organization)?->isManager() === true;
    }

    /**
     * Determine whether the actor owns the organization.
     */
    protected function isOwner(User $user, Organization $organization): bool
    {
        return $this->memberRole($user, $organization) === Role::Owner;
    }
}
