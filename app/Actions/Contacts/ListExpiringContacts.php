<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Models\Organization;
use App\Models\OrganizationRelationship;
use Illuminate\Database\Eloquent\Collection;

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
        return OrganizationRelationship::query()
            ->forOrganization($organization)
            ->externals()
            ->notExpired()
            ->finishingBetween($range)
            ->started()
            ->withStakeholder()
            ->orderBy('finish_date')
            ->limit($limit)
            ->get();
    }
}
