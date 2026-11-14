<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Enums\OwnershipTransferStatus;
use App\Models\OrganizationOwnershipTransfer;
use App\Models\User;
use App\Notifications\OwnershipTransferDeclinedNotification;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DeclineOwnershipTransfer
{
    /**
     * Decline a pending ownership transfer.
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

            if ($transfer->status !== OwnershipTransferStatus::Pending
                || $transfer->isExpired()
                || ! $person->is($transfer->toPerson)) {
                throw new InvalidArgumentException('This ownership transfer is no longer valid.');
            }

            $transfer->update([
                'status' => OwnershipTransferStatus::Declined,
                'declined_at' => now(),
            ]);

            return $transfer;
        });

        $transfer->loadMissing(['organization', 'fromPerson', 'toPerson']);

        $transfer->fromPerson->user?->notify(
            new OwnershipTransferDeclinedNotification($transfer->organization->name, $transfer->toPerson->name),
        );

        return true;
    }
}
