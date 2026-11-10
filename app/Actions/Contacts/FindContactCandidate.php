<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class FindContactCandidate
{
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
            ->where('stakeholder_type', $entity->getMorphClass())
            ->where('stakeholder_id', $entity->getKey())
            ->exists();

        $alreadyContact = OrganizationRelationship::query()
            ->forOrganization($tenant)
            ->external()
            ->where('stakeholder_type', $entity->getMorphClass())
            ->where('stakeholder_id', $entity->getKey())
            ->exists();

        if (! $registeredElsewhere || $alreadyContact) {
            throw (new ModelNotFoundException)->setModel($class);
        }

        return $entity;
    }
}
