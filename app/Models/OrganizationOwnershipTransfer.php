<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OwnershipTransferStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A pending or historical transfer of an organization's ownership.
 *
 * @property int $organization_id
 * @property string $uuid
 * @property int $from_person_id
 * @property int $to_person_id
 * @property OwnershipTransferStatus $status
 * @property Carbon $expires_at
 * @property-read Organization $organization
 * @property-read Person $fromPerson
 * @property-read Person $toPerson
 *
 * @method static Builder<static> pending()
 */
#[Fillable([
    'organization_id',
    'from_person_id',
    'to_person_id',
    'status',
    'expires_at',
    'accepted_at',
    'declined_at',
])]
#[RouteKey('uuid')]
class OrganizationOwnershipTransfer extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OwnershipTransferStatus::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $transfer): void {
            $transfer->uuid ??= (string) Str::uuid7();
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
    public function fromPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'from_person_id');
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function toPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'to_person_id');
    }

    /**
     * Limit the query to pending transfers.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', OwnershipTransferStatus::Pending);
    }

    /**
     * Determine whether the request has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
