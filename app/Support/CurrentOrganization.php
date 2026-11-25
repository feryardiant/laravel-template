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
    public function switch(Organization|int $organization): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        $organization = $organization instanceof Organization
            ? $organization->getKey()
            : $organization;

        if (! $user->profile->organizations()->whereKey($organization)->exists()) {
            return false;
        }

        session([self::SESSION_KEY => $organization]);

        return true;
    }
}
