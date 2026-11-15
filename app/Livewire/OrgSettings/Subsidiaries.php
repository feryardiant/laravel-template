<?php

declare(strict_types=1);

namespace App\Livewire\OrgSettings;

use App\Actions\Organizations\ListSubsidiaries;
use App\Enums\SubsidiaryKind;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\Organization;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class Subsidiaries extends Component
{
    use HasCurrentOrganization;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $organization = $this->currentOrganization();

        abort_if($organization === null || ! $organization->isRootTenant(), 404);

        $role = $this->currentRole();

        abort_unless($role?->isOwner() || $role?->isAdmin(), 403);
    }

    /**
     * The root organization, when the current organization is the root tenant.
     */
    #[Computed]
    public function root(): ?Organization
    {
        $organization = $this->currentOrganization();

        return $organization?->isRootTenant() === true ? $organization : null;
    }

    /**
     * The root tenant's subsidiary tree, depth-first.
     *
     * @return Collection<int, array{organization: Organization, parent: Organization, kind: ?SubsidiaryKind, depth: int}>
     */
    #[Computed]
    public function subsidiaries(): Collection
    {
        $organization = $this->currentOrganization();
        $root = $organization?->isRootTenant() === true ? $organization : null;

        return $root === null ? collect() : app(ListSubsidiaries::class)->handle($root);
    }

    /**
     * Only an owner may create or remove subsidiaries.
     */
    #[Computed]
    public function canManage(): bool
    {
        return $this->currentRole()?->isOwner() === true;
    }

    #[On('organization-created')]
    #[On('subsidiary-removed')]
    public function refreshSubsidiaries(): void
    {
        //
    }

    public function render(): View
    {
        /** @disregard P1013 */
        return view('livewire.org-settings.subsidiaries')->title(__('Subsidiaries'));
    }
}
