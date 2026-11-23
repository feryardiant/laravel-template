<?php

declare(strict_types=1);

namespace App\Actions\Approvals;

use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\User;

final class FindApprovalRequest
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
            ->where('uuid', $uuid)
            ->where('approver_person_id', $personId)
            ->where('status', ApprovalRequestStatus::Pending)
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Resolve a pending approval for the organization and approver.
     */
    public function openForApproverByOrganization(User $user, int $organizationId): ?ApprovalRequest
    {
        $personId = $user->person?->getKey();

        if ($personId === null) {
            return null;
        }

        return ApprovalRequest::query()
            ->where('organization_id', $organizationId)
            ->where('approver_person_id', $personId)
            ->where('status', ApprovalRequestStatus::Pending)
            ->where('expires_at', '>', now())
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
            ->whereKey($approvalId)
            ->where('approver_person_id', $personId)
            ->first();
    }

    /**
     * Resolve the organization's current pending approval.
     */
    public function pendingForOrganization(Organization $organization): ?ApprovalRequest
    {
        return ApprovalRequest::query()
            ->pending()
            ->where('organization_id', $organization->getKey())
            ->where('expires_at', '>', now())
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
            ->whereKey($approvalId)
            ->where('type', $type)
            ->where('requester_person_id', $personId)
            ->first();
    }
}
