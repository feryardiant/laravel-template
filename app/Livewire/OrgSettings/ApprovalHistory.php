<?php

declare(strict_types=1);

namespace App\Livewire\OrgSettings;

use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\ApprovalRequest;
use App\Repositories\ApprovalRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * @property-read LengthAwarePaginator<int, ApprovalRequest> $approvals
 */
class ApprovalHistory extends Component
{
    use HasCurrentOrganization, WithPagination;

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
     * @return LengthAwarePaginator<int, ApprovalRequest>
     */
    #[Computed]
    public function approvals(): LengthAwarePaginator
    {
        $organization = $this->requireOrganization();

        $this->authorize('viewApprovals', $organization);

        return app(ApprovalRepository::class)->getForOrganization($organization);
    }
}
