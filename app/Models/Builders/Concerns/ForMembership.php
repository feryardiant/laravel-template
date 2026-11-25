<?php

declare(strict_types=1);

namespace App\Models\Builders\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Builder<Model>
 */
trait ForMembership
{
    /**
     * Limit a members query to memberships that started inside the range.
     *
     * Only valid on queries built from a members relation.
     *
     * @param  array{start: string, end: string}  $range
     */
    public function membershipStartedBetween(array $range): static
    {
        return $this->whereDate('organization_relationships.start_date', '>=', $range['start'])
            ->whereDate('organization_relationships.start_date', '<=', $range['end']);
    }

    /**
     * Limit a members query to memberships that have not finished.
     *
     * Only valid on queries built from a members relation.
     */
    public function membershipStarted(): static
    {
        return $this->where(fn (Builder $query): Builder => $query
            ->whereNull('organization_relationships.start_date')
            ->orWhereDate('organization_relationships.start_date', '<=', today()));
    }

    /**
     * Limit a members query to memberships that have not finished.
     *
     * Only valid on queries built from a members relation.
     */
    public function membershipNotFinished(): static
    {
        return $this->where(fn (Builder $query): Builder => $query
            ->whereNull('organization_relationships.finish_date')
            ->orWhereDate('organization_relationships.finish_date', '>=', today()));
    }

    /**
     * Limit a members query to memberships active today.
     *
     * Only valid on queries built from a members relation.
     */
    public function membershipActive(): static
    {
        return $this
            ->membershipStarted()
            ->membershipNotFinished();
    }
}
