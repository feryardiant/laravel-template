<?php

declare(strict_types=1);

namespace App\Livewire\OrgSettings;

use App\Actions\Tenancies\ToggleFeature;
use App\Enums\Feature;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\Organization;
use Flux\Flux;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Toggles the feature flags available to the current organization.
 */
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
        $this->registration = $this->isRootTenant && Feature::Registration->enabled();
        $this->contactGender = Feature::ContactGender->enabled($organization);
        $this->memberDeletion = Feature::MemberDeletion->enabled($organization);
        $this->contactDeletion = Feature::ContactDeletion->enabled($organization);
        $this->profileSelfEdit = Feature::ProfileSelfEdit->enabled($organization);
    }

    /**
     * Toggle public registration for the root tenant.
     */
    public function updatedRegistration(bool $enabled): void
    {
        if (! $this->toggle(Feature::Registration, $enabled)) {
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
        if (! $this->toggle(Feature::ProfileSelfEdit, $enabled)) {
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
        if (! $this->toggle(Feature::ContactGender, $enabled)) {
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
        if (! $this->toggle(Feature::MemberDeletion, $enabled)) {
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
        if (! $this->toggle(Feature::ContactDeletion, $enabled)) {
            return;
        }

        Flux::toast(
            variant: 'success',
            text: $enabled ? __('Contact deletion enabled.') : __('Contact deletion disabled.'),
        );
    }

    private function toggle(Feature $feature, bool $enabled): bool
    {
        $organization = $this->requireOrganization([
            404 => static fn (?Organization $organization): bool => $organization === null,
            409 => fn (?Organization $organization): bool => $organization?->getKey() !== $this->organizationId,
        ]);

        $this->authorize('manageFeatures', $organization);

        try {
            return app(ToggleFeature::class)->handle($organization, $feature, $enabled);
        } catch (InvalidArgumentException) {
            // ToggleFeature only throws this for a root-only flag on a child tenant.
            abort(404);
        }
    }
}
