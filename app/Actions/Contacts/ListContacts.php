<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use Illuminate\Database\Eloquent\Builder;
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
            ->forOrganization($tenant)
            ->externals()
            ->select('organization_relationships.*')
            ->whereHasMorph(
                'stakeholder',
                [Organization::class, Person::class],
                function (Builder $query, string $type): void {
                    if ($type === Organization::class) {
                        $query->withTrashed();
                    }
                }
            )
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
            ->withStakeholderAndPrimaryContact()
            ->paginate($perPage);
    }
}
