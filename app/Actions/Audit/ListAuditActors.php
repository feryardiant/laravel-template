<?php

declare(strict_types=1);

namespace App\Actions\Audit;

use App\Actions\Memberships\ListCredentialedMembers;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class ListAuditActors
{
    public function __construct(
        private ListCredentialedMembers $members,
    ) {
        // .
    }

    /**
     * List members with a credential, current user first.
     *
     * @return Collection<int, Person>
     */
    public function handle(Organization $organization, ?User $current): Collection
    {
        $currentId = $current?->getKey();
        $members = $this->members->handle($organization);
        $currentPerson = $current?->person;

        if ($currentPerson instanceof Person
            && ! $members->contains(fn (Person $member): bool => $member->user_id === $currentId)) {
            $currentPerson->loadMissing('user');
            $members->push($currentPerson);
        }

        return $members
            ->sortBy(fn (Person $member): int => $member->user_id === $currentId ? 0 : 1)
            ->values();
    }
}
