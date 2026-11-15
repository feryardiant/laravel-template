<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Actions\Audit\RecordAudit;
use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Enums\AuditEvent;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Notifications\MembershipLeaveDeclinedNotification;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DeclineMembershipLeave
{
    public function __construct(private RecordAudit $audit)
    {
        // .
    }

    /**
     * Decline a pending leave request.
     *
     * Returns false when the request expired or was already handled.
     */
    public function handle(ApprovalRequest $request, User $actor): bool
    {
        $person = $actor->person;

        if ($person === null || ! $person->is($request->approver)) {
            throw new InvalidArgumentException('This request does not belong to you.');
        }

        $request = DB::transaction(function () use ($request, $person, $actor): ?ApprovalRequest {
            $request = ApprovalRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($request->type !== ApprovalRequestType::MembershipLeave
                || $request->status !== ApprovalRequestStatus::Pending
                || $request->isExpired()
                || ! $person->is($request->approver)) {
                return null;
            }

            $request->update([
                'status' => ApprovalRequestStatus::Declined,
                'declined_at' => now(),
            ]);

            $this->audit->handle($request->organization, $actor, AuditEvent::ApprovalDeclined, $request, [
                'type' => $request->type->value,
            ]);

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
