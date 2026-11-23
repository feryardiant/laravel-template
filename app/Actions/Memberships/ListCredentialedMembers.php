<?php

declare(strict_types=1);

namespace App\Actions\Memberships;

use App\Models\Organization;
use App\Models\Person;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class ListCredentialedMembers
{
    /**
     * List members with a login credential.
     *
     * @return Collection<int, Person>
     */
    public function handle(
        Organization $organization,
        ?int $excludePersonId = null,
        bool $activeOnly = false,
    ): Collection {
        $today = now()->toDateString();

        return $organization->members()
            ->whereHas('user')
            ->when($excludePersonId !== null, fn (Builder $query): Builder => $query->whereKeyNot($excludePersonId))
            ->when($activeOnly, fn (Builder $query): Builder => $query
                ->where(fn (Builder $query): Builder => $query
                    ->whereNull('organization_relationships.start_date')
                    ->orWhere('organization_relationships.start_date', '<=', $today))
                ->where(fn (Builder $query): Builder => $query
                    ->whereNull('organization_relationships.finish_date')
                    ->orWhere('organization_relationships.finish_date', '>=', $today)))
            ->with('user')
            ->orderBy('people.name')
            ->get();
    }
}
