<?php

declare(strict_types=1);

namespace App\Actions;

use App\Actions\Contacts\CreateContact;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use RuntimeException;

final class ProvisionPublicTenant
{
    public function __construct(private CreateContact $createContact)
    {
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
            'name' => \sprintf('%s Org', $user->name),
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
        ]);

        $organization->members()->attach($organization->contact_id, [
            'type' => OrganizationRelationshipType::Member,
            'role' => Role::Owner,
            'is_primary' => true,
            'start_date' => now(),
        ]);

        return $organization;
    }
}
