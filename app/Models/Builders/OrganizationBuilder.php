<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<Organization>
 */
final class OrganizationBuilder extends Builder
{
    /**
     * Limit the query to root tenant organizations.
     */
    public function rootTenant(): static
    {
        return $this->where('is_tenant', true)->whereNull('root_organization_id');
    }
}
