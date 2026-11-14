<?php

declare(strict_types=1);

namespace Database\Factories;

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
            'uuid' => fake()->uuid(),
            'contact_id' => Person::factory(),
            'name' => fake()->company(),
            'summary' => fake()->sentence(),
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Organization $organization): void {
            if ($organization->is_tenant) {
                $organization->update(['owner_organization_id' => $organization->getKey()]);
            }

            $organization->members()
                ->whereNull('owner_organization_id')
                ->update(['owner_organization_id' => $organization->getKey()]);
        });
    }

    public function asRootTenant(): static
    {
        return $this->state(fn () => [
            'is_tenant' => true,
            'root_organization_id' => null,
        ]);
    }

    public function asTenantOf(OrganizationFactory|Organization $root): static
    {
        return $this->state(fn () => [
            'is_tenant' => true,
            'root_organization_id' => $root instanceof Organization ? $root->id : $root,
        ]);
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
        PersonFactory|Person|null $member = null,
        Role $role = Role::Member,
        ?true $isPrimary = null,
        ?CarbonInterface $startDate = null,
        ?CarbonInterface $finishDate = null,
    ): static {
        return $this->hasAttached($member ?? Person::factory(), fn () => [
            'type' => OrganizationRelationshipType::Member,
            'role' => $role,
            'is_primary' => $isPrimary,
            'start_date' => $startDate ?: now(),
            'finish_date' => $finishDate,
        ], 'members');
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

            if ($counterparty->owner_organization_id === null) {
                $counterparty->update(['owner_organization_id' => $organization->getKey()]);
            }
        });
    }

    public function asVendorOf(
        OrganizationFactory|Organization $organization,
        ?CarbonInterface $startDate = null,
        ?CarbonInterface $finishDate = null,
    ): static {
        return $this->asCounterpartyOf($organization, OrganizationRelationshipType::Vendor, $startDate, $finishDate);
    }

    public function asSupplierOf(
        OrganizationFactory|Organization $organization,
        ?CarbonInterface $startDate = null,
        ?CarbonInterface $finishDate = null,
    ): static {
        return $this->asCounterpartyOf($organization, OrganizationRelationshipType::Supplier, $startDate, $finishDate);
    }

    public function asCustomerOf(
        OrganizationFactory|Organization $organization,
        ?CarbonInterface $startDate = null,
        ?CarbonInterface $finishDate = null,
    ): static {
        return $this->asCounterpartyOf($organization, OrganizationRelationshipType::Customer, $startDate, $finishDate);
    }

    public function asCounterpartyOf(
        OrganizationFactory|Organization $organization,
        OrganizationRelationshipType $type,
        ?CarbonInterface $startDate = null,
        ?CarbonInterface $finishDate = null,
    ): static {
        return $this->afterCreating(function (Organization $counterparty) use ($organization, $type, $startDate, $finishDate): void {
            $tenant = $organization instanceof Organization ? $organization : $organization->createOne();

            $tenant->organizationCounterparties($type)->attach($counterparty, [
                'type' => $type,
                'start_date' => $startDate ?: now(),
                'finish_date' => $finishDate,
            ]);

            if ($counterparty->owner_organization_id === null) {
                $counterparty->update(['owner_organization_id' => $tenant->getKey()]);
            }
        });
    }
}
