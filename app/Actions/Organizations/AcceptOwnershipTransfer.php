<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\User;
use App\Notifications\OwnershipTransferredNotification;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AcceptOwnershipTransfer
{
    /**
     * Accept a pending ownership transfer and swap the roles.
     *
     * Returns false when the request expired, changed state, or the
     * memberships moved underneath it.
     */
    public function handle(ApprovalRequest $request, User $actor): bool
    {
        $person = $actor->person;

        if ($person === null || ! $person->is($request->approver)) {
            throw new InvalidArgumentException('This transfer does not belong to you.');
        }

        try {
            $request = DB::transaction(function () use ($request, $person): ?ApprovalRequest {
                // Lock the organization first, matching RequestOwnershipTransfer,
                // so an accept and a new request cannot deadlock.
                $organization = Organization::query()
                    ->whereKey($request->organization_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $request = ApprovalRequest::query()
                    ->whereKey($request->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($request->type !== ApprovalRequestType::OwnershipTransfer
                    || $request->status !== ApprovalRequestStatus::Pending
                    || $request->isExpired()
                    || ! $person->is($request->approver)
                    || $request->requester->roleIn($organization)?->isOwner() !== true
                    || $person->roleIn($organization) === null) {
                    return null;
                }

                $demoted = OrganizationRelationship::query()
                    ->forOrganization($organization)
                    ->whereStakeholder($request->requester, OrganizationRelationshipType::Member)
                    ->update(['role' => Role::Admin]);

                $promoted = OrganizationRelationship::query()
                    ->forOrganization($organization)
                    ->whereStakeholder($request->approver, OrganizationRelationshipType::Member)
                    ->update(['role' => Role::Owner]);

                // A membership removed between the checks above would leave the
                // organization without an owner, so abandon the swap instead.
                if ($demoted !== 1 || $promoted !== 1) {
                    throw new InvalidArgumentException('This ownership transfer is no longer valid.');
                }

                $request->update([
                    'status' => ApprovalRequestStatus::Accepted,
                    'accepted_at' => now(),
                ]);

                return $request;
            });
        } catch (InvalidArgumentException) {
            return false;
        }

        if ($request === null) {
            return false;
        }

        $request->loadMissing(['organization', 'requester', 'approver']);

        $request->requester->user?->notify(
            new OwnershipTransferredNotification($request->organization->name, $request->approver->name),
        );

        return true;
    }
}
