<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use Illuminate\Database\Eloquent\Builder;
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
     * @return Builder<OrganizationRelationship>
     */
    private function query(Organization $tenant): Builder
    {
        return OrganizationRelationship::query()
            ->forOrganization($tenant)
            ->external()
            ->whereHasMorph('stakeholder', [Organization::class, Person::class])
            ->with(['stakeholder' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                Organization::class => ['primaryContact'],
            ])]);
    }
}
