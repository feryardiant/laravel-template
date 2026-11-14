<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Relationships\RelationshipTimeline;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
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
        $owner = User::factory()->withProfile()->create([
            'email' => 'owner@example.com',
            'handle' => 'owner',
        ]);

        $root = Organization::factory()
            ->asRootTenant()
            ->withMember($owner->person, role: Role::Owner, isPrimary: true)
            ->create([
                'contact_id' => $owner->person->getKey(),
                'name' => \sprintf('%s Org', $owner->name),
                'summary' => null,
            ]);

        User::factory()
            ->asMemberOf($root, role: Role::Admin, isPrimary: true)
            ->create([
                'email' => 'admin@example.com',
                'handle' => 'admin',
            ]);

        User::factory(3)
            ->asMemberOf($root, role: Role::Member, isPrimary: true)
            ->state(new Sequence(fn (Sequence $sequence) => [
                'email' => \sprintf('member%d@example.com', $sequence->index + 1),
                'handle' => \sprintf('member%d', $sequence->index + 1),
            ]))
            ->create();

        if (app()->environment('local')) {
            $this->call(DemoSeeder::class, parameters: ['root' => $root]);
        }

        $this->seedPeriods();
    }

    /**
     * Give the seeded relationships an opening history period.
     */
    private function seedPeriods(): void
    {
        $timeline = app(RelationshipTimeline::class);

        OrganizationRelationship::query()
            ->whereIn('type', [
                ...OrganizationRelationshipType::externals(),
                OrganizationRelationshipType::Member,
            ])
            ->eachById(fn (OrganizationRelationship $relationship) => $timeline->open($relationship));
    }

    private function initialUsers(): void
    {
        User::forceCreate([
            'name' => \sprintf('%s Owner', config('app.name')),
            'email' => config('setup.initial_email'),
            'handle' => 'owner',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
        ]);
    }
}
