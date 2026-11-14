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
     *
     * Returns false when the request expired, changed state, or the
     * memberships moved underneath it.
     */
    public function handle(OrganizationOwnershipTransfer $transfer, User $actor): bool
    {
        $person = $actor->person;

        if ($person === null || ! $person->is($transfer->toPerson)) {
            throw new InvalidArgumentException('This transfer does not belong to you.');
        }

        try {
            $transfer = DB::transaction(function () use ($transfer, $person): ?OrganizationOwnershipTransfer {
                // Lock the organization first, matching RequestOwnershipTransfer,
                // so an accept and a new request cannot deadlock.
                $organization = Organization::query()
                    ->whereKey($transfer->organization_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $transfer = OrganizationOwnershipTransfer::query()
                    ->whereKey($transfer->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($transfer->status !== OwnershipTransferStatus::Pending
                    || $transfer->isExpired()
                    || ! $person->is($transfer->toPerson)
                    || $transfer->fromPerson->roleIn($organization)?->isOwner() !== true
                    || $person->roleIn($organization) === null) {
                    return null;
                }

                $demoted = OrganizationRelationship::query()
                    ->forOrganization($organization)
                    ->whereStakeholder($transfer->fromPerson, OrganizationRelationshipType::Member)
                    ->update(['role' => Role::Admin]);

                $promoted = OrganizationRelationship::query()
                    ->forOrganization($organization)
                    ->whereStakeholder($transfer->toPerson, OrganizationRelationshipType::Member)
                    ->update(['role' => Role::Owner]);

                // A membership removed between the checks above would leave the
                // organization without an owner, so abandon the swap instead.
                if ($demoted !== 1 || $promoted !== 1) {
                    throw new InvalidArgumentException('This ownership transfer is no longer valid.');
                }

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
        } catch (InvalidArgumentException) {
            return false;
        }

        if ($transfer === null) {
            return false;
        }

        $transfer->loadMissing(['organization', 'fromPerson', 'toPerson']);

        $transfer->fromPerson->user?->notify(
            new OwnershipTransferredNotification($transfer->organization->name, $transfer->toPerson->name),
        );

        return true;
    }
}
