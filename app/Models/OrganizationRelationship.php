<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
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
 * @property null|Role $role
 * @property null|string $remark
 * @property null|CarbonImmutable $start_date
 * @property null|CarbonImmutable $finish_date
 * @property-read null|bool $is_started
 * @property-read null|bool $is_finished
 * @property-read bool $is_internal
 * @property-read bool $is_external
 * @property-read Organization|Person|null $stakeholder
 */
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
            'role' => Role::class,
            'start_date' => 'immutable_date',
            'finish_date' => 'immutable_date',
        ];
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
     * Limit the query to the given tenant.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForOrganization(Builder $query, Organization $organization): Builder
    {
        return $query->where('organization_id', $organization->getKey());
    }

    /**
     * Limit the query to external contact relationships.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeExternal(Builder $query): Builder
    {
        return $query->whereIn('type', OrganizationRelationshipType::externals());
    }

    /**
     * Determine whether the stakeholder is an individual.
     */
    public function isIndividual(): bool
    {
        return $this->stakeholder instanceof Person;
    }

    /**
     * Determine whether the stakeholder is an organization.
     */
    public function isOrganization(): bool
    {
        return $this->stakeholder instanceof Organization;
    }

    /**
     * Determine whether the linked entity is shared with another tenant.
     *
     * Shared entities must not be mutated or soft-deleted from this tenant.
     */
    public function isShared(): bool
    {
        $stakeholder = $this->stakeholder;

        if ($stakeholder === null) {
            return false;
        }

        if ($stakeholder instanceof Person) {
            if ($stakeholder->user_id !== null) {
                return true;
            }

            // Referenced as any organization's primary contact.
            if ($stakeholder->primaryContactOrganizations()->exists()) {
                return true;
            }
        }

        if ($stakeholder instanceof Organization) {
            if ($stakeholder->is_tenant || $stakeholder->root_organization_id !== null) {
                return true;
            }

            // The organization owns relationships of its own.
            if (static::query()->where('organization_id', $stakeholder->getKey())->exists()) {
                return true;
            }

            if ($stakeholder->members()->exists() || $stakeholder->subsidiaries()->exists()) {
                return true;
            }

            $contactPerson = $stakeholder->primaryContact;

            if ($contactPerson !== null) {
                if ($contactPerson->user_id !== null) {
                    return true;
                }

                if ($contactPerson->primaryContactOrganizations()->whereKeyNot($stakeholder->getKey())->exists()) {
                    return true;
                }

                if (static::query()
                    ->where('stakeholder_type', $contactPerson->getMorphClass())
                    ->where('stakeholder_id', $contactPerson->getKey())
                    ->exists()) {
                    return true;
                }
            }
        }

        return static::query()
            ->where('stakeholder_type', $stakeholder->getMorphClass())
            ->where('stakeholder_id', $stakeholder->getKey())
            ->where('organization_id', '!=', $this->organization_id)
            ->exists();
    }

    /**
     * @return Attribute<bool, never>
     */
    public function isInternal(): Attribute
    {
        return Attribute::get(fn () => $this->type?->isInternal() ?? false);
    }

    /**
     * @return Attribute<bool, never>
     */
    public function isExternal(): Attribute
    {
        return Attribute::get(fn () => $this->type?->isExternal() ?? false);
    }

    /**
     * @return Attribute<null|bool, never>
     */
    public function isStarted(): Attribute
    {
        return Attribute::get(fn () => $this->start_date?->isPast());
    }

    /**
     * @return Attribute<null|bool, never>
     */
    public function isFinished(): Attribute
    {
        return Attribute::get(fn () => $this->finish_date?->isPast());
    }
}
