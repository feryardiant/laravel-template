<?php

declare(strict_types=1);

namespace App\Actions\Memberships;

use App\Models\Organization;

final class CountNewMembers
{
    /**
     * Count active members whose membership started inside the range.
     *
     * @param  array{start: string, end: string}  $range
     */
    public function handle(Organization $organization, array $range): int
    {
        return $organization->members()
            ->whereDate('organization_relationships.start_date', '>=', $range['start'])
            ->whereDate('organization_relationships.start_date', '<=', $range['end'])
            ->where(fn ($query) => $query
                ->whereNull('organization_relationships.finish_date')
                ->orWhereDate('organization_relationships.finish_date', '>=', today()))
            ->count();
    }
}
