<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Gender;
use App\Enums\PersonRelativeType;
use Creasi\Nusa\Contracts\HasAddresses;
use Creasi\Nusa\Models\Concerns\WithAddresses;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Individual entity and its personal relationships.
 *
 * @property ?int $user_id
 * @property string $name
 * @property ?string $summary
 * @property-read ?User $user
 * @property-read Collection<int, Person> $relatives
 * @property-read Collection<int, Organization> $organizationRelationships
 * @property-read Collection<int, Organization> $primaryContactOrganizations
 *
 * @implements HasAddresses<static>
 */
#[Fillable(['user_id', 'name', 'gender', 'summary'])]
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
            ->withPivot('type', 'code', 'start_date', 'finish_date')
            ->as('stakeholder');
    }

    /**
     * Attach a relative to this person.
     */
    public function addRelative(Person $relative, PersonRelativeType $type): void
    {
        $this->relatives()->attach($relative, ['type' => $type]);
    }
}
