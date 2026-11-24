<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\Models\OrganizationRelationship;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<OrganizationRelationship>
 */
final class OrganizationRelationshipBuilder extends Builder
{
    use Concerns\ForOrganization;
    use Concerns\ForStakeholder;

    /**
     * Limit the query to relationships that have started or have no start date.
     */
    public function started(): static
    {
        return $this->where(fn (Builder $query): Builder => $query
            ->whereNull('start_date')
            ->orWhereDate('start_date', '<=', today()));
    }

    /**
     * Limit the query to relationships that started inside the range.
     */
    public function startedBetween(string $start, string $end): static
    {
        return $this->whereDate('start_date', '>=', $start)
            ->whereDate('start_date', '<=', $end);
    }

    /**
     * Limit the query to relationships that finish inside the range.
     */
    public function finishingBetween(string $start, string $end): static
    {
        return $this->whereDate('finish_date', '>=', $start)
            ->whereDate('finish_date', '<=', $end);
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
