<?php

declare(strict_types=1);

namespace App\Actions\Memberships;

use App\Actions\Users\CreateUser;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Models\User;
use App\Notifications\VerifyEmailForNewUser;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateMember
{
    public function __construct(
        private CreateUser $createUser,
    ) {
        // .
    }

    /**
     * Create a person, optional credential, and organization membership.
     */
    public function handle(
        Organization $organization,
        string $name,
        Role $role,
        ?string $email = null,
        ?string $photoUrl = null,
    ): Person {
        $user = null;

        $person = DB::transaction(function () use ($organization, $name, $role, $email, $photoUrl, &$user): Person {
            if ($email !== null) {
                $user = $this->createUser->handle([
                    'name' => $name,
                    'email' => Str::lower($email),
                    'role' => Role::Member,
                    'password' => Str::random(16),
                ]);
            }

            $person = Person::create([
                'user_id' => $user?->getKey(),
                'name' => $name,
                'photo_url' => $photoUrl,
            ]);

            $this->attach(
                $organization,
                $person,
                startDate: now(),
                role: $role,
            );

            return $person;
        });

        if ($user instanceof User) {
            $user->notify(new VerifyEmailForNewUser);
        }

        return $person;
    }

    /**
     * Add a person as a member of the organization.
     */
    public function attach(
        Organization $organization,
        Person $member,
        bool $isPrimary = false,
        ?CarbonInterface $startDate = null,
        ?CarbonInterface $finishDate = null,
        Role $role = Role::Member,
    ): void {
        DB::transaction(function () use ($organization, $member, $isPrimary, $startDate, $finishDate, $role): void {
            $member->newQuery()
                ->whereKey($member->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($isPrimary) {
                // shortcut: primary membership is global until relationship rows
                // carry a tenant key; then scope this reset to the organization.
                $member->organizationRelationships()->newPivotQuery()
                    ->where('type', OrganizationRelationshipType::Member)
                    ->update(['is_primary' => null]);
            }

            OrganizationRelationship::query()->updateOrCreate(
                [
                    'organization_id' => $organization->getKey(),
                    'stakeholder_type' => $member->getMorphClass(),
                    'stakeholder_id' => $member->getKey(),
                    'type' => OrganizationRelationshipType::Member,
                ],
                [
                    'is_primary' => $isPrimary ? true : null,
                    'role' => $role,
                    'start_date' => $startDate,
                    'finish_date' => $finishDate,
                ],
            );
        });
    }
}
