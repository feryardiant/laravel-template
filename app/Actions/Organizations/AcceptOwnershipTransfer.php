<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Enums\OrganizationRelationshipType;
use App\Enums\OwnershipTransferStatus;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\OrganizationOwnershipTransfer;
use App\Models\OrganizationRelationship;
use App\Models\User;
use App\Notifications\OwnershipTransferredNotification;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AcceptOwnershipTransfer
{
    /**
     * Accept a pending ownership transfer and swap the roles.
     */
    public function handle(OrganizationOwnershipTransfer $transfer, User $actor): bool
    {
        $person = $actor->person;

        if ($person === null || ! $person->is($transfer->toPerson)) {
            throw new InvalidArgumentException('This transfer does not belong to you.');
        }

        $transfer = DB::transaction(function () use ($transfer, $person): OrganizationOwnershipTransfer {
            $transfer = OrganizationOwnershipTransfer::query()
                ->whereKey($transfer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $organization = Organization::query()
                ->whereKey($transfer->organization_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($transfer->status !== OwnershipTransferStatus::Pending
                || $transfer->isExpired()
                || ! $person->is($transfer->toPerson)
                || $transfer->fromPerson->roleIn($organization)?->isOwner() !== true
                || $person->roleIn($organization) === null) {
                throw new InvalidArgumentException('This ownership transfer is no longer valid.');
            }

            OrganizationRelationship::query()
                ->forOrganization($organization)
                ->whereStakeholder($transfer->fromPerson, OrganizationRelationshipType::Member)
                ->update(['role' => Role::Admin]);

            OrganizationRelationship::query()
                ->forOrganization($organization)
                ->whereStakeholder($transfer->toPerson, OrganizationRelationshipType::Member)
                ->update(['role' => Role::Owner]);

            $transfer->update([
                'status' => OwnershipTransferStatus::Accepted,
                'accepted_at' => now(),
            ]);

            OrganizationOwnershipTransfer::query()
                ->where('organization_id', $organization->getKey())
                ->whereKeyNot($transfer->getKey())
                ->where('status', OwnershipTransferStatus::Pending)
                ->update(['status' => OwnershipTransferStatus::Cancelled]);

            return $transfer;
        });

        $transfer->loadMissing(['organization', 'fromPerson', 'toPerson']);

        $transfer->fromPerson->user?->notify(
            new OwnershipTransferredNotification($transfer->organization->name, $transfer->toPerson->name),
        );

        return true;
    }
}
