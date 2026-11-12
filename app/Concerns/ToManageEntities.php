<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Actions\Users\CreateUser;
use App\Enums\Gender;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

use function Illuminate\Support\now;

trait ToManageEntities
{
    public function createOrganization(
        string $name,
        string $contactName,
        ?string $summary = null,
        ?string $logoPath = null,
        Gender|string|null $contactGender = null,
        ?string $contactSummary = null,
        ?User $user = null,
    ): Organization {
        $contactPerson = $this->createIndividual($contactName, $contactGender, summary: $contactSummary, user: $user);

        /** @var Organization $organization */
        $organization = Organization::create([
            'contact_id' => $contactPerson->getKey(),
            'name' => $name,
            'summary' => $summary,
            'logo_url' => $logoPath,
        ]);

        $organization->members()->attach($contactPerson, [
            'type' => OrganizationRelationshipType::Member,
            'role' => Role::Owner,
            'is_primary' => true,
            'start_date' => now(),
        ]);

        return $organization;
    }

    public function createIndividual(
        string $name,
        Gender|string|null $gender = null,
        ?string $email = null,
        ?string $photoPath = null,
        ?string $summary = null,
        ?User $user = null,
    ): Person {
        if (! $user && $email) {
            $user = app(CreateUser::class)->handle(
                name: $name,
                email: $email,
                password: Str::random(16),
            );
        }

        return Person::create([
            'user_id' => $user?->getKey(),
            'name' => $name,
            'gender' => $gender,
            'summary' => $summary,
            'photo_url' => $photoPath,
        ]);
    }

    private function ensureExternalRole(OrganizationRelationshipType|string|null $role): OrganizationRelationshipType
    {
        $role = $this->role($role);

        if (! $role->isExternal()) {
            throw new InvalidArgumentException('Invalid contact role.');
        }

        return $role;
    }

    private function role(OrganizationRelationshipType|string|null $role): OrganizationRelationshipType
    {
        return $role instanceof OrganizationRelationshipType
            ? $role
            : OrganizationRelationshipType::from((string) $role);
    }
}
