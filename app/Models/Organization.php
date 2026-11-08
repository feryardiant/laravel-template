<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganizationRelationshipType;
use Carbon\CarbonInterface;
use Creasi\Nusa\Contracts\HasAddress;
use Creasi\Nusa\Models\Concerns\WithAddress;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\DB;

/**
 * Business entity that relates to other organizations and people.
 *
 * @property int $contact_id
 * @property string $name
 * @property ?string $summary
 * @property-read ?Person $primaryContact
 * @property-read Collection<int, Organization> $organizationCounterparties
 * @property-read Collection<int, Person> $personCounterparties
 * @property-read Collection<int, Organization> $subsidiaries
 * @property-read Collection<int, Person> $members
 *
 * @implements HasAddress<static>
 */
#[Fillable(['name', 'summary'])]
class Organization extends Entity implements HasAddress
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    /** @use WithAddress<static, Address> */
    use WithAddress;

    /**
     * @return BelongsTo<Person, $this>
     */
    public function primaryContact(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'contact_id');
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
     * @return MorphToMany<Person, $this, OrganizationRelationship, 'member'>
     */
    public function members(): MorphToMany
    {
        return $this->relationships(Person::class)
            ->withPivot('is_primary')
            ->wherePivot('type', '=', OrganizationRelationshipType::Member)
            ->as('member');
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

    /**
     * Attach a person as a member, optionally as their primary organization.
     */
    public function addMember(
        Person $member,
        bool $isPrimary = false,
        ?CarbonInterface $finishDate = null,
        ?CarbonInterface $startDate = null,
    ): void {
        if (! $isPrimary) {
            $this->members()->attach($member, [
                'type' => OrganizationRelationshipType::Member,
                'is_primary' => null,
                'start_date' => $startDate,
                'finish_date' => $finishDate,
            ]);

            return;
        }

        DB::transaction(function () use ($member, $finishDate, $startDate): void {
            $member->newQuery()
                ->whereKey($member->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $member->organizationRelationships()->newPivotQuery()
                ->where('type', OrganizationRelationshipType::Member)
                ->update(['is_primary' => null]);

            $this->members()->attach($member, [
                'type' => OrganizationRelationshipType::Member,
                'is_primary' => true,
                'start_date' => $startDate,
                'finish_date' => $finishDate,
            ]);
        });
    }
}
