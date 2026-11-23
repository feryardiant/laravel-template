<?php

declare(strict_types=1);

namespace App\Actions\Memberships;

use App\Models\Organization;
use App\Models\Person;
use App\Notifications\VerifyEmailForNewUser;

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

        abort_if($person->user === null || $person->user->hasVerifiedEmail(), 422);

        $person->user->notify(new VerifyEmailForNewUser);

        return $person;
    }
}
