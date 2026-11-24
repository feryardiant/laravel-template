<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;

/**
 * Updates a user's password.
 */
final class UpdatePassword
{
    /**
     * Update the user's password.
     */
    public function handle(User $user, string $password): void
    {
        $user->update(['password' => $password]);
    }
}
