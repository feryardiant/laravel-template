<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Base Eloquent model for organizations and people.
 *
 * @property-read int $id
 * @property ?int $owner_organization_id
 * @property string $uuid
 * @property-read ?Organization $ownerOrganization
 */
#[RouteKey('uuid')]
abstract class Entity extends Model
{
    use SoftDeletes;

    /**
     * {@inheritdoc}
     */
    protected static function booted(): void
    {
        static::creating(function (Entity $entity): void {
            $entity->uuid ??= (string) Str::uuid7();
        });
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function ownerOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'owner_organization_id');
    }

    /**
     * Determine whether the given organization owns this entity's profile.
     */
    public function isOwnedBy(?Organization $organization): bool
    {
        return $organization !== null && $this->owner_organization_id === $organization->getKey();
    }

    /**
     * @codeCoverageIgnore
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
