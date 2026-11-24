<?php

declare(strict_types=1);

namespace App\Actions\Memberships;

use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Paginates the organization members with optional search and sorting.
 */
final class ListMembers
{
    /**
     * Paginate the organization members, optionally filtered by name or email.
     *
     * @return LengthAwarePaginator<int, Person&object{pivot: OrganizationRelationship}>
     */
    public function handle(
        Organization $organization,
        string $search = '',
        string $sortDirection = 'desc',
        int $perPage = 10,
    ): LengthAwarePaginator {
        $term = trim($search);

        return $organization->members()
            ->with('user')
            ->when($term !== '', fn (Builder $query): Builder => $query->membershipSearch($term))
            ->membershipOrder($sortDirection)
            ->paginate($perPage);
    }
}
