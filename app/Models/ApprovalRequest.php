<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A pending or historical approval requested inside an organization.
 *
 * @property int $organization_id
 * @property string $uuid
 * @property ApprovalRequestType $type
 * @property int $requester_person_id
 * @property int $approver_person_id
 * @property ApprovalRequestStatus $status
 * @property array<string, mixed>|null $payload
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $declined_at
 * @property Carbon|null $cancelled_at
 * @property-read Organization $organization
 * @property-read Person $requester
 * @property-read Person $approver
 *
 * @method static Builder<static> pending()
 */
#[Fillable([
    'organization_id',
    'type',
    'requester_person_id',
    'approver_person_id',
    'status',
    'payload',
    'expires_at',
    'accepted_at',
    'declined_at',
    'cancelled_at',
])]
#[RouteKey('uuid')]
class ApprovalRequest extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ApprovalRequestType::class,
            'status' => ApprovalRequestStatus::class,
            'payload' => 'array',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            $request->uuid ??= (string) Str::uuid7();
        });
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'requester_person_id');
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'approver_person_id');
    }

    /**
     * Limit the query to pending requests.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ApprovalRequestStatus::Pending);
    }

    /**
     * Determine whether the request has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
