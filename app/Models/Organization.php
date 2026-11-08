<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganizationRelativeType;
use Carbon\CarbonInterface;
use Creasi\Nusa\Contracts\HasAddress;
use Creasi\Nusa\Models\Concerns\WithAddress;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Business entity that relates to other organizations and people.
 *
 * @property string $name
 * @property ?string $summary
 * @property-read Collection<int, Organization> $organizationalRelatives
 * @property-read Collection<int, Person> $individualRelatives
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
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'owner_id');
    }

    /**
     * @template TEntity of Entity
     *
     * @param  class-string<TEntity>  $relative
     * @return MorphToMany<TEntity, $this, OrganizationRelative>
     */
    protected function relatives(string $relative): MorphToMany
    {
        return $this->morphedByMany($relative, 'stakeholder', 'organizations_relatives', 'organization_id')
            ->using(OrganizationRelative::class)
            ->withPivot('type', 'code', 'start_date', 'finish_date');
    }

    /**
     * @return MorphToMany<Organization, $this, OrganizationRelative, 'stakeholder'>
     */
    public function organizationalRelatives(?OrganizationRelativeType ...$type): MorphToMany
    {
        if (empty($type)) {
            $type = OrganizationRelativeType::externals();
        }

        return $this->relatives(Organization::class)
            ->wherePivotIn('type', $type)
            ->as('stakeholder');
    }

    /**
     * @return MorphToMany<Person, $this, OrganizationRelative, 'stakeholder'>
     */
    public function individualRelatives(?OrganizationRelativeType ...$type): MorphToMany
    {
        if (empty($type)) {
            $type = OrganizationRelativeType::externals();
        }

        return $this->relatives(Person::class)
            ->wherePivotIn('type', $type)
            ->as('stakeholder');
    }

    /**
     * @return MorphToMany<Person, $this, OrganizationRelative, 'member'>
     */
    public function members(): MorphToMany
    {
        return $this->relatives(Person::class)
            ->withPivot('is_primary')
            ->wherePivot('type', '=', OrganizationRelativeType::Member)
            ->as('member');
    }

    /**
     * @return MorphToMany<Organization, $this, OrganizationRelative, 'subsidiary'>
     */
    public function subsidiaries(): MorphToMany
    {
        return $this->relatives(Organization::class)
            ->wherePivot('type', '=', OrganizationRelativeType::Subsidiary)
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
        if ($isPrimary) {
            $member->organizationalRelatives()->newPivotQuery()
                ->where('type', OrganizationRelativeType::Member)
                ->update(['is_primary' => false]);
        }

        $this->members()->attach($member, [
            'type' => OrganizationRelativeType::Member,
            'is_primary' => $isPrimary,
            'start_date' => $startDate,
            'finish_date' => $finishDate,
        ]);
    }
}
