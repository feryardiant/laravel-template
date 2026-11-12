<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Actions\Users\CreateUser;
use App\Enums\Gender;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\Person;
use Illuminate\Support\Str;

trait ToManageEntities
{
    public function createOrganization(
        string $name,
        string $contactName,
        ?string $summary = null,
        ?string $logoPath = null,
        Gender|string|null $contactGender = null,
        ?string $contactSummary = null,
    ): Organization {
        $contactPerson = $this->createIndividual($contactName, $contactGender, summary: $contactSummary);

        return Organization::create([
            'contact_id' => $contactPerson->getKey(),
            'name' => $name,
            'summary' => $summary,
            'logo_url' => $logoPath,
        ]);
    }

    public function createIndividual(
        string $name,
        Gender|string|null $gender = null,
        ?string $email = null,
        ?string $photoPath = null,
        ?string $summary = null,
    ): Person {
        $user = $email ? app(CreateUser::class)->handle([
            'name' => $name,
            'email' => $email,
            'role' => Role::Member,
            'password' => Str::random(16),
        ]) : null;

        return Person::create([
            'user_id' => $user?->getKey(),
            'name' => $name,
            'gender' => $gender,
            'summary' => $summary,
            'photo_url' => $photoPath,
        ]);
    }
}
