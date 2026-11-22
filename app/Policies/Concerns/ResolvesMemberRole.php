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
    protected function memberRole(User $user, Organization $organization): ?Role
    {
        return $user->roleIn($organization);
    }

    protected function isManager(User $user, Organization $organization): bool
    {
        $role = $this->memberRole($user, $organization);

        return $role === Role::Owner || $role === Role::Admin;
    }

    protected function isOwner(User $user, Organization $organization): bool
    {
        return $this->memberRole($user, $organization) === Role::Owner;
    }
}
