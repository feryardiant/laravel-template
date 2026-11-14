<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class SearchContact
{
    /**
     * Find external counterparties that match the given name.
     *
     * Candidates are entities registered by another tenant or the current
     * tenant's own expired contacts, so they can be attached or renewed without
     * creating a duplicate relationship.
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
            ->where(fn (Builder $query) => $query
                ->whereIn('id', $this->stakeholderIds($tenant, Organization::class, own: false))
                ->orWhereIn('id', $this->stakeholderIds($tenant, Organization::class, own: true)))
            ->whereNotIn('id', $this->currentStakeholderIds($tenant, Organization::class))
            ->whereLike('name', "%{$term}%")
            ->orderBy('name')
            ->limit($limit)
            ->get();

        $people = Person::query()
            ->where(fn (Builder $query) => $query
                ->whereIn('id', $this->stakeholderIds($tenant, Person::class, own: false))
                ->orWhereIn('id', $this->stakeholderIds($tenant, Person::class, own: true)))
            ->whereNotIn('id', $this->currentStakeholderIds($tenant, Person::class))
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

    /**
     * Stakeholder ids registered by another tenant or the tenant's expired rows.
     *
     * @param  class-string<Organization|Person>  $stakeholder
     * @return Builder<OrganizationRelationship>
     */
    private function stakeholderIds(Organization $tenant, string $stakeholder, bool $own): Builder
    {
        $query = OrganizationRelationship::query()
            ->external()
            ->where('stakeholder_type', $stakeholder)
            ->select('stakeholder_id');

        if ($own) {
            return $query->forOrganization($tenant)->expired();
        }

        return $query->where('organization_id', '!=', $tenant->getKey());
    }

    /**
     * Stakeholder ids the tenant already has an unfinished relationship with.
     *
     * @param  class-string<Organization|Person>  $stakeholder
     * @return Builder<OrganizationRelationship>
     */
    private function currentStakeholderIds(Organization $tenant, string $stakeholder): Builder
    {
        return OrganizationRelationship::query()
            ->external()
            ->forOrganization($tenant)
            ->notExpired()
            ->where('stakeholder_type', $stakeholder)
            ->select('stakeholder_id');
    }
}
