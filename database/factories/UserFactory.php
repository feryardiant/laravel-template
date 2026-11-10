<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Actions\Organizations\AddMember;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName().' '.fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'role' => Role::Member,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    public function withRole(Role $role): static
    {
        return $this->state(fn () => ['role' => $role]);
    }

    /**
     * Attach a person profile to the user.
     */
    public function withPerson(?Person $person = null): static
    {
        return $this->afterCreating(function (User $user) use ($person): void {
            $person ??= Person::factory()->withoutCredential()->create(['name' => $user->name]);

            $person->update(['user_id' => $user->getKey()]);
        });
    }

    /**
     * Add the user as a member of the given organization.
     */
    public function memberOf(
        Organization $organization,
        Role $role = Role::Member,
        bool $primary = false,
    ): static {
        return $this->afterCreating(function (User $user) use ($organization, $role, $primary): void {
            $person = $user->person()->first()
                ?? Person::factory()->withoutCredential()->create([
                    'user_id' => $user->getKey(),
                    'name' => $user->name,
                ]);

            app(AddMember::class)->handle(
                $organization,
                $person,
                isPrimary: $primary,
                startDate: now(),
                role: $role,
            );
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
