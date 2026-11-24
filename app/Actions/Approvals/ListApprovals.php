<?php

declare(strict_types=1);

namespace App\Actions\Approvals;

use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\Person;
use Illuminate\Contracts\Database\Eloquent\Builder;
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
            ->where('organization_id', $organization->getKey())
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
        return ApprovalRequest::onlyPendings()
            ->where('expires_at', '>', now())
            ->where(fn (Builder $query): Builder => $query
                ->where('requester_person_id', $person->getKey())
                ->orWhere('approver_person_id', $person->getKey()))
            ->with('requester')
            ->get();
    }
}
