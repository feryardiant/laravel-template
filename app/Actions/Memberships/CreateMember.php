<?php

declare(strict_types=1);

namespace App\Actions\Memberships;

use App\Actions\Addresses\SaveAddress;
use App\Actions\Contacts\SaveContactDetails;
use App\Actions\FileStorage;
use App\Actions\People\CreatePerson;
use App\Actions\Relationships\RelationshipTimeline;
use App\Enums\ContactDetailType;
use App\Enums\OrganizationRelationshipType;
use App\Enums\RelationshipPeriodReason;
use App\Enums\Role;
use App\Events\Memberships\MemberAdded;
use App\Events\Memberships\MemberRoleChanged;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Models\User;
use App\Notifications\VerifyEmailForNewUser;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final class CreateMember
{
    public function __construct(
        private SaveAddress $saveAddress,
        private RelationshipTimeline $timeline,
        private SaveContactDetails $saveContactDetails,
        private CreatePerson $createPerson,
        private FileStorage $fileStorage,
    ) {
        // .
    }

    /**
     * Create a person, optional credential, and organization membership.
     *
     * @param  array<string, mixed>|null  $address
     */
    public function handle(
        Organization $organization,
        string $name,
        Role $role,
        ?string $email = null,
        ?UploadedFile $photo = null,
        ?User $actor = null,
        ?array $address = null,
        ?string $contactEmail = null,
        ?string $contactPhone = null,
    ): Person {
        if ($role->isOwner()) {
            throw new InvalidArgumentException('Owner is assigned by transferring ownership.');
        }

        $user = null;
        $photoPath = $photo instanceof UploadedFile ? $this->fileStorage->handle($photo, 'photos') : null;

        try {
            $person = DB::transaction(function () use ($organization, $name, $role, $email, $photoPath, $actor, $address, $contactEmail, $contactPhone, &$user): Person {
                $person = $this->createPerson->handle($name, email: $email, photoPath: $photoPath, owner: $organization);
                $user = $person->user;

                $this->saveAddress->handle($person, $address);

                $this->saveContactDetails->handle($person, [
                    ContactDetailType::Email->value => $contactEmail,
                    ContactDetailType::Phone->value => $contactPhone,
                ]);

                $this->attach(
                    $organization,
                    $person,
                    startDate: now(),
                    role: $role,
                    actor: $actor,
                );

                return $person;
            });

            if ($user instanceof User) {
                $user->notify(new VerifyEmailForNewUser);
            }

            return $person;
        } catch (Throwable $exception) {
            // A failure after the commit (such as the invite email) must not
            // delete a photo the committed member already references.
            if ($photoPath !== null && ! Person::query()->where('photo_url', $photoPath)->exists()) {
                $this->fileStorage->delete($photoPath);
            }

            throw $exception;
        }
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
        ?User $actor = null,
    ): void {
        DB::transaction(function () use ($organization, $member, $isPrimary, $startDate, $finishDate, $role, $actor): void {
            $member->newQuery()
                ->whereKey($member->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // A credential-less person with no workspace joins as an org-owned
            // member; self-registered people and existing contacts keep ownership.
            if ($member->owner_organization_id === null
                && $member->user_id === null
                && ! $member->memberships()->exists()) {
                $member->update(['owner_organization_id' => $organization->getKey()]);
            }

            // A person's first workspace is their default; later memberships
            // only become the default when explicitly requested.
            $isPrimary = $isPrimary || ! $member->memberships()->exists();

            if ($isPrimary) {
                // shortcut: primary membership is global until relationship rows
                // carry a tenant key; then scope this reset to the organization.
                $member->organizationRelationships()->newPivotQuery()
                    ->where('type', OrganizationRelationshipType::Member)
                    ->update(['is_primary' => null]);
            }

            $existing = OrganizationRelationship::query()
                ->forOrganization($organization)
                ->whereStakeholder($member)
                ->members()
                ->lockForUpdate()
                ->first();

            $previousRole = $existing?->role;
            $roleChanged = $existing !== null && $previousRole instanceof Role && $previousRole !== $role;

            if ($roleChanged) {
                $this->timeline->close($existing, RelationshipPeriodReason::RoleChanged, $actor);
            }

            $attributes = [
                'is_primary' => $isPrimary ? true : null,
                'role' => $role,
            ];

            if ($existing === null) {
                $attributes['start_date'] = $startDate;
                $attributes['finish_date'] = $finishDate;
            } else {
                if ($startDate !== null) {
                    $attributes['start_date'] = $startDate;
                }

                if ($finishDate !== null) {
                    $attributes['finish_date'] = $finishDate;
                }
            }

            OrganizationRelationship::query()->updateOrCreate(
                [
                    'organization_id' => $organization->getKey(),
                    'stakeholder_type' => $member->getMorphClass(),
                    'stakeholder_id' => $member->getKey(),
                    'type' => OrganizationRelationshipType::Member,
                ],
                $attributes,
            );

            $relationship = OrganizationRelationship::query()
                ->forOrganization($organization)
                ->whereStakeholder($member)
                ->members()
                ->firstOrFail();

            $period = $this->timeline->open(
                $relationship,
                $roleChanged ? ($startDate ?? today()) : null,
            );

            if ($existing === null) {
                event(new MemberAdded($organization, $actor, $member, $role, $period));
            } elseif ($roleChanged) {
                event(new MemberRoleChanged($organization, $previousRole, $role, $member, $actor, $period));
            }
        });
    }
}
