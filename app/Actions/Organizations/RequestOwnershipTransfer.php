<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Notifications\OwnershipTransferRequestedNotification;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RequestOwnershipTransfer
{
    /**
     * Ask another member to take ownership of the organization.
     */
    public function handle(Organization $organization, User $actor, Person $recipient): ApprovalRequest
    {
        $owner = $actor->person;

        if ($owner === null || ! $owner->roleIn($organization)?->isOwner()) {
            throw new InvalidArgumentException('Only the owner can transfer ownership.');
        }

        if ($recipient->is($owner) || $recipient->user_id === null || $recipient->roleIn($organization) === null) {
            throw new InvalidArgumentException('The recipient must be an active member with a login credential.');
        }

        $request = DB::transaction(function () use ($organization, $owner, $recipient): ApprovalRequest {
            $organization = Organization::query()
                ->whereKey($organization->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $owner->roleIn($organization)?->isOwner()) {
                throw new InvalidArgumentException('Only the owner can transfer ownership.');
            }

            if (ApprovalRequest::query()->pending()->where('organization_id', $organization->getKey())->exists()) {
                throw new InvalidArgumentException('Another approval request is already pending.');
            }

            return ApprovalRequest::create([
                'organization_id' => $organization->getKey(),
                'type' => ApprovalRequestType::OwnershipTransfer,
                'requester_person_id' => $owner->getKey(),
                'approver_person_id' => $recipient->getKey(),
                'status' => ApprovalRequestStatus::Pending,
                'expires_at' => now()->addDays(7),
            ]);
        });

        $request->loadMissing(['organization', 'requester', 'approver']);

        $recipient->user?->notify(new OwnershipTransferRequestedNotification($request));

        return $request;
    }
}
