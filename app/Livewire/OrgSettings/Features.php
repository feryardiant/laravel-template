<?php

declare(strict_types=1);

namespace App\Livewire\OrgSettings;

use App\Livewire\Concerns\HasCurrentOrganization;
use App\Support\Registration;
use Flux\Flux;
use Livewire\Component;

class Features extends Component
{
    use HasCurrentOrganization;

    public bool $registration = false;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $organization = $this->currentOrganization();

        abort_if($organization === null || ! $organization->isRootTenant(), 404);

        $this->authorizeManager();

        $this->registration = Registration::enabled();
    }

    /**
     * Toggle public registration for the root tenant.
     */
    public function updatedRegistration(bool $enabled): void
    {
        $organization = $this->currentOrganization();

        abort_if($organization === null || ! $organization->isRootTenant(), 404);

        $this->authorizeManager();

        if ($enabled) {
            Registration::activate($organization);
        } else {
            Registration::deactivate($organization);
        }

        Flux::toast(
            variant: 'success',
            text: $enabled ? __('Registration enabled.') : __('Registration disabled.'),
        );
    }
}
