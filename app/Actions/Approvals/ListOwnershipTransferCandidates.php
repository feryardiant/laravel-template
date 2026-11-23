<?php

declare(strict_types=1);

namespace App\Actions\Approvals;

use App\Actions\Memberships\ListCredentialedMembers;
use App\Models\Organization;
use App\Models\Person;
use Illuminate\Database\Eloquent\Collection;

final class ListOwnershipTransferCandidates
{
    public function __construct(
        private ListCredentialedMembers $members,
    ) {
        // .
    }

    /**
     * List active, credentialed members other than the owner.
     *
     * @return Collection<int, Person>
     */
    public function handle(Organization $organization, Person $owner): Collection
    {
        return $this->members->handle($organization, $owner->getKey(), activeOnly: true);
    }
}
