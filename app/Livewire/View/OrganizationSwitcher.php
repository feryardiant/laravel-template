<?php

declare(strict_types=1);

namespace App\Livewire\View;

use App\Livewire\Concerns\HasCurrentOrganization;
use App\Support\CurrentOrganization;
use Illuminate\Contracts\View\View;
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
        return view('livewire.view.organization-switcher', [
            'current' => $this->currentOrganization(),
            'organizations' => app(CurrentOrganization::class)->organizations(),
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
