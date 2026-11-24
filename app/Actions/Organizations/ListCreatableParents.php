<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Collection;

final class ListCreatableParents
{
    public function __construct(
        private ListSubsidiaries $listSubsidiaries,
    ) {
        // .
    }

    /**
     * List organizations that can act as the parent.
     *
     * @return Collection<int, Organization>
     */
    public function handle(User $user, bool $internal): Collection
    {
        if ($internal) {
            $root = Organization::query()->rootTenant()->first();

            return $root === null
                ? collect()
                : $this->listSubsidiaries->handle($root)->pluck('organization');
        }

        $person = $user->person;

        if ($person === null) {
            return collect();
        }

        // Mirrors OrganizationPolicy::createSubsidiary without a role query per organization.
        return $person->organizations()
            ->wherePivot('role', Role::Owner->value)
            ->get()
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }
}
