<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;

/**
 * Reads a user's two-factor recovery codes.
 */
final class GetRecoveryCodes
{
    /**
     * Read the user's two-factor recovery codes.
     *
     * @return list<string>|null Null when two-factor is not enabled.
     */
    public function handle(User $user): ?array
    {
        if (! $user->hasEnabledTwoFactorAuthentication() || $user->two_factor_recovery_codes === null) {
            return null;
        }

        $codes = json_decode(decrypt($user->two_factor_recovery_codes), true);

        return \is_array($codes)
            ? array_values(array_map(fn (mixed $code): string => (string) $code, $codes))
            : null;
    }
}
