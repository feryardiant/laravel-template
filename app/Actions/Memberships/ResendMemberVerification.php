<?php

declare(strict_types=1);

namespace App\Actions\Memberships;

use App\Models\Organization;
use App\Models\Person;
use App\Notifications\VerifyEmailForNewUser;
use App\Repositories\MembershipRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

/**
 * Resends the verification email to an unverified member.
 */
final class ResendMemberVerification
{
    public function __construct(
        private MembershipRepository $memberships,
    ) {
        // .
    }

    /**
     * Resend the verification email to a member with an unverified credential.
     */
    public function handle(Organization $organization, int $personId): Person
    {
        $person = $this->memberships->findInOrganization($organization, $personId, ['user'])
            ?? throw (new ModelNotFoundException)->setModel(Person::class);

        if ($person->user === null || $person->user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'member' => __('Unable to resend the verification email.'),
            ]);
        }

        $person->user->notify(new VerifyEmailForNewUser);

        return $person;
    }
}
