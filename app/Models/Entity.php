<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContactDetailType;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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

        static::forceDeleted(function (Entity $entity): void {
            $entity->contactDetails()->delete();
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
     * @return MorphMany<ContactDetail, $this>
     */
    public function contactDetails(): MorphMany
    {
        return $this->morphMany(ContactDetail::class, 'contactable');
    }

    /**
     * Read a single communication record for display.
     */
    public function contactDetailValue(ContactDetailType $type): ?string
    {
        $value = $this->contactDetails()->where('type', $type)->value('value');

        return \is_string($value) ? $value : null;
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
