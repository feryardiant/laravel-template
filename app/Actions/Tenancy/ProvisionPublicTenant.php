<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Actions\Contacts\CreateContact;
use App\Actions\Relationships\RelationshipTimeline;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

final class ProvisionPublicTenant
{
    public function __construct(
        private CreateContact $createContact,
        private RelationshipTimeline $timeline,
    ) {
        // .
    }

    /**
     * Turn a public registration into a customer tenant owned by its person.
     */
    public function handle(User $user): Organization
    {
        $root = Organization::query()->rootTenant()->lockForUpdate()->first();

        if ($root === null) {
            throw new RuntimeException('The root tenant has not been provisioned.');
        }

        $organization = $this->createContact->handle($root, [
            'name' => Str::limit(__('Organization for :name', ['name' => $user->name]), 255, ''),
            'role' => OrganizationRelationshipType::Customer,
            'type' => 'organization',
            'start_date' => now(),
            'contact_name' => $user->name,
            'user' => $user,
        ]);

        \assert($organization instanceof Organization);

        $organization->update([
            'is_tenant' => true,
            'root_organization_id' => $root->getKey(),
            'owner_organization_id' => $organization->getKey(),
        ]);

        $organization->primaryContact?->update(['owner_organization_id' => null]);

        $organization->members()->attach($organization->contact_id, [
            'type' => OrganizationRelationshipType::Member,
            'role' => Role::Owner,
            'is_primary' => true,
            'start_date' => now(),
        ]);

        $membership = OrganizationRelationship::query()
            ->forOrganization($organization)
            ->where('stakeholder_type', (new Person)->getMorphClass())
            ->where('stakeholder_id', $organization->contact_id)
            ->where('type', OrganizationRelationshipType::Member)
            ->first();

        if ($membership !== null) {
            $this->timeline->open($membership);
        }

        return $organization;
    }
}
