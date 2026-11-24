<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Models\Organization;

/**
 * Resolves an organization by id without checking membership.
 */
final class FindOrganization
{
    /**
     * Resolve any organization by id, regardless of membership.
     */
    public function handle(int $organizationId): Organization
    {
        return Organization::query()->findOrFail($organizationId);
    }
}
