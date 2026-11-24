<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Models\Organization;
use App\Models\OrganizationRelationship;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class ListExpiringContacts
{
    /**
     * List active external contacts finishing inside the range.
     *
     * @param  array{start: string, end: string}  $range
     * @return Collection<int, OrganizationRelationship>
     */
    public function handle(Organization $organization, array $range, int $limit = 5): Collection
    {
        return OrganizationRelationship::forOrganization($organization)
            ->onlyExternals()
            ->notExpired()
            ->whereDate('finish_date', '>=', $range['start'])
            ->whereDate('finish_date', '<=', $range['end'])
            ->where(fn ($query) => $query
                ->whereNull('start_date')
                ->orWhereDate('start_date', '<=', today()))
            ->with(['stakeholder' => fn (MorphTo $morphTo) => $morphTo->withTrashed()])
            ->orderBy('finish_date')
            ->limit($limit)
            ->get();
    }
}
