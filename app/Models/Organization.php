<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganizationRelationshipType;
use Creasi\Nusa\Contracts\HasAddress;
use Creasi\Nusa\Models\Concerns\WithAddress;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

/**
 * Business entity that relates to other organizations and people.
 *
 * @property int $contact_id
 * @property string $name
 * @property ?string $summary
 * @property bool $is_tenant
 * @property ?int $root_organization_id
 * @property-read ?Person $primaryContact
 * @property-read Collection<int, Organization> $organizationCounterparties
 * @property-read Collection<int, Person> $personCounterparties
 * @property-read Collection<int, Organization> $subsidiaries
 * @property-read Collection<int, Person> $members
 *
 * @implements HasAddress<static>
 */
#[Fillable(['contact_id', 'name', 'summary', 'is_tenant', 'root_organization_id', 'logo_url'])]
class Organization extends Entity implements HasAddress
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    /** @use WithAddress<static, Address> */
    use WithAddress;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_tenant' => 'boolean',
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected static function booted(): void
    {
        parent::booted();

        static::forceDeleting(function (Organization $organization): void {
            OrganizationRelationship::query()
                ->whereStakeholder($organization)
                ->delete();
        });
    }

    /**
     * Limit the query to root tenant organizations.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeRootTenant(Builder $query): Builder
    {
        return $query->where('is_tenant', true)->whereNull('root_organization_id');
    }

    /**
     * Get the organization's logo URL.
     *
     * @return Attribute<?string, ?string>
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value ? route('uploads', ['path' => $value]) : null,
            set: fn (?string $value): array => ['logo_url' => $value],
        );
    }

    /**
     * Get the organization's initials.
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function primaryContact(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'contact_id')->withTrashed();
    }

    /**
     * @template TEntity of Entity
     *
     * @param  class-string<TEntity>  $stakeholder
     * @return MorphToMany<TEntity, $this, OrganizationRelationship>
     */
    protected function relationships(string $stakeholder): MorphToMany
    {
        return $this->morphedByMany($stakeholder, 'stakeholder', 'organization_relationships', 'organization_id')
            ->using(OrganizationRelationship::class)
            ->withPivot('type', 'code', 'start_date', 'finish_date');
    }

    /**
     * @return MorphToMany<Organization, $this, OrganizationRelationship, 'stakeholder'>
     */
    public function organizationCounterparties(?OrganizationRelationshipType ...$type): MorphToMany
    {
        if (empty($type)) {
            $type = OrganizationRelationshipType::externals();
        }

        return $this->relationships(Organization::class)
            ->wherePivotIn('type', $type)
            ->as('stakeholder');
    }

    /**
     * @return MorphToMany<Person, $this, OrganizationRelationship, 'stakeholder'>
     */
    public function personCounterparties(?OrganizationRelationshipType ...$type): MorphToMany
    {
        if (empty($type)) {
            $type = OrganizationRelationshipType::externals();
        }

        return $this->relationships(Person::class)
            ->wherePivotIn('type', $type)
            ->as('stakeholder');
    }

    /**
     * @return Collection<int, Organization|Person>
     */
    public function counterparties(?OrganizationRelationshipType ...$type): Collection
    {
        return $this->organizationCounterparties(...$type)->get()
            ->concat($this->personCounterparties(...$type)->get());
    }

    /**
     * @return MorphToMany<Person, $this, OrganizationRelationship>
     */
    public function members(): MorphToMany
    {
        return $this->relationships(Person::class)
            ->withPivot('is_primary', 'role')
            ->wherePivot('type', '=', OrganizationRelationshipType::Member);
    }

    /**
     * @return MorphToMany<Organization, $this, OrganizationRelationship, 'subsidiary'>
     */
    public function subsidiaries(): MorphToMany
    {
        return $this->relationships(Organization::class)
            ->wherePivot('type', '=', OrganizationRelationshipType::Subsidiary)
            ->as('subsidiary');
    }
}
