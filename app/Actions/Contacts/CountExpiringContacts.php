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
            ->external()
            ->notExpired()
            ->whereDate('finish_date', '>=', $range['start'])
            ->whereDate('finish_date', '<=', $range['end'])
            ->where(fn ($query) => $query
                ->whereNull('start_date')
                ->orWhereDate('start_date', '<=', today()))
            ->count();
    }
}
