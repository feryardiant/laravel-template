<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Contacts\CreateContact;
use App\Enums\OrganizationRelationshipType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Events\Registered;

use function Illuminate\Support\now;

class PublicRegisteredUser
{
    public function __construct(private CreateContact $createContact)
    {
        // .
    }

    public function handle(Registered $event): void
    {
        /** @var Organization $root */
        $root = Organization::rootTenant()->first();
        /** @var User $user */
        $user = $event->user;

        $organization = $this->createContact->handle($root, [
            'name' => \sprintf('%s Org', $user->name),
            'role' => OrganizationRelationshipType::Customer,
            'type' => 'organization',
            'start_date' => now(),
            'contact_name' => $user->name,
            'user' => $user,
        ]);

        $organization->update(['is_tenant' => true, 'root_organization_id' => $root->getKey()]);
    }
}
