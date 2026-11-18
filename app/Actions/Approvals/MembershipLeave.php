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
use App\Events\Memberships\MemberLeft;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Models\User;
use App\Notifications\MembershipLeaveDeclinedNotification;
use App\Notifications\MembershipLeaveRequestedNotification;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class MembershipLeave
{
    public function __construct(
        private RelationshipTimeline $timeline,
    ) {
        // .
    }

    /**
     * Ask an owner to approve an admin's leave.
     */
    public function request(Organization $organization, User $actor): ApprovalRequest
    {
        $person = $actor->person;

        if ($person === null || $person->roleIn($organization)?->isAdmin() !== true) {
            throw new InvalidArgumentException('Only an admin needs approval to leave.');
        }

        $request = DB::transaction(function () use ($organization, $person, $actor): ApprovalRequest {
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

            $today = now()->toDateString();

            $owner = $organization->owners()
                ->where(fn ($query) => $query->whereNull('organization_relationships.start_date')
                    ->orWhere('organization_relationships.start_date', '<=', $today))
                ->where(fn ($query) => $query->whereNull('organization_relationships.finish_date')
                    ->orWhere('organization_relationships.finish_date', '>=', $today))
                ->first();

            if (! $owner instanceof Person) {
                throw new InvalidArgumentException('The organization has no owner to approve the request.');
            }

            $request = ApprovalRequest::create([
                'organization_id' => $organization->getKey(),
                'type' => ApprovalRequestType::MembershipLeave,
                'requester_person_id' => $person->getKey(),
                'approver_person_id' => $owner->getKey(),
                'status' => ApprovalRequestStatus::Pending,
                'payload' => ['role' => Role::Admin->value],
                'expires_at' => now()->addDays(7),
            ]);

            event(new ApprovalRequested($request, $actor));

            return $request;
        });

        $request->loadMissing(['organization', 'requester', 'approver']);

        $request->approver->user?->notify(new MembershipLeaveRequestedNotification($request));

        return $request;
    }

    /**
     * Approve a pending leave request and detach the requester.
     *
     * Returns false when the request expired, changed state, or the
     * membership moved underneath it.
     */
    public function accept(ApprovalRequest $request, User $actor): bool
    {
        $person = $actor->person;

        if ($person === null || ! $person->is($request->approver)) {
            throw new InvalidArgumentException('This request does not belong to you.');
        }

        return DB::transaction(function () use ($request, $person, $actor): bool {
            $organization = Organization::query()
                ->whereKey($request->organization_id)
                ->lockForUpdate()
                ->firstOrFail();

            $request = ApprovalRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($request->type !== ApprovalRequestType::MembershipLeave
                || $request->status->isFinished()
                || $request->isExpired()
                || $person->roleIn($organization)?->isOwner() !== true) {
                return false;
            }

            $requester = $request->requester;

            if ($requester->roleIn($organization)?->isAdmin() !== true) {
                return false;
            }

            $relationship = OrganizationRelationship::forOrganization($organization)
                ->whereStakeholder($requester, OrganizationRelationshipType::Member)
                ->first();

            $period = $relationship !== null
                ? $this->timeline->close($relationship, RelationshipPeriodReason::Left, $actor)
                : null;

            $organization->members()->detach($requester->getKey());

            $accepted = $request->update([
                'status' => ApprovalRequestStatus::Accepted,
                'accepted_at' => now(),
            ]);

            event(new ApprovalAccepted($request, $actor));

            event(new MemberLeft($organization, $actor, $requester, 'approval', $request, $period));

            return $accepted;
        });
    }

    /**
     * Decline a pending leave request.
     *
     * Returns false when the request expired or was already handled.
     */
    public function decline(ApprovalRequest $request, User $actor): bool
    {
        $person = $actor->person;

        if ($person === null || ! $person->is($request->approver)) {
            throw new InvalidArgumentException('This request does not belong to you.');
        }

        $request = DB::transaction(function () use ($request, $actor): ?ApprovalRequest {
            $request = ApprovalRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($request->type !== ApprovalRequestType::MembershipLeave
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
            new MembershipLeaveDeclinedNotification($request->organization->name, $request->approver->name),
        );

        return true;
    }
}
