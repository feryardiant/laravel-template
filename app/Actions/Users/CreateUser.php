<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use RuntimeException;

class CreateUser
{
    /**
     * Create a user and retry when a generated handle loses a race.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): User
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return User::create($attributes);
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === 2) {
                    throw $exception;
                }

                $attributes['handle'] = User::generateHandle(Str::random(8));
            }
        }

        throw new RuntimeException('Unable to create user.');
    }
}
