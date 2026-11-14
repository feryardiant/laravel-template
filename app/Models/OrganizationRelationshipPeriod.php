<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganizationRelationshipType;
use App\Enums\RelationshipPeriodReason;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One stint of an organization relationship.
 *
 * @property int $organization_id
 * @property ?int $organization_relationship_id
 * @property string $stakeholder_type
 * @property int $stakeholder_id
 * @property OrganizationRelationshipType $type
 * @property ?string $code
 * @property ?Role $role
 * @property Carbon $start_date
 * @property ?Carbon $finish_date
 * @property ?RelationshipPeriodReason $reason
 * @property ?int $actor_id
 * @property ?Carbon $closed_at
 * @property-read Organization $organization
 * @property-read ?OrganizationRelationship $relationship
 * @property-read Organization|Person $stakeholder
 * @property-read ?User $actor
 */
#[Fillable([
    'organization_id',
    'organization_relationship_id',
    'stakeholder_type',
    'stakeholder_id',
    'type',
    'code',
    'role',
    'start_date',
    'finish_date',
    'reason',
    'actor_id',
    'closed_at',
])]
class OrganizationRelationshipPeriod extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OrganizationRelationshipType::class,
            'role' => Role::class,
            'start_date' => 'immutable_date',
            'finish_date' => 'immutable_date',
            'reason' => RelationshipPeriodReason::class,
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<OrganizationRelationship, $this>
     */
    public function relationship(): BelongsTo
    {
        return $this->belongsTo(OrganizationRelationship::class, 'organization_relationship_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function stakeholder(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Determine whether this is the current stint.
     */
    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }
}
