<?php

declare(strict_types=1);

namespace App\Actions\People;

use App\Actions\Users\CreateUser;
use App\Enums\Gender;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Str;

final class CreatePerson
{
    public function __construct(
        private CreateUser $createUser,
    ) {
        // .
    }

    /**
     * Create a person, optionally with a login credential.
     */
    public function handle(
        string $name,
        Gender|string|null $gender = null,
        ?string $email = null,
        ?string $photoPath = null,
        ?string $summary = null,
        ?User $user = null,
        ?Organization $owner = null,
    ): Person {
        if (! $user && $email) {
            $user = $this->createUser->handle(
                name: $name,
                email: $email,
                password: Str::random(16),
            );
        }

        return Person::create([
            'user_id' => $user?->getKey(),
            'owner_organization_id' => $owner?->getKey(),
            'name' => $name,
            'gender' => $gender,
            'summary' => $summary,
            'photo_url' => $photoPath,
        ]);
    }
}
