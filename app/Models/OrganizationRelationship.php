<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
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
 * @property-read Entity $stakeholder
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
