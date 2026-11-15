<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Actions\Audit\RecordAudit;
use App\Enums\ApprovalRequestStatus;
use App\Enums\AuditEvent;
use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CancelApprovalRequest
{
    public function __construct(private RecordAudit $audit)
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

        return DB::transaction(function () use ($request, $person, $actor): bool {
            $request = ApprovalRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($request->status !== ApprovalRequestStatus::Pending
                || ! $person->is($request->requester)) {
                return false;
            }

            $request->update([
                'status' => ApprovalRequestStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

            $this->audit->handle($request->organization, $actor, AuditEvent::ApprovalCancelled, $request, [
                'type' => $request->type->value,
            ]);

            return true;
        });
    }
}
