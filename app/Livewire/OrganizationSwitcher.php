<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Lists the user's organizations and switches the active workspace.
 */
class OrganizationSwitcher extends Component
{
    use HasCurrentOrganization;

    public function render(): View
    {
        $user = Auth::user();

        return view('livewire.organization-switcher', [
            'current' => $this->currentOrganization(),
            'organizations' => $user instanceof User ? $user->organizations() : collect(),
        ]);
    }

    /**
     * Refresh the list after an organization is created.
     */
    #[On('organization-created')]
    public function refreshOrganizations(): void
    {
        //
    }
}
