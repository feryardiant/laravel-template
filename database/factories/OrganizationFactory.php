<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Address;
use App\Models\Organization;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for organization records.
 *
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => Person::factory(),
            'name' => fake()->company(),
            'summary' => fake()->sentence(),
        ];
    }

    /**
     * Attach an address to the organization.
     */
    public function withAddress(): static
    {
        return $this->has(Address::factory());
    }
}
