<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ApprovalRequestStatus;
use App\Models\ApprovalRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApprovalRequestLinkController
{
    /**
     * Send a signed email link to the approval review modal.
     */
    public function __invoke(Request $request, ApprovalRequest $approval): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user?->person?->is($approval->approver), 403);
        abort_unless(
            $approval->status === ApprovalRequestStatus::Pending && ! $approval->isExpired(),
            404,
        );

        return redirect()->route('organizations.index', ['approval' => $approval->uuid]);
    }
}
