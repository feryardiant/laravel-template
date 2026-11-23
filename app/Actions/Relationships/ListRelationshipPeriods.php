<?php

declare(strict_types=1);

namespace App\Actions\Relationships;

use App\Enums\OrganizationRelationshipType;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\OrganizationRelationshipPeriod;
use App\Models\Person;
use Illuminate\Database\Eloquent\Collection;

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
            ->where('organization_id', $organization->getKey())
            ->where('stakeholder_type', (new Person)->getMorphClass())
            ->where('stakeholder_id', $person->getKey())
            ->where('type', OrganizationRelationshipType::Member)
            ->with('actor')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
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
            ->where('organization_id', $contact->organization_id)
            ->where('stakeholder_type', $contact->stakeholder_type)
            ->where('stakeholder_id', $contact->stakeholder_id)
            ->with('actor')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();
    }
}
