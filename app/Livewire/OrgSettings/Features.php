<?php

declare(strict_types=1);

namespace App\Livewire\OrgSettings;

use App\Livewire\Concerns\HasCurrentOrganization;
use App\Support\Feature\ContactGender;
use App\Support\Feature\Registration;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Features extends Component
{
    use HasCurrentOrganization;

    public bool $registration = false;

    public bool $contactGender = false;

    #[Locked]
    public bool $isRootTenant = false;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $organization = $this->currentOrganization();

        abort_if($organization === null, 404);

        $this->authorizeManager();

        $this->isRootTenant = $organization->isRootTenant();
        $this->registration = $this->isRootTenant && Registration::enabled();
        $this->contactGender = ContactGender::enabled($organization);
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

    /**
     * Toggle the contact gender field for the current tenant.
     */
    public function updatedContactGender(bool $enabled): void
    {
        $organization = $this->currentOrganization();

        abort_if($organization === null, 404);

        $this->authorizeManager();

        if ($enabled) {
            ContactGender::activate($organization);
        } else {
            ContactGender::deactivate($organization);
        }

        Flux::toast(
            variant: 'success',
            text: $enabled ? __('Contact gender enabled.') : __('Contact gender disabled.'),
        );
    }
}
