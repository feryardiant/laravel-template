<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class OrganizationSwitcher extends Component
{
    use HasCurrentOrganization;

    public function render(): View
    {
        $user = Auth::user();

        return view('livewire.organization-switcher', [
            'current' => $this->currentOrganization(),
            'organizations' => $user instanceof User ? $user->organizations() : collect(),
            'role' => $this->currentRole(),
        ]);
    }
}
