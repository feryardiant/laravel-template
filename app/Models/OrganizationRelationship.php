<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganizationRelationshipType;
use App\Enums\RelationshipStatus;
use App\Enums\Role;
use App\Enums\SubsidiaryKind;
use App\Models\Builders\OrganizationRelationshipBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

/**
 * Pivot representing an organization's relationship with one of its stakeholders.
 *
 * @property ?string $code
 * @property int $organization_id
 * @property ?string $stakeholder_type
 * @property null|OrganizationRelationshipType $type
 * @property null|SubsidiaryKind $kind
 * @property null|Role $role
 * @property null|bool $is_primary
 * @property null|string $remark
 * @property null|CarbonImmutable $start_date
 * @property null|CarbonImmutable $finish_date
 * @property-read RelationshipStatus $status
 * @property-read bool $is_internal
 * @property-read bool $is_external
 * @property-read bool $is_individual
 * @property-read bool $is_organization
 * @property-read bool $is_shared
 * @property-read ?Organization $organization
 * @property-read Organization|Person $stakeholder
 *
 * @method static OrganizationRelationshipBuilder query()
 */
#[UseEloquentBuilder(OrganizationRelationshipBuilder::class)]
class OrganizationRelationship extends MorphPivot
{
    protected $table = 'organization_relationships';

    public $timestamps = false;

    /**
     * Ensure the stakeholder type matches the relationship type.
     */
    protected static function booted(): void
    {
        static::saving(function (self $relationship): void {
            if ($relationship->type !== OrganizationRelationshipType::Member || $relationship->is_primary === false) {
                $relationship->is_primary = null;
            }

            if ($relationship->type !== OrganizationRelationshipType::Member) {
                $relationship->role = null;
            } elseif ($relationship->role === null) {
                $relationship->role = Role::Member;
            }

            if ($relationship->type !== OrganizationRelationshipType::Subsidiary) {
                $relationship->kind = null;
            } elseif ($relationship->kind === null) {
                $relationship->kind = SubsidiaryKind::Subsidiary;
            }

            if ($relationship->type === null || $relationship->stakeholder_type === null) {
                return;
            }

            $stakeholder = Relation::getMorphedModel($relationship->stakeholder_type)
                ?? $relationship->stakeholder_type;

            $allowed = match ($relationship->type) {
                OrganizationRelationshipType::Subsidiary => [Organization::class],
                OrganizationRelationshipType::Member => [Person::class],
                OrganizationRelationshipType::Customer,
                OrganizationRelationshipType::Supplier,
                OrganizationRelationshipType::Vendor => [Organization::class, Person::class],
            };

            if (! \in_array($stakeholder, $allowed, true)) {
                throw new InvalidArgumentException(
                    "Invalid stakeholder for {$relationship->type->value} relationship."
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'organization_id' => 'int',
            'type' => OrganizationRelationshipType::class,
            'kind' => SubsidiaryKind::class,
            'role' => Role::class,
            'is_primary' => 'boolean',
            'start_date' => 'immutable_date',
            'finish_date' => 'immutable_date',
        ];
    }

    /**
     * Get the organization that owns this relationship.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * Get the stakeholder this relationship points to.
     *
     * @return MorphTo<Model, $this>
     */
    public function stakeholder(): MorphTo
    {
        return $this->morphTo('stakeholder', 'stakeholder_type', 'stakeholder_id');
    }

    /**
     * @return Attribute<bool, never>
     */
    protected function isInternal(): Attribute
    {
        return Attribute::get(fn () => $this->type?->isInternal() ?? false);
    }

    /**
     * @return Attribute<bool, never>
     */
    protected function isExternal(): Attribute
    {
        return Attribute::get(fn () => $this->type?->isExternal() ?? false);
    }

    /**
     * Determine whether the stakeholder is an individual.
     *
     * @return Attribute<bool, never>
     */
    protected function isIndividual(): Attribute
    {
        return Attribute::get(
            fn (mixed $_, array $attributes): bool => $attributes['stakeholder_type'] === Person::class
        );
    }

    /**
     * Determine whether the stakeholder is an organization.
     *
     * @return Attribute<bool, never>
     */
    protected function isOrganization(): Attribute
    {
        return Attribute::get(
            fn (mixed $_, array $attributes): bool => $attributes['stakeholder_type'] === Organization::class
        );
    }

    /**
     * Determine whether the linked entity is shared with another tenant.
     *
     * Used as the fallback when neither entity records an owner. Cached per
     * model instance; re-fetch the relationship after changing its links or
     * ownership within the same instance.
     *
     * @return Attribute<bool, never>
     */
    protected function isShared(): Attribute
    {
        return Attribute::get(function (): bool {
            $stakeholder = $this->stakeholder;

            // Another relationship for the same stakeholder (including imported
            // duplicates in this tenant) keeps the entity in use. Any other match
            // would also be returned by this check, so nothing below repeats it.
            if (static::query()->forStakeholder($stakeholder)->whereKeyNot($this)->exists()) {
                return true;
            }

            if ($stakeholder instanceof Person) {
                return $stakeholder->user_id !== null
                    || $stakeholder->primaryContactOrganizations()->exists();
            }

            if ($stakeholder->is_tenant || $stakeholder->root_organization_id !== null) {
                return true;
            }

            // The organization owns relationships of its own, including its
            // members and subsidiaries.
            if (static::query()->forOrganization($stakeholder)->exists()) {
                return true;
            }

            $contactPerson = $stakeholder->primaryContact;

            return $contactPerson !== null
                && ($contactPerson->user_id !== null
                    || $contactPerson->primaryContactOrganizations()->whereKeyNot($stakeholder)->exists()
                    || static::query()->forStakeholder($contactPerson)->exists());
        })->shouldCache();
    }

    /**
     * Get the relationship status derived from its dates.
     *
     * @return Attribute<RelationshipStatus, never>
     */
    protected function status(): Attribute
    {
        return Attribute::get(fn (): RelationshipStatus => $this->resolveStatus());
    }

    /**
     * Resolve the status from the relationship's date window.
     */
    private function resolveStatus(): RelationshipStatus
    {
        return match (true) {
            $this->finish_date?->lt(today()) => RelationshipStatus::Expired,
            $this->start_date?->gt(today()) => RelationshipStatus::Scheduled,
            default => RelationshipStatus::Active,
        };
    }

    /**
     * Determine whether the linked entity's profile is locked for the tenant.
     *
     * An explicit owner decides it; until ownership is recorded, fall back to the
     * legacy shared-entity heuristic.
     */
    public function profileLockedFor(Organization $organization): bool
    {
        $owner = $this->stakeholder->owner_organization_id;

        return $owner !== null
            ? $owner !== $organization->getKey()
            : $this->is_shared;
    }

    /**
     * Determine whether deleting this contact may also delete the entity.
     */
    public function canDeleteEntityFor(Organization $organization): bool
    {
        $stakeholder = $this->stakeholder;

        if ($stakeholder->trashed()) {
            return false;
        }

        $owner = $stakeholder->owner_organization_id;

        if ($owner !== null && $owner !== $organization->getKey()) {
            return false;
        }

        return ! $this->is_shared;
    }
}
