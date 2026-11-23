<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

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

        return $user->organizations()
            ->filter(fn (Organization $organization): bool => Gate::forUser($user)->allows('createSubsidiary', $organization))
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }
}
