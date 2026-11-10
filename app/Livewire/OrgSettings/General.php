<?php

declare(strict_types=1);

namespace App\Livewire\OrgSettings;

use App\Concerns\ProfileValidationRules;
use App\Livewire\Concerns\HasCurrentOrganization;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class General extends Component
{
    use HasCurrentOrganization, ProfileValidationRules;

    public string $name = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $organization = $this->currentOrganization();

        abort_if($organization === null, 404);

        $this->authorizeManager();

        $this->name = $organization->name;
    }

    /**
     * Update the current organization's name.
     */
    public function update(): void
    {
        $organization = $this->currentOrganization();

        abort_if($organization === null, 404);

        $this->authorizeManager();

        $validated = $this->validate([
            'name' => $this->nameRules(),
        ]);

        $organization->update(['name' => $validated['name']]);

        Flux::toast(variant: 'success', text: __('Organization updated.'));
    }

    public function render(): View
    {
        /** @disregard P1013 */
        return view('livewire.org-settings.general')->title(__('Organization settings'));
    }
}
