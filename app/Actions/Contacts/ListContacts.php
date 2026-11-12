<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListContacts
{
    /**
     * Paginate the tenant's external contacts.
     *
     * @return LengthAwarePaginator<int, OrganizationRelationship>
     */
    public function handle(Organization $tenant, string $search = '', int $perPage = 10): LengthAwarePaginator
    {
        $term = trim($search);

        return OrganizationRelationship::query()
            ->select('organization_relationships.*')
            ->forOrganization($tenant)
            ->external()
            ->whereHasMorph('stakeholder', [Organization::class, Person::class])
            ->leftJoin('people', function (JoinClause $join): void {
                $join->on('people.id', '=', 'organization_relationships.stakeholder_id')
                    ->where('organization_relationships.stakeholder_type', '=', (new Person)->getMorphClass());
            })
            ->leftJoin('organizations', function (JoinClause $join): void {
                $join->on('organizations.id', '=', 'organization_relationships.stakeholder_id')
                    ->where('organization_relationships.stakeholder_type', '=', (new Organization)->getMorphClass());
            })
            ->when($term !== '', fn (Builder $query): Builder => $query->where(function (Builder $query) use ($term): void {
                $query->whereLike('people.name', "%{$term}%")
                    ->orWhereLike('organizations.name', "%{$term}%")
                    ->orWhereIn(
                        'organizations.contact_id',
                        Person::withTrashed()->select('id')->whereLike('name', "%{$term}%"),
                    );
            }))
            ->orderByRaw('COALESCE(people.created_at, organizations.created_at) desc')
            ->orderByDesc('organization_relationships.id')
            ->with(['stakeholder' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                Organization::class => ['primaryContact'],
            ])])
            ->paginate($perPage);
    }
}
