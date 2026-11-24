<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Holds the active workspace organization for the current session.
 */
final class CurrentOrganization
{
    public const SESSION_KEY = 'current_organization_id';

    /**
     * Resolve the organization the user is currently working in.
     */
    public function get(): ?Organization
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return null;
        }

        if ($organizationId = session(self::SESSION_KEY)) {
            return $user->profile->organizations()->whereKey($organizationId)->first();
        }

        return $user->defaultOrganization;
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
