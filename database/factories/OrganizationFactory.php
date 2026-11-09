<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Actions\Organizations\AddMember;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Models\Address;
use App\Models\Organization;
use App\Models\Person;
use Carbon\CarbonInterface;
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
            'contact_id' => Person::factory(),
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

    /**
     * Attach a person as a member of the organization.
     */
    public function withMember(
        ?Person $member = null,
        bool $isPrimary = false,
        ?CarbonInterface $startDate = null,
        ?CarbonInterface $finishDate = null,
        Role $role = Role::Member,
    ): static {
        return $this->afterCreating(function (Organization $organization) use ($member, $isPrimary, $startDate, $finishDate, $role): void {
            app(AddMember::class)->handle(
                $organization,
                $member ?? Person::factory()->create(),
                $isPrimary,
                $startDate,
                $finishDate,
                $role,
            );
        });
    }

    /**
     * Attach an external counterparty to the organization.
     */
    public function withCounterparty(
        Organization|Person $counterparty,
        OrganizationRelationshipType $type,
        ?string $code = null,
        ?CarbonInterface $startDate = null,
        ?CarbonInterface $finishDate = null,
    ): static {
        return $this->afterCreating(function (Organization $organization) use ($counterparty, $type, $code, $startDate, $finishDate): void {
            $relationship = $counterparty instanceof Person
                ? $organization->personCounterparties($type)
                : $organization->organizationCounterparties($type);

            $relationship->attach($counterparty, [
                'type' => $type,
                'code' => $code,
                'start_date' => $startDate,
                'finish_date' => $finishDate,
            ]);
        });
    }
}
