<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FindContact
{
    /**
     * Resolve a contact by its stakeholder UUID.
     */
    public function byUuid(Organization $tenant, string $uuid): OrganizationRelationship
    {
        return $this->query($tenant)
            ->whereHasMorph(
                'stakeholder',
                [Organization::class, Person::class],
                fn (Builder $query): Builder => $query->where('uuid', $uuid),
            )
            ->firstOrFail();
    }

    /**
     * Resolve a contact by its relationship id.
     */
    public function byId(Organization $tenant, int $contactId): OrganizationRelationship
    {
        return $this->query($tenant)->whereKey($contactId)->firstOrFail();
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
            ->external()
            ->where('organization_id', '!=', $tenant->getKey())
            ->whereStakeholder($entity)
            ->exists();

        $alreadyContact = OrganizationRelationship::query()
            ->external()
            ->forOrganization($tenant)
            ->whereStakeholder($entity)
            ->exists();

        if (! $registeredElsewhere || $alreadyContact) {
            throw (new ModelNotFoundException)->setModel($class);
        }

        return $entity;
    }

    /**
     * @return Builder<OrganizationRelationship>
     */
    private function query(Organization $tenant): Builder
    {
        return OrganizationRelationship::query()
            ->external()
            ->forOrganization($tenant)
            ->whereHasMorph('stakeholder', [Organization::class, Person::class])
            ->with(['stakeholder' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                Organization::class => ['primaryContact'],
            ])]);
    }
}
