<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganizationRelativeType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

/**
 * Pivot linking an organization to one of its stakeholders.
 *
 * @property ?string $code
 * @property int $organization_id
 * @property ?string $stakeholder_type
 * @property null|OrganizationRelativeType $type
 * @property null|string $remark
 * @property null|CarbonImmutable $start_date
 * @property null|CarbonImmutable $finish_date
 * @property-read null|bool $is_started
 * @property-read null|bool $is_finished
 * @property-read bool $is_internal
 * @property-read bool $is_external
 * @property-read Entity $stakeholder
 */
class OrganizationRelative extends MorphPivot
{
    protected $table = 'organizations_relatives';

    /**
     * Ensure the stakeholder type matches the relationship type.
     */
    protected static function booted(): void
    {
        static::saving(function (self $relative): void {
            if ($relative->type === null || $relative->stakeholder_type === null) {
                return;
            }

            $stakeholder = Relation::getMorphedModel($relative->stakeholder_type)
                ?? $relative->stakeholder_type;

            $allowed = match ($relative->type) {
                OrganizationRelativeType::Subsidiary => [Organization::class],
                OrganizationRelativeType::Member => [Person::class],
                OrganizationRelativeType::Customer,
                OrganizationRelativeType::Supplier,
                OrganizationRelativeType::Vendor => [Organization::class, Person::class],
            };

            if (! \in_array($stakeholder, $allowed, true)) {
                throw new InvalidArgumentException(
                    "Invalid stakeholder for {$relative->type->value} relation."
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
            'type' => OrganizationRelativeType::class,
            'start_date' => 'immutable_date',
            'finish_date' => 'immutable_date',
        ];
    }

    /**
     * @return Attribute<bool, never>
     */
    public function isInternal(): Attribute
    {
        return Attribute::get(fn () => $this->type->isInternal());
    }

    /**
     * @return Attribute<bool, never>
     */
    public function isExternal(): Attribute
    {
        return Attribute::get(fn () => $this->type->isExternal());
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
