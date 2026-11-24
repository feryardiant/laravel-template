<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\Enums\OrganizationRelationshipType;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<OrganizationRelationship>
 */
final class OrganizationRelationshipBuilder extends Builder
{
    use Concerns\ForOrganization;

    public function whereStakeholder(Organization|Person $entity, ?OrganizationRelationshipType $type = null): static
    {
        $this
            ->where('stakeholder_type', $entity->getMorphClass())
            ->where('stakeholder_id', $entity->getKey());

        if ($type) {
            $this->where('type', $type);
        }

        return $this;
    }

    /**
     * Limit the query to external contact relationships.
     */
    public function internals(): static
    {
        return $this->whereIn('type', OrganizationRelationshipType::internals());
    }

    /**
     * Limit the query to external contact relationships.
     */
    public function externals(): static
    {
        return $this->whereIn('type', OrganizationRelationshipType::externals());
    }

    /**
     * Limit the query to relationships whose window has ended.
     */
    public function expired(): static
    {
        return $this->whereNotNull('finish_date')->whereDate('finish_date', '<', today());
    }

    /**
     * Limit the query to relationships that have not ended (active or scheduled).
     */
    public function notExpired(): static
    {
        return $this->where(fn (Builder $query): Builder => $query
            ->whereNull('finish_date')
            ->orWhereDate('finish_date', '>=', today()));
    }
}
