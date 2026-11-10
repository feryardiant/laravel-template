<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SwitchOrganizationController extends Controller
{
    /**
     * Switch the authenticated user's current organization.
     */
    public function __invoke(
        Request $request,
        Organization $organization,
        CurrentOrganization $currentOrganization,
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user instanceof User, 403);
        abort_unless($currentOrganization->switch($user, $organization), 403);

        return back();
    }
}
