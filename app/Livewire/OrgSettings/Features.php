<?php

declare(strict_types=1);

namespace App\Livewire\OrgSettings;

use App\Events\FeatureToggled;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\Organization;
use App\Models\User;
use App\Support\Feature\ContactDeletion;
use App\Support\Feature\ContactGender;
use App\Support\Feature\MemberDeletion;
use App\Support\Feature\ProfileSelfEdit;
use App\Support\Feature\Registration;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Features extends Component
{
    use HasCurrentOrganization;

    public bool $registration = false;

    public bool $contactGender = false;

    public bool $memberDeletion = true;

    public bool $contactDeletion = true;

    public bool $profileSelfEdit = false;

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
        $this->profileSelfEdit = ProfileSelfEdit::enabled($organization);
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

        if ($this->featureEnabled($organization, Registration::FEATURE) === $enabled) {
            return;
        }

        DB::transaction(function () use ($organization, $enabled): void {
            if ($enabled) {
                Registration::activate($organization);
            } else {
                Registration::deactivate($organization);
            }

            $this->recordToggle($organization, Registration::FEATURE, $enabled);
        });

        Flux::toast(
            variant: 'success',
            text: $enabled ? __('Registration enabled.') : __('Registration disabled.'),
        );
    }

    /**
     * Toggle self-editing for org-owned profiles.
     */
    public function updatedProfileSelfEdit(bool $enabled): void
    {
        $organization = $this->currentOrganization();

        abort_if($organization === null, 404);
        abort_if($organization->getKey() !== $this->organizationId, 409);

        $this->authorizeManager();

        if ($this->featureEnabled($organization, ProfileSelfEdit::FEATURE) === $enabled) {
            return;
        }

        DB::transaction(function () use ($organization, $enabled): void {
            if ($enabled) {
                ProfileSelfEdit::activate($organization);
            } else {
                ProfileSelfEdit::deactivate($organization);
            }

            $this->recordToggle($organization, ProfileSelfEdit::FEATURE, $enabled);
        });

        Flux::toast(
            variant: 'success',
            text: $enabled ? __('Profile self-edit enabled.') : __('Profile self-edit disabled.'),
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

        if ($this->featureEnabled($organization, ContactGender::FEATURE) === $enabled) {
            return;
        }

        DB::transaction(function () use ($organization, $enabled): void {
            if ($enabled) {
                ContactGender::activate($organization);
            } else {
                ContactGender::deactivate($organization);
            }

            $this->recordToggle($organization, ContactGender::FEATURE, $enabled);
        });

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

        if ($this->featureEnabled($organization, MemberDeletion::FEATURE) === $enabled) {
            return;
        }

        DB::transaction(function () use ($organization, $enabled): void {
            if ($enabled) {
                MemberDeletion::activate($organization);
            } else {
                MemberDeletion::deactivate($organization);
            }

            $this->recordToggle($organization, MemberDeletion::FEATURE, $enabled);
        });

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

        if ($this->featureEnabled($organization, ContactDeletion::FEATURE) === $enabled) {
            return;
        }

        DB::transaction(function () use ($organization, $enabled): void {
            if ($enabled) {
                ContactDeletion::activate($organization);
            } else {
                ContactDeletion::deactivate($organization);
            }

            $this->recordToggle($organization, ContactDeletion::FEATURE, $enabled);
        });

        Flux::toast(
            variant: 'success',
            text: $enabled ? __('Contact deletion enabled.') : __('Contact deletion disabled.'),
        );
    }

    private function recordToggle(Organization $organization, string $feature, bool $enabled): void
    {
        $actor = Auth::user();

        event(new FeatureToggled($organization, $actor instanceof User ? $actor : null, $feature, $enabled));
    }

    private function featureEnabled(Organization $organization, string $feature): bool
    {
        return (bool) Feature::for($organization)->active($feature);
    }
}
