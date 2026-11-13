<?php

declare(strict_types=1);

namespace App\Actions\Memberships;

use App\Events\MemberRemoved;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class LeaveOrganization
{
    /**
     * Remove the user's own membership from the organization.
     */
    public function handle(User $user, Organization $organization): bool
    {
        $person = $user->person;

        if ($person === null) {
            return false;
        }

        $left = DB::transaction(function () use ($person, $organization): bool {
            $organization = Organization::query()->whereKey($organization->getKey())->lockForUpdate()->first();

            if ($organization === null) {
                return false;
            }

            $role = $person->roleIn($organization);

            if ($role === null || ($role->isOwner() && $organization->activeOwnerCount() === 1)) {
                return false;
            }

            $organization->members()->detach($person->getKey());

            return true;
        });

        if (! $left) {
            return false;
        }

        event(new MemberRemoved($organization, $person, null, ! $person->organizations()->exists()));

        return true;
    }
}
