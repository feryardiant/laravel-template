<?php

declare(strict_types=1);

namespace App\Livewire\OrgSettings;

use App\Livewire\Concerns\HasCurrentOrganization;
use App\Support\Feature\ContactDeletion;
use App\Support\Feature\ContactGender;
use App\Support\Feature\MemberDeletion;
use App\Support\Feature\Registration;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Features extends Component
{
    use HasCurrentOrganization;

    public bool $registration = false;

    public bool $contactGender = false;

    public bool $memberDeletion = true;

    public bool $contactDeletion = true;

    #[Locked]
    public bool $isRootTenant = false;

    #[Locked]
    public ?int $organizationId = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $organization = $this->currentOrganization();

        abort_if($organization === null, 404);

        $this->authorizeManager();

        $this->organizationId = $organization->getKey();
        $this->isRootTenant = $organization->isRootTenant();
        $this->registration = $this->isRootTenant && Registration::enabled();
        $this->contactGender = ContactGender::enabled($organization);
        $this->memberDeletion = MemberDeletion::enabled($organization);
        $this->contactDeletion = ContactDeletion::enabled($organization);
    }

    /**
     * Toggle public registration for the root tenant.
     */
    public function updatedRegistration(bool $enabled): void
    {
        $organization = $this->currentOrganization();

        abort_if($organization === null || ! $organization->isRootTenant(), 404);
        abort_if($organization->getKey() !== $this->organizationId, 409);

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
        abort_if($organization->getKey() !== $this->organizationId, 409);

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

    /**
     * Toggle member removal for the current tenant.
     */
    public function updatedMemberDeletion(bool $enabled): void
    {
        $organization = $this->currentOrganization();

        abort_if($organization === null, 404);
        abort_if($organization->getKey() !== $this->organizationId, 409);

        $this->authorizeManager();

        if ($enabled) {
            MemberDeletion::activate($organization);
        } else {
            MemberDeletion::deactivate($organization);
        }

        Flux::toast(
            variant: 'success',
            text: $enabled ? __('Member removal enabled.') : __('Member removal disabled.'),
        );
    }

    /**
     * Toggle contact deletion for the current tenant.
     */
    public function updatedContactDeletion(bool $enabled): void
    {
        $organization = $this->currentOrganization();

        abort_if($organization === null, 404);
        abort_if($organization->getKey() !== $this->organizationId, 409);

        $this->authorizeManager();

        if ($enabled) {
            ContactDeletion::activate($organization);
        } else {
            ContactDeletion::deactivate($organization);
        }

        Flux::toast(
            variant: 'success',
            text: $enabled ? __('Contact deletion enabled.') : __('Contact deletion disabled.'),
        );
    }
}
