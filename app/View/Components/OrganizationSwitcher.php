<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Enums\Role;
use App\Support\CurrentOrganization;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

class OrganizationSwitcher extends Component
{
    public function render(): View
    {
        $user = Auth::user();
        $current = app(CurrentOrganization::class)->get($user);
        $role = $current !== null && $user !== null ? $user->roleIn($current) : null;

        return view('components.organization-switcher', [
            'current' => $current,
            'organizations' => $user?->organizations() ?? collect(),
            'role' => $role instanceof Role ? $role : null,
        ]);
    }
}
