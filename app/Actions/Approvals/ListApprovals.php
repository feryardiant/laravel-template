<?php

declare(strict_types=1);

namespace App\Actions\Approvals;

use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\Person;
use Illuminate\Database\Eloquent\Collection;

final class ListApprovals
{
    /**
     * List the organization's approvals, newest first.
     *
     * @return Collection<int, ApprovalRequest>
     */
    public function forOrganization(Organization $organization): Collection
    {
        return ApprovalRequest::query()
            ->forOrganization($organization)
            ->with(['requester', 'approver'])
            ->latest()
            ->get();
    }

    /**
     * List the pending, unexpired approvals involving the person.
     *
     * @return Collection<int, ApprovalRequest>
     */
    public function pendingForPerson(Person $person): Collection
    {
        return ApprovalRequest::query()
            ->onlyPendings()
            ->involvingPerson($person)
            ->with('requester')
            ->get();
    }
}
