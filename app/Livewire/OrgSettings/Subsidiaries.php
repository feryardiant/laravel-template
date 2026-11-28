<?php

declare(strict_types=1);

namespace App\Livewire\OrgSettings;

use App\Enums\SubsidiaryKind;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\Organization;
use App\Repositories\OrganizationRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * @property-read ?Organization $root
 * @property-read LengthAwarePaginator<int, array{organization: Organization, parent: Organization, kind: ?SubsidiaryKind, depth: int}> $subsidiaries
 * @property-read bool $canManage
 */
class Subsidiaries extends Component
{
    use HasCurrentOrganization, WithPagination;

    private const PER_PAGE = 50;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $organization = $this->currentOrganization();

        abort_if($organization === null || ! $organization->isRootTenant(), 404);

        $this->authorize('viewSubsidiaries', $organization);
    }

    public function render(): View
    {
        /** @disregard P1013 */
        return view('livewire.org-settings.subsidiaries')->title(__('Subsidiaries'));
    }

    /**
     * Refresh after a subsidiary is created or removed.
     */
    #[On('organization-created')]
    #[On('subsidiary-removed')]
    public function refreshSubsidiaries(): void
    {
        //
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
     * The root tenant's subsidiary tree, depth-first, page by page.
     *
     * @return LengthAwarePaginator<int, array{organization: Organization, parent: Organization, kind: ?SubsidiaryKind, depth: int}>
     */
    #[Computed]
    public function subsidiaries(): LengthAwarePaginator
    {
        $organization = $this->currentOrganization();

        abort_if($organization === null || ! $organization->isRootTenant(), 404);

        $this->authorize('viewSubsidiaries', $organization);

        $rows = app(OrganizationRepository::class)->getSubsidiaries($organization);

        return new LengthAwarePaginator(
            $rows->forPage($this->getPage(), self::PER_PAGE)->values(),
            $rows->count(),
            self::PER_PAGE,
            $this->getPage(),
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }

    /**
     * Only an owner may create or remove subsidiaries.
     */
    #[Computed]
    public function canManage(): bool
    {
        $organization = $this->currentOrganization();

        return $organization !== null && Gate::allows('createSubsidiary', $organization);
    }
}
