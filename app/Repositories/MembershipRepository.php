<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Reads an organization's memberships and credentialed members.
 */
final class MembershipRepository
{
    /**
     * Paginate the organization members, optionally filtered by name or email.
     *
     * @return LengthAwarePaginator<int, Person&object{pivot: OrganizationRelationship}>
     */
    public function get(
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

    /**
     * List the most recent active members in the range.
     *
     * @param  array{start: string, end: string}  $range
     * @return Collection<int, Person>
     */
    public function getRecent(Organization $organization, array $range, int $limit = 5): Collection
    {
        return $organization->members()
            ->membershipStartedBetween($range)
            ->membershipNotFinished()
            ->with('user')
            ->orderByDesc('organization_relationships.start_date')
            ->limit($limit)
            ->get();
    }

    /**
     * Resolve several members of the organization.
     *
     * @param  array<int, int|string>  $personIds
     * @return Collection<int, Person>
     */
    public function getInOrganization(Organization $organization, array $personIds): Collection
    {
        return $organization->members()
            ->whereKey($personIds)
            ->get();
    }

    /**
     * Count active members whose membership started inside the range.
     *
     * @param  array{start: string, end: string}  $range
     */
    public function countRecent(Organization $organization, array $range): int
    {
        return $organization->members()
            ->membershipStartedBetween($range)
            ->membershipNotFinished()
            ->count();
    }

    /**
     * List members with a login credential.
     *
     * @return Collection<int, Person>
     */
    public function getCredentialed(
        Organization $organization,
        ?int $excludePersonId = null,
        bool $activeOnly = false,
    ): Collection {
        return $organization->members()
            ->credentialed()
            ->when($excludePersonId !== null, fn (Builder $query): Builder => $query->whereKeyNot($excludePersonId))
            ->when($activeOnly, fn (Builder $query): Builder => $query->membershipActive())
            ->with('user')
            ->orderBy('people.name')
            ->get();
    }

    /**
     * Resolve a member of the organization when present.
     *
     * @param  array<int, string>  $with
     */
    public function findInOrganization(Organization $organization, int $personId, array $with = []): ?Person
    {
        return $organization->members()
            ->with($with)
            ->whereKey($personId)
            ->first();
    }
}
