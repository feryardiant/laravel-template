<?php

declare(strict_types=1);

namespace App\Livewire\OrgSettings;

use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\OrganizationOwnershipTransfer;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class OwnershipHistory extends Component
{
    use HasCurrentOrganization;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        abort_if($this->currentOrganization() === null, 404);

        abort_unless($this->currentRole()?->isOwner(), 403);
    }

    /**
     * The current organization's ownership transfers, newest first.
     *
     * @return Collection<int, OrganizationOwnershipTransfer>
     */
    #[Computed]
    public function transfers(): Collection
    {
        return OrganizationOwnershipTransfer::query()
            ->where('organization_id', $this->requireOrganization()->getKey())
            ->with(['fromPerson', 'toPerson'])
            ->latest()
            ->get();
    }

    public function render(): View
    {
        /** @disregard P1013 */
        return view('livewire.org-settings.ownership-history')->title(__('Ownership history'));
    }
}
