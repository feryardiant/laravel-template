<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\Enums\OrganizationRelationshipType;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @extends Builder<OrganizationRelationship>
 */
final class OrganizationRelationshipBuilder extends Builder
{
    use Concerns\ForOrganization;

    public function whereStakeholder(Organization|Person $entity, ?OrganizationRelationshipType $type = null): static
    {
        $this
            ->where('stakeholder_type', $entity->getMorphClass())
            ->where('stakeholder_id', $entity->getKey());

        if ($type) {
            $this->where('type', $type);
        }

        return $this;
    }

    /**
     * Limit the query to the given stakeholder morph class.
     */
    public function whereStakeholderType(string $stakeholder): static
    {
        return $this->where('stakeholder_type', $stakeholder);
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
     * Limit the query to relationships owned by another organization.
     */
    public function outsideOrganization(Organization|int $organization): static
    {
        $organization = $organization instanceof Organization ? $organization->getKey() : $organization;

        return $this->where('organization_id', '!=', $organization);
    }

    /**
     * Limit the query to external contact relationships.
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

    /**
     * Limit the query to relationships that have started or have no start date.
     */
    public function started(): static
    {
        return $this->where(fn (Builder $query): Builder => $query
            ->whereNull('start_date')
            ->orWhereDate('start_date', '<=', today()));
    }

    /**
     * Limit the query to relationships that started inside the range.
     *
     * @param  array{start: string, end: string}  $range
     */
    public function startedBetween(array $range): static
    {
        return $this->whereDate('start_date', '>=', $range['start'])
            ->whereDate('start_date', '<=', $range['end']);
    }

    /**
     * Limit the query to relationships that finish inside the range.
     *
     * @param  array{start: string, end: string}  $range
     */
    public function finishingBetween(array $range): static
    {
        return $this->whereDate('finish_date', '>=', $range['start'])
            ->whereDate('finish_date', '<=', $range['end']);
    }

    /**
     * Limit the query to relationships whose window has ended.
     */
    public function expired(): static
    {
        return $this->whereNotNull('finish_date')->whereDate('finish_date', '<', today());
    }

    /**
     * Limit the query to relationships that have not ended (active or scheduled).
     */
    public function notExpired(): static
    {
        return $this->where(fn (Builder $query): Builder => $query
            ->whereNull('finish_date')
            ->orWhereDate('finish_date', '>=', today()));
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
}
