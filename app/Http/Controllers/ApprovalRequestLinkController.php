<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Redirects a signed email link to the approval review modal.
 */
class ApprovalRequestLinkController
{
    /**
     * Send a signed email link to the approval review modal.
     */
    public function __invoke(Request $request, ApprovalRequest $approval): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->profile->is($approval->approver), 403);
        abort_unless($approval->status->isPending() && ! $approval->isExpired(), 404);

        return redirect()->route('organizations.index', ['approval' => $approval->uuid]);
    }
}
