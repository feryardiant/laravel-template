<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Enums\Role;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Notifications\MembershipLeaveRequestedNotification;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RequestMembershipLeave
{
    /**
     * Ask an owner to approve an admin's leave.
     */
    public function handle(Organization $organization, User $actor): ApprovalRequest
    {
        $person = $actor->person;

        if ($person === null || $person->roleIn($organization)?->isAdmin() !== true) {
            throw new InvalidArgumentException('Only an admin needs approval to leave.');
        }

        $request = DB::transaction(function () use ($organization, $person): ApprovalRequest {
            $organization = Organization::query()
                ->whereKey($organization->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($person->roleIn($organization)?->isAdmin() !== true) {
                throw new InvalidArgumentException('Only an admin needs approval to leave.');
            }

            if (ApprovalRequest::query()->pending()->where('organization_id', $organization->getKey())->exists()) {
                throw new InvalidArgumentException('Another approval request is already pending.');
            }

            $owner = $organization->members()
                ->wherePivot('role', Role::Owner->value)
                ->first();

            if (! $owner instanceof Person) {
                throw new InvalidArgumentException('The organization has no owner to approve the request.');
            }

            return ApprovalRequest::create([
                'organization_id' => $organization->getKey(),
                'type' => ApprovalRequestType::MembershipLeave,
                'requester_person_id' => $person->getKey(),
                'approver_person_id' => $owner->getKey(),
                'status' => ApprovalRequestStatus::Pending,
                'payload' => ['role' => Role::Admin->value],
                'expires_at' => now()->addDays(7),
            ]);
        });

        $request->loadMissing(['organization', 'requester', 'approver']);

        $request->approver->user?->notify(new MembershipLeaveRequestedNotification($request));

        return $request;
    }
}
