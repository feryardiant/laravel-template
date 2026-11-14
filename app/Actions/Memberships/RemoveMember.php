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

        $removed = DB::transaction(function () use ($organization, $persons, $actor): Collection {
            $organization = Organization::query()->whereKey($organization->getKey())->lockForUpdate()->first();

            if ($organization === null) {
                return new Collection;
            }

            $actorRole = $actor?->person?->roleIn($organization);

            if ($actor !== null && ($actorRole?->isOwner() !== true && $actorRole?->isAdmin() !== true)) {
                return new Collection;
            }

            // Owners only leave through `LeaveOrganization` or an ownership
            // transfer, even when the removal was authorized before a transfer.
            $removed = $persons->reject(
                fn (Person $person): bool => $person->roleIn($organization)?->isOwner() === true,
            );

            $organization->members()->detach($removed->modelKeys());

            return $removed;
        });

        foreach ($removed as $person) {
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
