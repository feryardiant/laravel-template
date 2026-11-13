<?php

declare(strict_types=1);

namespace App\Actions\Memberships;

use App\Events\MemberRemoved;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

final class RemoveMember
{
    /**
     * Remove a person from the organization.
     */
    public function handle(Organization $organization, Person $person, ?User $actor = null): void
    {
        $this->handleMany($organization, new Collection([$person]), $actor);
    }

    /**
     * Remove several people from the organization in one transaction.
     *
     * @param  Collection<int, Person>  $persons
     */
    public function handleMany(Organization $organization, Collection $persons, ?User $actor = null): void
    {
        if ($persons->isEmpty()) {
            return;
        }

        DB::transaction(fn () => $organization->members()->detach($persons->modelKeys()));

        foreach ($persons as $person) {
            try {
                event(new MemberRemoved(
                    $organization,
                    $person,
                    $actor,
                    ! $person->organizations()->exists(),
                ));
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
