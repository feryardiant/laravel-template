<?php

declare(strict_types=1);

namespace App\Livewire\OrgSettings;

use App\Actions\Approvals\ListApprovals;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\ApprovalRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * @property-read Collection<int, ApprovalRequest> $approvals
 */
class ApprovalHistory extends Component
{
    use HasCurrentOrganization;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        abort_if($this->currentOrganization() === null, 404);

        $this->authorize('viewApprovals', $this->requireOrganization());
    }

    public function render(): View
    {
        /** @disregard P1013 */
        return view('livewire.org-settings.approval-history')->title(__('Approvals history'));
    }

    /**
     * The current organization's approval requests, newest first.
     *
     * @return Collection<int, ApprovalRequest>
     */
    #[Computed]
    public function approvals(): Collection
    {
        $organization = $this->requireOrganization();

        $this->authorize('viewApprovals', $organization);

        return app(ListApprovals::class)->forOrganization($organization);
    }
}
