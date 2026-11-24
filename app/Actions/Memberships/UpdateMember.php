<?php

declare(strict_types=1);

namespace App\Actions\Memberships;

use App\Actions\Addresses\SaveAddress;
use App\Actions\Contacts\SaveContactDetails;
use App\Actions\FileStorage;
use App\Actions\Users\CreateUser;
use App\Enums\RelationshipStatus;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Notifications\VerifyEmailForNewUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Updates a member profile, credential, role, and photo.
 */
final class UpdateMember
{
    public function __construct(
        private CreateUser $createUser,
        private ChangeMemberRole $changeMemberRole,
        private SaveAddress $saveAddress,
        private SaveContactDetails $saveContactDetails,
        private FileStorage $fileStorage,
        private FindMember $findMember,
    ) {
        // .
    }

    /**
     * Update a member profile, credential, membership role, and photo.
     *
     * @param  array{
     *     name: string,
     *     email: string,
     *     role: Role,
     *     gender: ?string,
     *     address: array<string, string|null>|null,
     *     details: array<string, string>,
     *     create_credential: bool,
     *     can_edit_profile: bool,
     *     remove_photo: bool
     * }  $data
     * @param  array{
     *     name: string,
     *     email: string,
     *     role: int,
     *     address: array<string, string|null>|null,
     *     details: array<string, string>,
     *     photo_url: ?string,
     *     has_credential: bool
     * }  $expected
     * @return array{person: Person, photo_url: ?string, email_changed: bool, credential_created: bool, photo_removal_failed: bool}
     */
    public function handle(
        Organization $organization,
        Person $person,
        User $actor,
        array $data,
        array $expected,
        ?UploadedFile $photo = null,
    ): array {
        $person = $this->findMember->inOrganization($organization, $person->getKey(), ['user']);
        $canEditProfile = $data['can_edit_profile'];

        if (! $canEditProfile) {
            $credentialEmailChanged = $expected['has_credential'] && $person->user !== null
                && Str::lower($person->user->email) !== $data['email']
                && $data['email'] !== $expected['email'];

            if ($data['name'] !== $expected['name']
                || $data['create_credential']
                || $photo instanceof UploadedFile
                || $credentialEmailChanged) {
                throw new AuthorizationException;
            }
        }

        $role = $data['role'];
        $membership = $person->membershipIn($organization);
        $currentRole = $membership?->role;

        if ($role->value === $expected['role'] && $role !== $currentRole) {
            // The editor did not change the role; keep the value saved elsewhere.
            $role = $currentRole ?? $role;
        }

        if ($role !== $currentRole) {
            if ($membership?->status !== RelationshipStatus::Active) {
                throw ValidationException::withMessages([
                    'role' => __('The role of an inactive membership cannot be changed.'),
                ]);
            }

            Gate::forUser($actor)->authorize('changeRole', [$person, $organization, $role]);
        }

        // Credentials are global: editing an email shared with other organizations
        // would hand them the account.
        if ($canEditProfile && $expected['has_credential'] && $person->user !== null
            && Str::lower($person->user->email) !== $data['email']
            && $data['email'] !== $expected['email']
            && $person->organizations()->whereKeyNot($organization->getKey())->exists()) {
            throw new AuthorizationException;
        }

        $previousPhoto = $person->getRawOriginal('photo_url');
        $removePhoto = $canEditProfile && $data['remove_photo'];
        $photoUrl = $removePhoto ? null : $previousPhoto;
        $stagedPhoto = null;

        if ($canEditProfile && $photo instanceof UploadedFile) {
            $stagedPhoto = $this->fileStorage->handle($photo, 'photos');
            $photoUrl = $stagedPhoto;
            $removePhoto = false;
        }

        $emailChanged = false;
        $credentialCreated = false;

        try {
            DB::transaction(function () use ($organization, $person, $actor, $data, $expected, $role, $currentRole, $canEditProfile, $stagedPhoto, $removePhoto, $photoUrl, &$emailChanged, &$credentialCreated): void {
                if ($canEditProfile) {
                    $attributes = [];

                    if ($data['name'] !== $expected['name']) {
                        $attributes['name'] = $data['name'];
                    }

                    if ($stagedPhoto !== null || $removePhoto) {
                        $attributes['photo_url'] = $photoUrl;
                    }

                    if ($data['gender'] !== null) {
                        $attributes['gender'] = $data['gender'];
                    }

                    if ($attributes !== []) {
                        $person->update($attributes);
                    }

                    if ($data['address'] !== $expected['address']) {
                        $this->saveAddress->handle($person, $data['address']);
                    }
                }

                // Communication records stay editable even when the profile is
                // managed elsewhere.
                $this->saveContactDetails->handle($person, $data['details']);

                if ($canEditProfile && $expected['has_credential']) {
                    if ($data['email'] !== $expected['email']) {
                        $user = $person->user;

                        if ($user === null) {
                            throw (new ModelNotFoundException)->setModel(User::class);
                        }

                        $emailChanged = $user->email !== $data['email'];

                        $user->email = $data['email'];

                        if ($emailChanged) {
                            $user->email_verified_at = null;
                        }

                        $user->save();
                    }
                } elseif ($canEditProfile && $data['create_credential']) {
                    $hasUser = Person::query()
                        ->whereKey($person->getKey())
                        ->lockForUpdate()
                        ->value('user_id') !== null;

                    if (! $hasUser) {
                        $user = $this->createUser->handle(
                            name: $person->name,
                            email: $data['email'],
                            password: Str::random(16),
                        );

                        $person->update(['user_id' => $user->getKey()]);

                        $credentialCreated = true;
                    }
                }

                if ($role !== $currentRole) {
                    $this->changeMemberRole->setRole($organization, $person, $role, $actor);
                }
            });
        } catch (Throwable $exception) {
            // A failure after the commit (such as the invite email) must not
            // delete a photo the committed member already references.
            if ($stagedPhoto !== null && ! Person::query()->where('photo_url', $stagedPhoto)->exists()) {
                $this->fileStorage->delete($stagedPhoto);
            }

            throw $exception;
        }

        if ($stagedPhoto !== null) {
            // The replacement is committed; a failed cleanup only leaves an
            // orphaned file, so it is logged rather than rolled back.
            $this->fileStorage->delete($previousPhoto);
        }

        $person = $person->fresh(['user']) ?? $person;

        $photoRemovalFailed = false;

        if ($removePhoto && ! $this->fileStorage->delete($previousPhoto)) {
            $restored = Person::query()
                ->whereKey($person->getKey())
                ->whereNull('photo_url')
                ->update(['photo_url' => $previousPhoto]);

            $photoUrl = $restored === 0
                ? ($person->refresh())->getRawOriginal('photo_url')
                : $previousPhoto;

            $photoRemovalFailed = true;
        }

        if (($expected['has_credential'] && $emailChanged) || $credentialCreated) {
            $person->user?->notify(new VerifyEmailForNewUser);
        }

        return [
            'person' => $person,
            'photo_url' => $photoUrl,
            'email_changed' => $emailChanged,
            'credential_created' => $credentialCreated,
            'photo_removal_failed' => $photoRemovalFailed,
        ];
    }
}
