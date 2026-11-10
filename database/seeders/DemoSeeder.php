<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Organization;
use App\Models\Person;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(?Organization $root = null): void
    {
        if (! $root) {
            return;
        }

        $this->createTenants($root);
    }

    private function createTenants(Organization $root): void
    {
        $people = Person::factory(5)
            ->withoutCredential()
            ->create();

        foreach ($people as $person) {
            $org = Organization::factory()
                ->asTenantOf($root)
                ->asCustomerOf($root)
                ->withMember($person, role: Role::Owner, isPrimary: true)
                ->create([
                    'contact_id' => $person->getKey(),
                    'name' => \sprintf('%s Org', $person->name),
                    'summary' => null,
                ]);

            Person::factory(fake()->numberBetween(2, 5))
                ->withoutCredential()
                ->asMemberOf($org, role: Role::Member, isPrimary: true)
                ->create();
        }
    }
}
