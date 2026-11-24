<?php

declare(strict_types=1);

namespace App\Actions\Relationships;

use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\OrganizationRelationshipPeriod;
use App\Models\Person;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lists the recorded periods of a membership or contact relationship.
 */
final class ListRelationshipPeriods
{
    /**
     * List a member's membership stints.
     *
     * @return Collection<int, OrganizationRelationshipPeriod>
     */
    public function forMember(Organization $organization, Person $person): Collection
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
    public function forContact(OrganizationRelationship $contact): Collection
    {
        return OrganizationRelationshipPeriod::query()
            ->forContact($contact)
            ->with('actor')
            ->latestFirst()
            ->get();
    }
}
