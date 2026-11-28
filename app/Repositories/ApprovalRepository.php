<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\ApprovalRequestType;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Reads approval requests for the organization, approver, and requester.
 */
final class ApprovalRepository
{
    /**
     * Paginate the organization's approvals, newest first.
     *
     * @return LengthAwarePaginator<int, ApprovalRequest>
     */
    public function getForOrganization(Organization $organization, int $perPage = 20): LengthAwarePaginator
    {
        return ApprovalRequest::query()
            ->forOrganization($organization)
            ->with(['requester', 'approver'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * List the pending, unexpired approvals involving the person.
     *
     * @return Collection<int, ApprovalRequest>
     */
    public function getPendingForPerson(Person $person): Collection
    {
        return ApprovalRequest::query()
            ->onlyPendings()
            ->involvingPerson($person)
            ->with('requester')
            ->get();
    }

    /**
     * Resolve the organization's current pending approval.
     */
    public function findPendingForOrganization(Organization $organization): ?ApprovalRequest
    {
        return ApprovalRequest::query()
            ->onlyPendings()
            ->forOrganization($organization)
            ->with(['requester', 'approver'])
            ->first();
    }

    /**
     * Resolve a pending approval for the organization and approver.
     */
    public function findOpenForApproverByOrganization(User $user, Organization|int $organization): ?ApprovalRequest
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
     * Resolve a pending approval opened from a signed email link.
     */
    public function findForApproverByUuid(User $user, string $uuid): ?ApprovalRequest
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
     * Resolve an approval for the approver, without changing its lifecycle.
     */
    public function findForApprover(User $user, int $approvalId): ?ApprovalRequest
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
     * Resolve an approval for the requester.
     */
    public function findForRequester(User $user, int $approvalId, ApprovalRequestType $type): ?ApprovalRequest
    {
        $personId = $user->person?->getKey();

        if ($personId === null) {
            return null;
        }

        return ApprovalRequest::query()
            ->forRequester($personId)
            ->whereKey($approvalId)
            ->ofType($type)
            ->first();
    }
}
