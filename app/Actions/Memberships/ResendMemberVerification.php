<?php

declare(strict_types=1);

namespace App\Actions\Memberships;

use App\Models\Organization;
use App\Models\Person;
use App\Notifications\VerifyEmailForNewUser;
use Illuminate\Validation\ValidationException;

final class ResendMemberVerification
{
    public function __construct(
        private FindMember $findMember,
    ) {
        // .
    }

    /**
     * Resend the verification email to a member with an unverified credential.
     */
    public function handle(Organization $organization, int $personId): Person
    {
        $person = $this->findMember->inOrganization($organization, $personId, ['user']);

        if ($person->user === null || $person->user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'member' => __('Unable to resend the verification email.'),
            ]);
        }

        $person->user->notify(new VerifyEmailForNewUser);

        return $person;
    }
}
