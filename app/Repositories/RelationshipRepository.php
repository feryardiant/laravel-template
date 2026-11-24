<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\OrganizationRelationshipPeriod;
use App\Models\Person;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reads the recorded periods of a membership or contact relationship.
 */
final class RelationshipRepository
{
    /**
     * List a member's membership stints.
     *
     * @return Collection<int, OrganizationRelationshipPeriod>
     */
    public function getForMember(Organization $organization, Person $person): Collection
    {
        return OrganizationRelationshipPeriod::query()
            ->forOrganization($organization)
            ->forMember($person)
            ->with('actor')
            ->latestFirst()
            ->get();
    }

    /**
     * List a contact relationship's stints.
     *
     * @return Collection<int, OrganizationRelationshipPeriod>
     */
    public function getForContact(OrganizationRelationship $contact): Collection
    {
        return OrganizationRelationshipPeriod::query()
            ->forContact($contact)
            ->with('actor')
            ->latestFirst()
            ->get();
    }
}
