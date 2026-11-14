<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Notifications\OwnershipTransferDeclinedNotification;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DeclineOwnershipTransfer
{
    /**
     * Decline a pending ownership transfer.
     *
     * Returns false when the request expired or was already handled.
     */
    public function handle(ApprovalRequest $request, User $actor): bool
    {
        $person = $actor->person;

        if ($person === null || ! $person->is($request->approver)) {
            throw new InvalidArgumentException('This transfer does not belong to you.');
        }

        $request = DB::transaction(function () use ($request, $person): ?ApprovalRequest {
            $request = ApprovalRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($request->type !== ApprovalRequestType::OwnershipTransfer
                || $request->status !== ApprovalRequestStatus::Pending
                || $request->isExpired()
                || ! $person->is($request->approver)) {
                return null;
            }

            $request->update([
                'status' => ApprovalRequestStatus::Declined,
                'declined_at' => now(),
            ]);

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
