<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Builders\OrganizationRelationshipBuilder;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Reads a tenant's contact relationships and reusable counterparties.
 */
final class ContactRepository
{
    /**
     * Paginate the tenant's external contacts.
     *
     * @return LengthAwarePaginator<int, OrganizationRelationship>
     */
    public function get(Organization $tenant, string $search = '', int $perPage = 10): LengthAwarePaginator
    {
        $term = trim($search);

        return OrganizationRelationship::query()
            ->forOrganization($tenant)
            ->externals()
            ->select('organization_relationships.*')
            ->hasStakeholder()
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

    /**
     * List active external contacts finishing inside the range.
     *
     * @param  array{start: string, end: string}  $range
     * @return Collection<int, OrganizationRelationship>
     */
    public function getExpiring(Organization $organization, array $range, int $limit = 5): Collection
    {
        return OrganizationRelationship::query()
            ->forOrganization($organization)
            ->externals()
            ->notExpired()
            ->finishingBetween($range['start'], $range['end'])
            ->started()
            ->withStakeholder()
            ->orderBy('finish_date')
            ->limit($limit)
            ->get();
    }

    /**
     * Count active external contacts finishing inside the range.
     *
     * @param  array{start: string, end: string}  $range
     */
    public function countExpiring(Organization $organization, array $range): int
    {
        return OrganizationRelationship::query()
            ->forOrganization($organization)
            ->externals()
            ->notExpired()
            ->finishingBetween($range['start'], $range['end'])
            ->started()
            ->count();
    }

    /**
     * Count external contacts whose relationship started inside the range.
     *
     * @param  array{start: string, end: string}  $range
     */
    public function countRecent(Organization $organization, array $range): int
    {
        return OrganizationRelationship::query()
            ->forOrganization($organization)
            ->externals()
            ->startedBetween($range['start'], $range['end'])
            ->count();
    }

    /**
     * Resolve a contact by its stakeholder UUID.
     */
    public function findByUuid(Organization $tenant, string $uuid): OrganizationRelationship
    {
        return $this->query($tenant)
            ->hasStakeholder($uuid)
            ->firstOrFail();
    }

    /**
     * Resolve a contact by its relationship id.
     */
    public function findById(Organization $tenant, int $contactId): OrganizationRelationship
    {
        return $this->query($tenant)
            ->hasStakeholder()
            ->whereKey($contactId)
            ->firstOrFail();
    }

    /**
     * Resolve a reusable contact candidate from its "{type}:{uuid}" reference.
     */
    public function findByReference(Organization $tenant, string $reference): Organization|Person
    {
        [$type, $uuid] = array_pad(explode(':', $reference, 2), 2, null);

        $class = match ($type) {
            'organization' => Organization::class,
            'individual' => Person::class,
            default => throw (new ModelNotFoundException)->setModel(Organization::class),
        };

        if ($uuid === null || $uuid === '') {
            throw (new ModelNotFoundException)->setModel($class);
        }

        /** @var Organization|Person $entity */
        $entity = $class::query()->where('uuid', $uuid)->firstOrFail();

        if ($entity instanceof Organization && $entity->is($tenant)) {
            throw (new ModelNotFoundException)->setModel($class);
        }

        $registeredElsewhere = OrganizationRelationship::query()
            ->forStakeholder($entity)
            ->externals()
            ->outsideOrganization($tenant)
            ->exists();

        $ownExpired = OrganizationRelationship::query()
            ->forOrganization($tenant)
            ->forStakeholder($entity)
            ->externals()
            ->expired()
            ->exists();

        $ownCurrent = OrganizationRelationship::query()
            ->forOrganization($tenant)
            ->forStakeholder($entity)
            ->externals()
            ->notExpired()
            ->exists();

        if ((! $registeredElsewhere && ! $ownExpired) || $ownCurrent) {
            throw (new ModelNotFoundException)->setModel($class);
        }

        return $entity;
    }

    /**
     * Resolve the tenant's expired relationship for a reusable entity.
     */
    public function findExpiredRelationshipFor(Organization $tenant, Organization|Person $entity): ?OrganizationRelationship
    {
        return OrganizationRelationship::query()
            ->forOrganization($tenant)
            ->forStakeholder($entity)
            ->externals()
            ->expired()
            ->first();
    }

    /**
     * Build the tenant-scoped external contact query.
     */
    private function query(Organization $tenant): OrganizationRelationshipBuilder
    {
        return OrganizationRelationship::query()
            ->forOrganization($tenant)
            ->externals()
            ->withStakeholderAndPrimaryContact();
    }
}
