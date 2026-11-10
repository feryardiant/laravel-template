<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Actions\Users\CreateUser;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Notifications\VerifyEmailForNewUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateMember
{
    /**
     * Create a person, optional credential, and organization membership.
     */
    public function handle(
        Organization $organization,
        string $name,
        Role $role,
        ?string $email = null,
    ): Person {
        $user = null;

        $person = DB::transaction(function () use ($organization, $name, $role, $email, &$user): Person {
            if ($email !== null) {
                $user = app(CreateUser::class)->handle([
                    'name' => $name,
                    'email' => Str::lower($email),
                    'role' => Role::Member,
                    'password' => Str::random(16),
                ]);
            }

            $person = Person::create([
                'user_id' => $user?->getKey(),
                'name' => $name,
            ]);

            app(AddMember::class)->handle(
                $organization,
                $person,
                startDate: now(),
                role: $role,
            );

            return $person;
        });

        if ($user instanceof User) {
            $user->notify(new VerifyEmailForNewUser);
        }

        return $person;
    }
}
