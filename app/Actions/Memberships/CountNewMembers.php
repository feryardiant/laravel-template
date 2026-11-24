<?php

declare(strict_types=1);

namespace App\Actions\Memberships;

use App\Models\Organization;

/**
 * Counts members whose membership started inside the range.
 */
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
            ->membershipStartedBetween($range)
            ->membershipNotFinished()
            ->count();
    }
}
