<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Models\Builders\OrganizationRelationshipBuilder;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class FindContact
{
    /**
     * Resolve a contact by its stakeholder UUID.
     */
    public function byUuid(Organization $tenant, string $uuid): OrganizationRelationship
    {
        return $this->query($tenant)
            ->hasStakeholder($uuid)
            ->firstOrFail();
    }

    /**
     * Resolve a contact by its relationship id.
     */
    public function byId(Organization $tenant, int $contactId): OrganizationRelationship
    {
        return $this->query($tenant)
            ->hasStakeholder()
            ->whereKey($contactId)
            ->firstOrFail();
    }

    /**
     * Resolve a reusable contact candidate from its "{type}:{uuid}" reference.
     */
    public function byReference(Organization $tenant, string $reference): Organization|Person
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
    public function expiredRelationshipFor(Organization $tenant, Organization|Person $entity): ?OrganizationRelationship
    {
        return OrganizationRelationship::query()
            ->forOrganization($tenant)
            ->forStakeholder($entity)
            ->externals()
            ->expired()
            ->first();
    }

    private function query(Organization $tenant): OrganizationRelationshipBuilder
    {
        return OrganizationRelationship::query()
            ->forOrganization($tenant)
            ->externals()
            ->withStakeholderAndPrimaryContact();
    }
}
