<?php

declare(strict_types=1);

namespace App\Actions\Memberships;

use App\Models\Organization;
use App\Models\Person;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lists the members whose membership started inside the range.
 */
final class ListRecentMembers
{
    /**
     * List the most recent active members in the range.
     *
     * @param  array{start: string, end: string}  $range
     * @return Collection<int, Person>
     */
    public function handle(Organization $organization, array $range, int $limit = 5): Collection
    {
        return $organization->members()
            ->membershipStartedBetween($range)
            ->membershipNotFinished()
            ->with('user')
            ->orderByDesc('organization_relationships.start_date')
            ->limit($limit)
            ->get();
    }
}
