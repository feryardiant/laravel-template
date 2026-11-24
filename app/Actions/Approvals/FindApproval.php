<?php

declare(strict_types=1);

namespace App\Actions\Approvals;

use App\Enums\ApprovalRequestType;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\User;

final class FindApproval
{
    /**
     * Resolve a pending approval opened from a signed email link.
     */
    public function openForApproverByUuid(User $user, string $uuid): ?ApprovalRequest
    {
        $personId = $user->person?->getKey();

        if ($personId === null || $uuid === '') {
            return null;
        }

        return ApprovalRequest::query()
            ->onlyPendings()
            ->forApprover($personId)
            ->where('uuid', $uuid)
            ->first();
    }

    /**
     * Resolve a pending approval for the organization and approver.
     */
    public function openForApproverByOrganization(User $user, Organization|int $organization): ?ApprovalRequest
    {
        $personId = $user->person?->getKey();

        if ($personId === null) {
            return null;
        }

        return ApprovalRequest::query()
            ->onlyPendings()
            ->forOrganization($organization)
            ->forApprover($personId)
            ->first();
    }

    /**
     * Resolve an approval for the approver, without changing its lifecycle.
     */
    public function forApprover(User $user, int $approvalId): ?ApprovalRequest
    {
        $personId = $user->person?->getKey();

        if ($personId === null) {
            return null;
        }

        return ApprovalRequest::query()
            ->forApprover($personId)
            ->whereKey($approvalId)
            ->first();
    }

    /**
     * Resolve the organization's current pending approval.
     */
    public function pendingForOrganization(Organization $organization): ?ApprovalRequest
    {
        return ApprovalRequest::query()
            ->onlyPendings()
            ->forOrganization($organization)
            ->with(['requester', 'approver'])
            ->first();
    }

    /**
     * Resolve an approval for the requester.
     */
    public function forRequester(User $user, int $approvalId, ApprovalRequestType $type): ?ApprovalRequest
    {
        $personId = $user->person?->getKey();

        if ($personId === null) {
            return null;
        }

        return ApprovalRequest::query()
            ->forRequester($personId)
            ->whereKey($approvalId)
            ->where('type', $type)
            ->first();
    }
}
