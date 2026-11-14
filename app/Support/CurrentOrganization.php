<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Organization;
use App\Models\User;

final class CurrentOrganization
{
    public const SESSION_KEY = 'current_organization_id';

    /**
     * Resolve the organization the user is currently working in.
     */
    public function get(?User $user): ?Organization
    {
        $person = $user?->person;

        if ($person === null) {
            return null;
        }

        $organizationId = session(self::SESSION_KEY);

        if ($organizationId !== null) {
            $organization = $person->organizations()->whereKey($organizationId)->first();

            if ($organization !== null) {
                return $organization;
            }
        }

        return $person->defaultOrganization() ?? $person->organizations()->first();
    }

    /**
     * Store the given organization as the user's current one.
     */
    public function switch(User $user, Organization $organization): bool
    {
        $person = $user->person;

        if ($person === null || ! $person->organizations()->whereKey($organization->getKey())->exists()) {
            return false;
        }

        session([self::SESSION_KEY => $organization->getKey()]);

        return true;
    }
}
