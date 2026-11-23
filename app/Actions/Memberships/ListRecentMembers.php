<?php

declare(strict_types=1);

namespace App\Actions\Memberships;

use App\Models\Organization;
use App\Models\Person;
use Illuminate\Database\Eloquent\Collection;

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
            ->whereDate('organization_relationships.start_date', '>=', $range['start'])
            ->whereDate('organization_relationships.start_date', '<=', $range['end'])
            ->where(fn ($query) => $query
                ->whereNull('organization_relationships.finish_date')
                ->orWhereDate('organization_relationships.finish_date', '>=', today()))
            ->with('user')
            ->orderByDesc('organization_relationships.start_date')
            ->limit($limit)
            ->get();
    }
}
