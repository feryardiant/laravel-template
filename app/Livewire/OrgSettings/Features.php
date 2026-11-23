<?php

declare(strict_types=1);

namespace App\Livewire\OrgSettings;

use App\Actions\Tenancies\ToggleFeature;
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

        $this->authorize('manageFeatures', $organization);

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

        $this->authorize('manageFeatures', $organization);

        if (! $this->toggle($organization, Registration::class, $enabled)) {
            return;
        }

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

        $this->authorize('manageFeatures', $organization);

        if (! $this->toggle($organization, ProfileSelfEdit::class, $enabled)) {
            return;
        }

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

        $this->authorize('manageFeatures', $organization);

        if (! $this->toggle($organization, ContactGender::class, $enabled)) {
            return;
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

        $this->authorize('manageFeatures', $organization);

        if (! $this->toggle($organization, MemberDeletion::class, $enabled)) {
            return;
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

        $this->authorize('manageFeatures', $organization);

        if (! $this->toggle($organization, ContactDeletion::class, $enabled)) {
            return;
        }

        Flux::toast(
            variant: 'success',
            text: $enabled ? __('Contact deletion enabled.') : __('Contact deletion disabled.'),
        );
    }

    /**
     * @param  class-string<Registration|ProfileSelfEdit|ContactGender|MemberDeletion|ContactDeletion>  $flag
     */
    private function toggle(Organization $organization, string $flag, bool $enabled): bool
    {
        $actor = Auth::user();

        return app(ToggleFeature::class)->handle(
            $organization,
            $flag,
            $enabled,
            $actor instanceof User ? $actor : null,
        );
    }
}
