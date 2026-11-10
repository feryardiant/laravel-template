<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Organizations\AddMember;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (User::query()->count() > 0) {
            return;
        }

        if (app()->environment('local', 'testing')) {
            $this->localUsers();
        } else {
            $this->initialUsers();
        }
    }

    private function localUsers(): void
    {
        $owner = User::factory()->create([
            'email' => 'owner@example.com',
            'handle' => 'owner',
            'role' => Role::Owner,
        ]);

        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'handle' => 'admin',
            'role' => Role::Admin,
        ]);

        $members = User::factory(3)->state(new Sequence(fn (Sequence $sequence) => [
            'email' => \sprintf('member-%d@example.com', $sequence->index + 1),
            'handle' => \sprintf('member%d', $sequence->index + 1),
        ]))->create(['role' => Role::Member]);

        $ownerPerson = Person::forceCreate([
            'uuid' => (string) Str::uuid7(),
            'user_id' => $owner->getKey(),
            'name' => $owner->name,
        ]);

        $organization = Organization::forceCreate([
            'uuid' => (string) Str::uuid7(),
            'contact_id' => $ownerPerson->getKey(),
            'name' => \sprintf('%s Org', $owner->name),
            'is_tenant' => true,
            'root_organization_id' => null,
        ]);

        app(AddMember::class)->handle(
            $organization,
            $ownerPerson,
            isPrimary: true,
            startDate: now(),
            role: Role::Owner,
        );

        $adminPerson = Person::forceCreate([
            'uuid' => (string) Str::uuid7(),
            'user_id' => $admin->getKey(),
            'name' => $admin->name,
        ]);

        app(AddMember::class)->handle(
            $organization,
            $adminPerson,
            startDate: now(),
            role: Role::Admin,
        );

        foreach ($members as $member) {
            $person = Person::forceCreate([
                'uuid' => (string) Str::uuid7(),
                'user_id' => $member->getKey(),
                'name' => $member->name,
            ]);

            app(AddMember::class)->handle(
                $organization,
                $person,
                startDate: now(),
                role: Role::Member,
            );
        }
    }

    private function initialUsers(): void
    {
        User::forceCreate([
            'name' => \sprintf('%s Owner', config('app.name')),
            'email' => config('setup.initial_email'),
            'handle' => 'owner',
            'role' => Role::Owner,
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
        ]);
    }
}
