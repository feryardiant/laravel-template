<?php

declare(strict_types=1);

namespace App\Actions\Setup;

use App\Actions\Organizations\AddMember;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\DB;

class ProvisionTenant
{
    /**
     * Turn the seeded placeholder user into the root tenant owner.
     */
    public function handle(string $name, string $email, string $password): User
    {
        return DB::transaction(function () use ($name, $email, $password): User {
            $user = User::query()
                ->where('email', config('setup.initial_email'))
                ->lockForUpdate()
                ->firstOrFail();

            $user->forceFill([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'email_verified_at' => now(),
            ])->save();

            $person = Person::create([
                'user_id' => $user->getKey(),
                'name' => $name,
            ]);

            $organization = Organization::create([
                'contact_id' => $person->getKey(),
                'name' => \sprintf("%s's Org", $name),
                'is_tenant' => true,
                'root_organization_id' => null,
            ]);

            app(AddMember::class)->handle(
                $organization,
                $person,
                isPrimary: true,
                startDate: now(),
                role: Role::Owner,
            );

            session([CurrentOrganization::SESSION_KEY => $organization->getKey()]);

            return $user;
        });
    }
}
