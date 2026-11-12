<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use RuntimeException;

final class CreateUser
{
    /**
     * Create a user and retry when a generated handle loses a race.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): User
    {
        $attributes['email'] = Str::lower($attributes['email']);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return User::create($attributes);
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === 2) {
                    throw $exception;
                }

                $attributes['handle'] = self::generateHandle(Str::random(8));
            }
        }

        throw new RuntimeException('Unable to create user.');
    }

    /**
     * Generate a valid, unique handle from the given seed.
     */
    public static function generateHandle(string $seed): string
    {
        $base = Str::substr(
            Str::lower(preg_replace('/[^a-z0-9]+/i', '', Str::before($seed, '@')) ?? ''),
            0,
            12,
        );

        if (Str::length($base) < 4) {
            $base .= Str::lower(Str::random(4 - Str::length($base)));
        }

        $handle = $base;

        while (User::query()->where('handle', $handle)->exists()) {
            $handle = Str::substr($base, 0, 10).Str::lower(Str::random(5));
        }

        return $handle;
    }
}
