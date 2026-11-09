<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Base Eloquent model for organizations and people.
 *
 * @property-read int $id
 * @property string $uuid
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
     * @codeCoverageIgnore
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
