<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Actions\People\CreatePerson;
use App\Enums\Gender;
use App\Models\Organization;
use App\Models\User;

final class CreateContactOrganization
{
    public function __construct(
        private CreatePerson $createPerson,
    ) {
        // .
    }

    /**
     * Create a contact organization and its primary contact person.
     */
    public function handle(
        string $name,
        string $contactName,
        ?string $summary = null,
        ?string $logoPath = null,
        Gender|string|null $contactGender = null,
        ?string $contactSummary = null,
        ?User $user = null,
        ?Organization $owner = null,
    ): Organization {
        $contactPerson = $this->createPerson->handle(
            name: $contactName,
            gender: $contactGender,
            summary: $contactSummary,
            user: $user,
            owner: $owner,
        );

        return Organization::create([
            'contact_id' => $contactPerson->getKey(),
            'owner_organization_id' => $owner?->getKey(),
            'name' => $name,
            'summary' => $summary,
            'logo_url' => $logoPath,
        ]);
    }
}
