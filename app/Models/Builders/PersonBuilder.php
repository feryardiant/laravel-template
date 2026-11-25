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
    use Concerns\ForMembership;

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
