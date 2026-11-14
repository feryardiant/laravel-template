<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Enums\OwnershipTransferStatus;
use App\Models\OrganizationOwnershipTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CancelOwnershipTransfer
{
    /**
     * Cancel a pending ownership transfer.
     */
    public function handle(OrganizationOwnershipTransfer $transfer, User $actor): bool
    {
        $person = $actor->person;

        if ($person === null || ! $person->is($transfer->fromPerson)) {
            throw new InvalidArgumentException('This transfer does not belong to you.');
        }

        return DB::transaction(function () use ($transfer, $person): bool {
            $transfer = OrganizationOwnershipTransfer::query()
                ->whereKey($transfer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($transfer->status !== OwnershipTransferStatus::Pending
                || ! $person->is($transfer->fromPerson)) {
                return false;
            }

            $transfer->update(['status' => OwnershipTransferStatus::Cancelled]);

            return true;
        });
    }
}
