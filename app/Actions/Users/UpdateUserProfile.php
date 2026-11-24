<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Actions\Contacts\SaveContactDetails;
use App\Actions\FileStorage;
use App\Actions\People\CreatePerson;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Updates the authenticated user's account and person profile.
 */
final class UpdateUserProfile
{
    public function __construct(
        private CreatePerson $createPerson,
        private SaveContactDetails $saveContactDetails,
        private FileStorage $fileStorage,
    ) {
        // .
    }

    /**
     * Update the authenticated user's profile, person, avatar, and details.
     *
     * @param  array{
     *     name: string,
     *     handle: string,
     *     email: string,
     *     summary: string,
     *     gender: ?string,
     *     gender_editable: bool,
     *     details: array<string, string>,
     *     can_edit: bool,
     *     remove_avatar: bool
     * }  $data
     * @param  array{
     *     name: string,
     *     handle: string,
     *     email: string,
     *     summary: string,
     *     details: array<string, string>,
     *     avatar_url: ?string
     * }  $expected
     * @return array{user: User, avatar_url: ?string, avatar_removal_failed: bool}
     */
    public function handle(
        User $user,
        array $data,
        array $expected,
        ?UploadedFile $avatar = null,
    ): array {
        $person = $user->person;
        $previousAvatar = $person?->getRawOriginal('photo_url');
        $removeAvatar = $data['can_edit'] && $data['remove_avatar'];
        $avatarPath = $removeAvatar ? null : $previousAvatar;
        $stagedAvatar = null;

        if ($data['can_edit'] && $avatar instanceof UploadedFile) {
            $stagedAvatar = $this->fileStorage->handle($avatar, 'avatars');
            $avatarPath = $stagedAvatar;
            $removeAvatar = false;
        }

        try {
            DB::transaction(function () use ($user, $data, $expected, $person, $stagedAvatar, $removeAvatar, $avatarPath): void {
                $person = $person ?? $this->createPerson->handle(
                    name: $data['can_edit'] ? $data['name'] : $user->name,
                    user: $user,
                );

                $user->setRelation('person', $person);

                $this->saveContactDetails->handle($person, $data['details']);

                $attributes = [];

                if ($data['summary'] !== $expected['summary']) {
                    $attributes['summary'] = $data['summary'] !== '' ? $data['summary'] : null;
                }

                if ($data['can_edit'] && $data['name'] !== $expected['name']) {
                    $attributes['name'] = $data['name'];
                }

                if ($stagedAvatar !== null || $removeAvatar) {
                    $attributes['photo_url'] = $avatarPath;
                }

                if ($data['gender_editable']) {
                    $attributes['gender'] = $data['gender'];
                }

                if ($attributes !== []) {
                    $person->update($attributes);
                }

                if ($data['handle'] !== $expected['handle']) {
                    $user->handle = $data['handle'];
                }

                if ($data['can_edit'] && $data['email'] !== $expected['email']) {
                    $user->email = $data['email'];
                }

                if ($user->isDirty('email')) {
                    $user->email_verified_at = null;
                }

                $user->save();
            });
        } catch (Throwable $exception) {
            $this->fileStorage->delete($stagedAvatar);

            throw $exception;
        }

        if ($stagedAvatar !== null) {
            // The replacement is committed; a failed cleanup only leaves an
            // orphaned file, so it is logged rather than rolled back.
            $this->fileStorage->delete($previousAvatar);
        }

        $fresh = $user->fresh();
        $avatarUrl = $avatarPath;
        $avatarRemovalFailed = false;

        if ($removeAvatar && ! $this->fileStorage->delete($previousAvatar)) {
            $restored = Person::query()
                ->whereKey($fresh?->person?->getKey())
                ->whereNull('photo_url')
                ->update(['photo_url' => $previousAvatar]);

            $avatarUrl = $restored === 0
                ? $fresh?->person?->refresh()->getRawOriginal('photo_url')
                : $previousAvatar;

            $avatarRemovalFailed = true;
        }

        return [
            'user' => $fresh ?? $user,
            'avatar_url' => $avatarUrl,
            'avatar_removal_failed' => $avatarRemovalFailed,
        ];
    }
}
