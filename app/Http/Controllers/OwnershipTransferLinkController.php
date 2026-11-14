<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OwnershipTransferStatus;
use App\Models\OrganizationOwnershipTransfer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OwnershipTransferLinkController
{
    /**
     * Send a signed email link to the transfer review modal.
     */
    public function __invoke(Request $request, OrganizationOwnershipTransfer $transfer): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user?->person?->is($transfer->toPerson), 403);
        abort_unless(
            $transfer->status === OwnershipTransferStatus::Pending && ! $transfer->isExpired(),
            404,
        );

        return redirect()->route('organizations.index', ['transfer' => $transfer->uuid]);
    }
}
