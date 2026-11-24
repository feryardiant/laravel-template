<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Models\Organization;
use App\Models\OrganizationRelationship;

final class CountExpiringContacts
{
    /**
     * Count active external contacts finishing inside the range.
     *
     * @param  array{start: string, end: string}  $range
     */
    public function handle(Organization $organization, array $range): int
    {
        return OrganizationRelationship::query()
            ->forOrganization($organization)
            ->externals()
            ->notExpired()
            ->finishingBetween($range['start'], $range['end'])
            ->started()
            ->count();
    }
}
