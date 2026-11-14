<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Actions\Relationships\RelationshipTimeline;
use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Enums\OrganizationRelationshipType;
use App\Enums\RelationshipPeriodReason;
use App\Events\MemberRemoved;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final class AcceptMembershipLeave
{
    public function __construct(private RelationshipTimeline $timeline)
    {
        // .
    }

    /**
     * Approve a pending leave request and detach the requester.
     *
     * Returns false when the request expired, changed state, or the
     * membership moved underneath it.
     */
    public function handle(ApprovalRequest $request, User $actor): bool
    {
        $person = $actor->person;

        if ($person === null || ! $person->is($request->approver)) {
            throw new InvalidArgumentException('This request does not belong to you.');
        }

        $requester = DB::transaction(function () use ($request, $person): ?Person {
            $organization = Organization::query()
                ->whereKey($request->organization_id)
                ->lockForUpdate()
                ->firstOrFail();

            $request = ApprovalRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($request->type !== ApprovalRequestType::MembershipLeave
                || $request->status !== ApprovalRequestStatus::Pending
                || $request->isExpired()
                || ! $person->is($request->approver)
                || $person->roleIn($organization)?->isOwner() !== true) {
                return null;
            }

            $requester = $request->requester;

            if ($requester->roleIn($organization)?->isAdmin() !== true) {
                return null;
            }

            $relationship = OrganizationRelationship::query()
                ->forOrganization($organization)
                ->whereStakeholder($requester)
                ->where('type', OrganizationRelationshipType::Member)
                ->first();

            if ($relationship !== null) {
                $this->timeline->close($relationship, RelationshipPeriodReason::Left);
            }

            $organization->members()->detach($requester->getKey());

            $request->update([
                'status' => ApprovalRequestStatus::Accepted,
                'accepted_at' => now(),
            ]);

            return $requester;
        });

        if ($requester === null) {
            return false;
        }

        try {
            event(new MemberRemoved(
                $request->organization,
                $requester,
                null,
                ! $requester->organizations()->exists(),
            ));
        } catch (Throwable $exception) {
            report($exception);
        }

        return true;
    }
}
