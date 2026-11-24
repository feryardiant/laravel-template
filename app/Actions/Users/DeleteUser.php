<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deletes a user account unless it is an organization's last owner.
 */
final class DeleteUser
{
    /**
     * Delete the user unless they are the last owner of an active organization.
     */
    public function handle(User $user, ?callable $beforeDelete = null): void
    {
        DB::transaction(function () use ($user, $beforeDelete): void {
            if ($this->isSoleOwner($user)) {
                throw ValidationException::withMessages([
                    'password' => __('The last owner cannot delete their account.'),
                ]);
            }

            if ($beforeDelete !== null) {
                $beforeDelete();
            }

            $user->delete();
        });
    }

    /**
     * Determine whether the user is the only owner of an active organization.
     */
    private function isSoleOwner(User $user): bool
    {
        $person = $user->person;

        if ($person === null) {
            return false;
        }

        $organizationIds = $person->organizations()
            ->wherePivot('role', Role::Owner->value)
            ->orderBy('organizations.id')
            ->pluck('organizations.id');

        foreach ($organizationIds as $organizationId) {
            // Serialize concurrent account deletions for this organization; the
            // count below then starts from a fresh snapshot.
            $organization = Organization::query()->whereKey($organizationId)->lockForUpdate()->firstOrFail();

            if ($organization->activeOwnerCount() === 1) {
                return true;
            }
        }

        return false;
    }
}
