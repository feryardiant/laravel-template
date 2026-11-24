<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\Models\Person;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<Person>
 */
final class PersonBuilder extends Builder
{
    /**
     * Limit the query to people with a login credential.
     */
    public function credentialed(): static
    {
        return $this->whereHas('user');
    }

    /**
     * Limit a members query to the given name or credential email.
     *
     * Only valid on queries built from a members relation.
     */
    public function membershipSearch(string $term): static
    {
        return $this->where(fn (Builder $query): Builder => $query
            ->whereLike('people.name', "%{$term}%")
            ->orWhereHas('user', fn (Builder $query): Builder => $query
                ->whereLike('users.email', "%{$term}%")));
    }

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
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('organization_relationships.start_date')
                ->orWhereDate('organization_relationships.start_date', '<=', today()))
            ->membershipNotFinished();
    }

    /**
     * Order a members query by membership start date.
     *
     * Only valid on queries built from a members relation.
     */
    public function membershipOrder(string $direction): static
    {
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        return $this->orderBy('organization_relationships.start_date', $direction)
            ->orderBy('people.id');
    }

    /**
     * Limit the query to the given name.
     */
    public function namedLike(string $term): static
    {
        return $this->whereLike('name', "%{$term}%");
    }
}
