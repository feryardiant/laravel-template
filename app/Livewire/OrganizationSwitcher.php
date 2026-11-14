<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class OrganizationSwitcher extends Component
{
    use HasCurrentOrganization;

    #[On('organization-created')]
    public function refreshOrganizations(): void
    {
        //
    }

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
