<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Gender;
use App\Enums\OrganizationRelationshipType;
use App\Enums\PersonRelativeType;
use App\Enums\RelationshipStatus;
use App\Enums\Role;
use App\Models\Builders\PersonBuilder;
use Creasi\Nusa\Contracts\HasAddresses;
use Creasi\Nusa\Models\Concerns\WithAddresses;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

/**
 * Individual entity and its personal relationships.
 *
 * @property ?int $user_id
 * @property ?int $owner_organization_id
 * @property string $name
 * @property null|Gender $gender
 * @property ?string $summary
 * @property-read ?User $user
 * @property-read Collection<int, Person> $relatives
 * @property-read Collection<int, Organization> $organizationRelationships
 * @property-read Collection<int, Organization> $primaryContactOrganizations
 * @property-read OrganizationRelationship $stakeholder
 *
 * @method static PersonBuilder query()
 *
 * @implements HasAddresses<static>
 */
#[Fillable(['user_id', 'owner_organization_id', 'name', 'gender', 'summary', 'photo_url'])]
#[UseEloquentBuilder(PersonBuilder::class)]
class Person extends Entity implements HasAddresses
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory;

    /** @use WithAddresses<static, Address> */
    use WithAddresses;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Organization, $this>
     */
    public function primaryContactOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'contact_id');
    }

    /**
     * @return BelongsToMany<Person, $this, PersonRelative, 'relation'>
     */
    public function relatives(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'people_relatives', 'person_id', 'relative_id')
            ->withPivot('type')
            ->using(PersonRelative::class)
            ->as('relation');
    }

    /**
     * @return MorphToMany<Organization, $this, OrganizationRelationship, 'stakeholder'>
     */
    public function organizationRelationships(): MorphToMany
    {
        return $this->morphToMany(Organization::class, 'stakeholder', 'organization_relationships', 'stakeholder_id', 'organization_id')
            ->using(OrganizationRelationship::class)
            ->withPivot('id', 'type', 'kind', 'role', 'is_primary', 'code', 'start_date', 'finish_date', 'remark')
            ->as('stakeholder');
    }

    /**
     * Get every membership relationship of the person.
     *
     * @return MorphToMany<Organization, $this, OrganizationRelationship, 'stakeholder'>
     */
    public function memberships(): MorphToMany
    {
        return $this->organizationRelationships()
            ->wherePivot('type', OrganizationRelationshipType::Member);
    }

    /**
     * Get the organizations the person is currently a member of.
     *
     * @return MorphToMany<Organization, $this, OrganizationRelationship, 'stakeholder'>
     */
    public function organizations(): MorphToMany
    {
        return $this->memberships()->membershipActive();
    }

    /**
     * @return Attribute<?Gender, Gender|string|null>
     */
    protected function gender(): Attribute
    {
        return Attribute::make(
            set: fn (Gender|string|null $value): array => ['gender' => filled($value) ? $value : null],
        );
    }

    /**
     * Get the person's photo URL.
     *
     * @return Attribute<?string, ?string>
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value ? route('uploads', ['path' => $value]) : null,
            set: fn (?string $value): array => ['photo_url' => $value],
        );
    }

    /**
     * Get the person's default workspace.
     */
    public function defaultOrganization(): ?Organization
    {
        return $this->organizations()->wherePivot('is_primary', true)->first()
            ?? $this->organizations()->first();
    }

    /**
     * Get the person's initials.
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    /**
     * Get the person's membership row in the organization, active or not.
     *
     * Prefers a pivot already loaded through the organization or the person,
     * so authorization on the current workspace does not query again.
     */
    public function membershipIn(Organization $organization): ?OrganizationRelationship
    {
        foreach ([$this, $organization] as $model) {
            foreach (['pivot', 'stakeholder'] as $relation) {
                if (! $model->relationLoaded($relation)) {
                    continue;
                }

                $pivot = $model->getRelation($relation);

                if ($pivot instanceof OrganizationRelationship
                    && $pivot->type === OrganizationRelationshipType::Member
                    && (int) $pivot->organization_id === (int) $organization->getKey()
                    && (int) $pivot->stakeholder_id === (int) $this->getKey()) {
                    return $pivot;
                }
            }
        }

        return OrganizationRelationship::query()
            ->forOrganization($organization)
            ->forStakeholder($this, OrganizationRelationshipType::Member)
            ->first();
    }

    /**
     * Get the person's active role in the given organization.
     */
    public function roleIn(Organization $organization): ?Role
    {
        $membership = $this->membershipIn($organization);

        return $membership?->status === RelationshipStatus::Active
            ? $membership->role
            : null;
    }

    /**
     * Attach a relative to this person.
     */
    public function addRelative(Person $relative, PersonRelativeType $type): void
    {
        $this->relatives()->attach($relative, ['type' => $type]);
    }
}
