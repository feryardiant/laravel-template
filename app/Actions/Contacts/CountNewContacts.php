<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Models\Organization;
use App\Models\OrganizationRelationship;

/**
 * Counts external contacts whose relationship started inside the range.
 */
final class CountNewContacts
{
    /**
     * Count external contacts whose relationship started inside the range.
     *
     * @param  array{start: string, end: string}  $range
     */
    public function handle(Organization $organization, array $range): int
    {
        return OrganizationRelationship::query()
            ->forOrganization($organization)
            ->externals()
            ->startedBetween($range['start'], $range['end'])
            ->count();
    }
}
