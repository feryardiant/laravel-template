<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\PersonRelativeType;
use App\Models\Address;
use App\Models\Person;
use App\Models\User;
use Faker\Provider\Person as FakerPerson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for person records.
 *
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = fake()->randomElement(Gender::cases());
        $fakerGender = $this->getFakerGender($gender);

        return [
            'user_id' => User::factory(),
            'name' => fake()->firstName($fakerGender).' '.fake()->lastName($fakerGender),
            'gender' => $gender,
            'summary' => fake()->sentence(),
        ];
    }

    /**
     * Create a person without a user credential.
     */
    public function withoutCredential(): static
    {
        return $this->state(fn () => [
            'user_id' => null,
        ]);
    }

    /**
     * Create a person with the given gender and a matching name.
     */
    public function withGender(Gender $gender): static
    {
        $fakerGender = $this->getFakerGender($gender);

        return $this->state(fn () => [
            'name' => fake()->firstName($fakerGender).' '.fake()->lastName($fakerGender),
            'gender' => $gender,
        ]);
    }

    /**
     * Attach the given number of addresses to the person.
     *
     * @param  ?int  $count  Number of addresses to attach.
     */
    public function withAddresses(?int $count = null): static
    {
        return $this->has(Address::factory()->count($count));
    }

    /**
     * Attach a personal relative of the given type.
     */
    public function withPersonalRelative(PersonFactory $relative, PersonRelativeType $type): static
    {
        return $this->hasAttached($relative, ['type' => $type], 'personalRelative');
    }

    /**
     * Map the gender to Faker's person provider.
     */
    private function getFakerGender(Gender $gender): string
    {
        return match ($gender) {
            Gender::Male => FakerPerson::GENDER_MALE,
            Gender::Female => FakerPerson::GENDER_FEMALE,
        };
    }
}
