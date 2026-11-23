<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Models\Organization;

final class FindRootTenant
{
    /**
     * Resolve the root tenant organization.
     */
    public function handle(): ?Organization
    {
        return Organization::query()->rootTenant()->first();
    }
}
