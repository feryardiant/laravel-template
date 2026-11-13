<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Cast the JSON `preferences` column to an array of user preferences.
 *
 * @implements CastsAttributes<array<string, mixed>, array<string, mixed>|null>
 */
final class AsUserPreference implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        $preferences = json_decode((string) $value, true);

        return \is_array($preferences) ? $preferences : null;
    }

    /**
     * @param  array<string, mixed>|null  $value
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
