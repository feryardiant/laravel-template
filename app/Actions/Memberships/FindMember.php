<?php

declare(strict_types=1);

namespace App\Actions\Memberships;

use App\Models\Organization;
use App\Models\Person;
use Illuminate\Database\Eloquent\Collection;

/**
 * Resolves members of an organization by person id.
 */
final class FindMember
{
    /**
     * Resolve a member of the organization or fail.
     *
     * @param  array<int, string>  $with
     */
    public function inOrganization(Organization $organization, int $personId, array $with = []): Person
    {
        return $organization->members()
            ->with($with)
            ->whereKey($personId)
            ->firstOrFail();
    }

    /**
     * Resolve a member of the organization when present.
     */
    public function findInOrganization(Organization $organization, int $personId): ?Person
    {
        return $organization->members()
            ->whereKey($personId)
            ->first();
    }

    /**
     * Resolve several members of the organization.
     *
     * @param  array<int, int|string>  $personIds
     * @return Collection<int, Person>
     */
    public function manyInOrganization(Organization $organization, array $personIds): Collection
    {
        return $organization->members()
            ->whereKey($personIds)
            ->get();
    }
}
