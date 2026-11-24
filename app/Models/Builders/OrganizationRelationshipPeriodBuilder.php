<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\Models\OrganizationRelationshipPeriod;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<OrganizationRelationshipPeriod>
 */
final class OrganizationRelationshipPeriodBuilder extends Builder
{
    use Concerns\ForOrganization;
    use Concerns\ForStakeholder;

    /**
     * Order the stints newest first.
     */
    public function latestFirst(): static
    {
        return $this->orderByDesc('start_date')->orderByDesc('id');
    }
}
