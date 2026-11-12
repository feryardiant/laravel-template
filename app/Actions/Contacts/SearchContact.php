<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use Illuminate\Database\Eloquent\Collection;

class SearchContact
{
    /**
     * Find existing external counterparties that match the given name.
     *
     * The current tenant's own contacts and the tenant itself are excluded, so a
     * candidate can be attached without creating a duplicate relationship.
     *
     * @return Collection<int, Organization|Person>
     */
    public function handle(Organization $tenant, string $search, int $limit = 10): Collection
    {
        $term = trim($search);

        if ($term === '') {
            return new Collection;
        }

        $organizations = Organization::query()
            ->whereKeyNot($tenant->getKey())
            ->whereIn(
                'id',
                OrganizationRelationship::query()
                    ->external()
                    ->where('organization_id', '!=', $tenant->getKey())
                    ->where('stakeholder_type', Organization::class)
                    ->select('stakeholder_id'),
            )
            ->whereNotIn(
                'id',
                OrganizationRelationship::query()
                    ->forOrganization($tenant)
                    ->external()
                    ->where('stakeholder_type', Organization::class)
                    ->select('stakeholder_id'),
            )
            ->whereLike('name', "%{$term}%")
            ->orderBy('name')
            ->limit($limit)
            ->get();

        $people = Person::query()
            ->whereIn(
                'id',
                OrganizationRelationship::query()
                    ->external()
                    ->where('organization_id', '!=', $tenant->getKey())
                    ->where('stakeholder_type', Person::class)
                    ->select('stakeholder_id'),
            )
            ->whereNotIn(
                'id',
                OrganizationRelationship::query()
                    ->forOrganization($tenant)
                    ->external()
                    ->where('stakeholder_type', Person::class)
                    ->select('stakeholder_id'),
            )
            ->whereLike('name', "%{$term}%")
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return $organizations
            ->concat($people)
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->take($limit)
            ->values();
    }
}
