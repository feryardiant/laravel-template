<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class OrganizationSettingsController extends Controller
{
    /**
     * Show the current organization's read-only settings.
     */
    public function __invoke(Request $request, CurrentOrganization $currentOrganization): View
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $organization = $currentOrganization->get($user);

        abort_if($organization === null, 404);

        $role = $user->roleIn($organization);

        abort_unless($role?->isOwner() || $role?->isAdmin(), 403);

        return view('org-settings', [
            'organization' => $organization->loadMissing('primaryContact'),
        ]);
    }
}
