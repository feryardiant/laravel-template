<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Enums\OwnershipTransferStatus;
use App\Models\Organization;
use App\Models\OrganizationOwnershipTransfer;
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
    public function handle(Organization $organization, User $actor, Person $recipient): OrganizationOwnershipTransfer
    {
        $owner = $actor->person;

        if ($owner === null || ! $owner->roleIn($organization)?->isOwner()) {
            throw new InvalidArgumentException('Only the owner can transfer ownership.');
        }

        if ($recipient->is($owner) || $recipient->user_id === null || $recipient->roleIn($organization) === null) {
            throw new InvalidArgumentException('The recipient must be an active member with a login credential.');
        }

        $transfer = DB::transaction(function () use ($organization, $owner, $recipient): OrganizationOwnershipTransfer {
            $organization = Organization::query()
                ->whereKey($organization->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $owner->roleIn($organization)?->isOwner()) {
                throw new InvalidArgumentException('Only the owner can transfer ownership.');
            }

            OrganizationOwnershipTransfer::query()
                ->where('organization_id', $organization->getKey())
                ->where('status', OwnershipTransferStatus::Pending)
                ->update(['status' => OwnershipTransferStatus::Cancelled]);

            return OrganizationOwnershipTransfer::create([
                'organization_id' => $organization->getKey(),
                'from_person_id' => $owner->getKey(),
                'to_person_id' => $recipient->getKey(),
                'status' => OwnershipTransferStatus::Pending,
                'expires_at' => now()->addDays(7),
            ]);
        });

        $transfer->loadMissing(['organization', 'fromPerson', 'toPerson']);

        $recipient->user?->notify(new OwnershipTransferRequestedNotification($transfer));

        return $transfer;
    }
}
