<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
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
        $customers = User::factory(5)
            ->withProfile()
            ->state(new Sequence(fn (Sequence $seq) => [
                'email' => \sprintf('owner%1$d@customer%1$d.com', $seq->index + 1),
                'handle' => \sprintf('owner%1$d-cust%1$d', $seq->index + 1),
            ]))
            ->create();

        foreach ($customers as $customer) {
            $org = Organization::factory()
                ->asTenantOf($root)
                ->asCustomerOf($root)
                ->withMember($customer->person, role: Role::Owner, isPrimary: true)
                ->create([
                    'contact_id' => $customer->person->getKey(),
                    'name' => \sprintf('%s Org', $customer->name),
                    'summary' => null,
                ]);

            Person::factory(fake()->numberBetween(2, 5))
                ->withoutCredential()
                ->asMemberOf($org, role: Role::Member, isPrimary: true)
                ->create();
        }
    }
}
