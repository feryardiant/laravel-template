<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
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
    public function withProfile(
        ?PersonFactory $person = null,
        OrganizationFactory|Organization|null $asMemberOf = null,
        ?Role $role = null,
        ?true $isPrimary = null,
    ): static {
        if (! $person) {
            $person = PersonFactory::new()
                // @phpstan-ignore-next-line
                ->state(fn (array $attr, ?User $parent): array => [
                    'name' => $parent?->name ?: $attr['name'],
                ]);
        }

        if ($asMemberOf) {
            $person = $person->asMemberOf($asMemberOf, role: $role, isPrimary: $isPrimary);
        }

        return $this->has($person, 'person');
    }

    /**
     * Add the user as a member of the given organization.
     */
    public function asMemberOf(
        OrganizationFactory|Organization|null $organization,
        Role $role = Role::Member,
        ?true $isPrimary = null,
    ): static {
        return $this->withProfile(asMemberOf: $organization, role: $role, isPrimary: $isPrimary);
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
