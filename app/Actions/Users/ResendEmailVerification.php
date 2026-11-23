<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;

final class ResendEmailVerification
{
    /**
     * Send a verification email when the user is still unverified.
     *
     * @return bool False when the user is already verified.
     */
    public function handle(User $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        $user->sendEmailVerificationNotification();

        return true;
    }
}
