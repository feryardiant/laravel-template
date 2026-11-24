<?php

declare(strict_types=1);

namespace App\Models\Builders\Concerns;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Builder<Model>
 */
trait ForOrganization
{
    /**
     * Limit the query to the given tenant.
     */
    public function forOrganization(Organization|int $organization): static
    {
        $organization = $organization instanceof Organization ? $organization->getKey() : $organization;

        return $this->where('organization_id', $organization);
    }
}
