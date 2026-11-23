<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Models\Organization;
use App\Models\User;

final class FindUserOrganization
{
    /**
     * Resolve one of the user's organizations or fail.
     */
    public function handle(User $user, int $organizationId): Organization
    {
        $organization = $user->organizations()->firstWhere('id', $organizationId);

        abort_if($organization === null, 404);

        return $organization;
    }
}
