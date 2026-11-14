<?php

declare(strict_types=1);

namespace App\Livewire\OrgSettings;

use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\ApprovalRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ApprovalHistory extends Component
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
     * The current organization's approval requests, newest first.
     *
     * @return Collection<int, ApprovalRequest>
     */
    #[Computed]
    public function approvals(): Collection
    {
        return ApprovalRequest::query()
            ->where('organization_id', $this->requireOrganization()->getKey())
            ->with(['requester', 'approver'])
            ->latest()
            ->get();
    }

    public function render(): View
    {
        /** @disregard P1013 */
        return view('livewire.org-settings.approval-history')->title(__('Approvals history'));
    }
}
