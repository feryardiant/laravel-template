<?php

declare(strict_types=1);

namespace App\Actions\Approvals;

use App\Enums\ApprovalRequestStatus;
use App\Events\Approvals\ApprovalCancelled;
use App\Models\ApprovalRequest;
use App\Models\User;
use InvalidArgumentException;

final class CancelApprovalRequest
{
    public function __construct()
    {
        // .
    }

    /**
     * Cancel a pending approval request as its requester.
     */
    public function handle(ApprovalRequest $request, User $actor): bool
    {
        $person = $actor->person;

        if ($person === null || ! $person->is($request->requester)) {
            throw new InvalidArgumentException('This request does not belong to you.');
        }

        if ($request->status->isFinished()) {
            return false;
        }

        $canceled = ApprovalRequest::query()
            ->whereKey($request->getKey())
            ->where('status', ApprovalRequestStatus::Pending)
            ->update([
                'status' => ApprovalRequestStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

        if ($canceled === 0) {
            return false;
        }

        $request->refresh();

        event(new ApprovalCancelled($request, $actor));

        return true;
    }
}
