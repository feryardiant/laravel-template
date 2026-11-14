<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Events\MemberRemoved;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final class AcceptMembershipLeave
{
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
