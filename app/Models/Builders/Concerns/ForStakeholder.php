<?php

declare(strict_types=1);

namespace App\Models\Builders\Concerns;

use App\Enums\OrganizationRelationshipType;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @mixin Builder<Model>
 */
trait ForStakeholder
{
    /**
     * Limit the query to relationships whose stakeholder is the given entity.
     */
    public function forStakeholder(Organization|Person $entity, ?OrganizationRelationshipType $type = null): static
    {
        return $this
            ->where('stakeholder_type', $entity->getMorphClass())
            ->where('stakeholder_id', $entity->getKey())
            ->when($type, fn (Builder $query): Builder => $query->where('type', $type));
    }

    /**
     * Limit the query to the given stakeholder morph class.
     */
    public function whereStakeholderType(string $stakeholder): static
    {
        return $this->where('stakeholder_type', $stakeholder);
    }

    /**
     * Limit the query to relationships whose stakeholder exists.
     *
     * Soft-deleted organizations still count, and an optional UUID narrows the
     * stakeholder match.
     */
    public function hasStakeholder(?string $uuid = null): static
    {
        return $this->whereHasMorph(
            'stakeholder',
            [Organization::class, Person::class],
            function (Builder $query, string $type) use ($uuid): void {
                if ($type === Organization::class) {
                    $query->withTrashed();
                }

                if ($uuid !== null) {
                    $query->where('uuid', $uuid);
                }
            },
        );
    }

    /**
     * Eager load the stakeholder, including trashed organizations.
     */
    public function withStakeholder(): static
    {
        return $this->with([
            'stakeholder' => fn (MorphTo $morphTo) => $morphTo->withTrashed(),
        ]);
    }

    /**
     * Eager load the stakeholder and an organization's primary contact.
     */
    public function withStakeholderAndPrimaryContact(): static
    {
        return $this->with([
            'stakeholder' => fn (MorphTo $morphTo) => $morphTo->withTrashed()->morphWith([
                Organization::class => ['primaryContact'],
            ]),
        ]);
    }

    /**
     * Limit the query to a person's membership stints.
     */
    public function forMember(Person $person): static
    {
        return $this->forStakeholder($person, OrganizationRelationshipType::Member);
    }

    /**
     * Limit the query to a contact relationship's stints.
     */
    public function forContact(OrganizationRelationship $contact): static
    {
        return $this
            ->forOrganization($contact->organization_id)
            ->where('stakeholder_type', $contact->stakeholder_type)
            ->where('stakeholder_id', $contact->stakeholder_id);
    }

    /**
     * Limit the query to member relationships.
     */
    public function members(): static
    {
        return $this->where('type', OrganizationRelationshipType::Member);
    }

    /**
     * Limit the query to subsidiary relationships.
     */
    public function subsidiaries(): static
    {
        return $this->where('type', OrganizationRelationshipType::Subsidiary);
    }

    /**
     * Limit the query to internal relationships (members and subsidiaries).
     */
    public function internals(): static
    {
        return $this->whereIn('type', OrganizationRelationshipType::internals());
    }

    /**
     * Limit the query to external contact relationships.
     */
    public function externals(): static
    {
        return $this->whereIn('type', OrganizationRelationshipType::externals());
    }
}
