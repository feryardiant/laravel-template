<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\Audit\AuditPayload;
use App\Casts\AsAuditPayload;
use App\Enums\AuditEvent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One recorded change inside an organization.
 *
 * @property int $id
 * @property ?int $organization_id
 * @property ?int $actor_id
 * @property AuditEvent $event
 * @property ?string $subject_type
 * @property ?int $subject_id
 * @property ?int $organization_relationship_period_id
 * @property ?AuditPayload $changes
 * @property Carbon $created_at
 * @property-read ?Organization $organization
 * @property-read ?User $actor
 * @property-read ?Model $subject
 * @property-read ?OrganizationRelationshipPeriod $period
 */
#[Fillable([
    'organization_id',
    'actor_id',
    'event',
    'subject_type',
    'subject_id',
    'organization_relationship_period_id',
    'changes',
    'created_at',
])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => AuditEvent::class,
            'changes' => AsAuditPayload::class,
            'created_at' => 'immutable_datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    /**
     * @return BelongsTo<OrganizationRelationshipPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(OrganizationRelationshipPeriod::class, 'organization_relationship_period_id');
    }
}
