<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Address;
use Creasi\Nusa\Models\Village;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for address records.
 *
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $village = $this->getRandomVillage();

        return [
            'line' => fake()->streetAddress(),
            'rt' => fake()->numberBetween(1, 15),
            'rw' => fake()->numberBetween(1, 10),
            'village_code' => $village->code,
            'district_code' => $village->district_code,
            'regency_code' => $village->regency_code,
            'province_code' => $village->province_code,
            'postal_code' => $village->postal_code,
        ];
    }

    /**
     * Pick a random village to source the address codes from.
     */
    private function getRandomVillage(): Village
    {
        return Village::query()->inRandomOrder()->first();
    }
}
