<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class FindUserOrganization
{
    /**
     * Resolve one of the user's organizations without failing.
     */
    public function find(User $user, int $organizationId): ?Organization
    {
        return $user->organizations()->firstWhere('id', $organizationId);
    }

    /**
     * Resolve one of the user's organizations or fail.
     */
    public function handle(User $user, int $organizationId): Organization
    {
        $organization = $this->find($user, $organizationId);

        if ($organization === null) {
            throw (new ModelNotFoundException)->setModel(Organization::class);
        }

        return $organization;
    }
}
