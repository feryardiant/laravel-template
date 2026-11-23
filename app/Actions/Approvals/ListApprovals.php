<?php

declare(strict_types=1);

namespace App\Actions\Approvals;

use App\Models\ApprovalRequest;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Collection;

final class ListApprovals
{
    /**
     * List the organization's approvals, newest first.
     *
     * @return Collection<int, ApprovalRequest>
     */
    public function handle(Organization $organization): Collection
    {
        return ApprovalRequest::query()
            ->where('organization_id', $organization->getKey())
            ->with(['requester', 'approver'])
            ->latest()
            ->get();
    }
}
