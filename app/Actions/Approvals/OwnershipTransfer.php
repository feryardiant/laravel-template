<?php

declare(strict_types=1);

namespace App\Actions\Approvals;

use App\Actions\Relationships\RelationshipTimeline;
use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Enums\OrganizationRelationshipType;
use App\Enums\RelationshipPeriodReason;
use App\Enums\Role;
use App\Events\Approvals\ApprovalAccepted;
use App\Events\Approvals\ApprovalDeclined;
use App\Events\Approvals\ApprovalRequested;
use App\Events\Memberships\MemberRoleChanged;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Models\User;
use App\Notifications\OwnershipTransferDeclinedNotification;
use App\Notifications\OwnershipTransferredNotification;
use App\Notifications\OwnershipTransferRequestedNotification;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class OwnershipTransfer
{
    public function __construct(
        private RelationshipTimeline $timeline,
    ) {
        // .
    }

    /**
     * Ask another member to take ownership of the organization.
     */
    public function request(Organization $organization, User $actor, Person $recipient): ApprovalRequest
    {
        $owner = $actor->person;

        if ($owner === null || ! $owner->roleIn($organization)?->isOwner()) {
            throw new InvalidArgumentException('Only the owner can transfer ownership.');
        }

        if ($recipient->is($owner) || $recipient->user_id === null || $recipient->roleIn($organization) === null) {
            throw new InvalidArgumentException('The recipient must be an active member with a login credential.');
        }

        $request = DB::transaction(function () use ($organization, $owner, $recipient, $actor): ApprovalRequest {
            $organization = Organization::query()
                ->whereKey($organization->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            ApprovalRequest::query()
                ->where('organization_id', $organization->getKey())
                ->where('status', ApprovalRequestStatus::Pending)
                ->where('expires_at', '<=', now())
                ->update([
                    'status' => ApprovalRequestStatus::Canceled,
                    'canceled_at' => now(),
                ]);

            if (ApprovalRequest::query()
                ->pending()
                ->where('organization_id', $organization->getKey())
                ->where('expires_at', '>', now())
                ->exists()) {
                throw new InvalidArgumentException('Another approval request is already pending.');
            }

            $request = ApprovalRequest::create([
                'organization_id' => $organization->getKey(),
                'type' => ApprovalRequestType::OwnershipTransfer,
                'requester_person_id' => $owner->getKey(),
                'approver_person_id' => $recipient->getKey(),
                'status' => ApprovalRequestStatus::Pending,
                'expires_at' => now()->addDays(7),
            ]);

            event(new ApprovalRequested($request, $actor));

            return $request;
        });

        $request->loadMissing(['organization', 'requester', 'approver']);

        $recipient->user?->notify(new OwnershipTransferRequestedNotification($request));

        return $request;
    }

    /**
     * Accept a pending ownership transfer and swap the roles.
     *
     * Returns false when the request expired, changed state, or the
     * memberships moved underneath it.
     */
    public function accept(ApprovalRequest $request, User $actor): bool
    {
        $person = $actor->person;

        if ($person === null || ! $person->is($request->approver)) {
            throw new InvalidArgumentException('This transfer does not belong to you.');
        }

        try {
            $request = DB::transaction(function () use ($request, $person, $actor): ?ApprovalRequest {
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
                    || $request->status->isFinished()
                    || $request->isExpired()
                    || $request->requester->roleIn($organization)?->isOwner() !== true
                    || $person->roleIn($organization) === null) {
                    return null;
                }

                $requesterRelationship = OrganizationRelationship::query()
                    ->forOrganization($organization)
                    ->whereStakeholder($request->requester, OrganizationRelationshipType::Member)
                    ->lockForUpdate()
                    ->first();

                $approverRelationship = OrganizationRelationship::query()
                    ->forOrganization($organization)
                    ->whereStakeholder($request->approver, OrganizationRelationshipType::Member)
                    ->lockForUpdate()
                    ->first();

                // A membership removed between the checks above would leave the
                // organization without an owner, so abandon the swap instead.
                if ($requesterRelationship === null || $approverRelationship === null) {
                    throw new InvalidArgumentException('This ownership transfer is no longer valid.');
                }

                $requesterRole = $requesterRelationship->role;
                $approverRole = $approverRelationship->role;

                $this->timeline->close($requesterRelationship, RelationshipPeriodReason::RoleChanged, $actor);
                $this->timeline->close($approverRelationship, RelationshipPeriodReason::RoleChanged, $actor);

                $requesterRelationship->update(['role' => Role::Admin]);
                $approverRelationship->update(['role' => Role::Owner]);

                $requesterPeriod = $this->timeline->open($requesterRelationship->refresh(), today());
                $approverPeriod = $this->timeline->open($approverRelationship->refresh(), today());

                if ($requesterRole instanceof Role) {
                    event(new MemberRoleChanged($organization, $requesterRole, Role::Admin, $request->requester, $actor, $requesterPeriod));
                }

                if ($approverRole instanceof Role) {
                    event(new MemberRoleChanged($organization, $approverRole, Role::Owner, $request->approver, $actor, $approverPeriod));
                }

                $request->update([
                    'status' => ApprovalRequestStatus::Accepted,
                    'accepted_at' => now(),
                ]);

                event(new ApprovalAccepted($request, $actor));

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

    /**
     * Decline a pending ownership transfer.
     *
     * Returns false when the request expired or was already handled.
     */
    public function decline(ApprovalRequest $request, User $actor): bool
    {
        $person = $actor->person;

        if ($person === null || ! $person->is($request->approver)) {
            throw new InvalidArgumentException('This transfer does not belong to you.');
        }

        $request = DB::transaction(function () use ($request, $actor): ?ApprovalRequest {
            $request = ApprovalRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($request->type !== ApprovalRequestType::OwnershipTransfer
                || $request->status->isFinished()
                || $request->isExpired()) {
                return null;
            }

            $request->update([
                'status' => ApprovalRequestStatus::Declined,
                'declined_at' => now(),
            ]);

            event(new ApprovalDeclined($request, $actor));

            return $request;
        });

        if ($request === null) {
            return false;
        }

        $request->loadMissing(['organization', 'requester', 'approver']);

        $request->requester->user?->notify(
            new OwnershipTransferDeclinedNotification($request->organization->name, $request->approver->name),
        );

        return true;
    }
}
