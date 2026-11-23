<?php

declare(strict_types=1);

namespace App\Livewire\Settings\Organizations;

use App\Actions\Approvals\CancelApprovalRequest;
use App\Actions\Approvals\FindApprovalRequest;
use App\Actions\Memberships\LeaveWorkspace;
use App\Actions\Organizations\FindUserOrganization;
use App\Enums\ApprovalRequestType;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\User;
use App\Support\CurrentOrganization;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class Leave extends Component
{
    use HasCurrentOrganization;

    public bool $showModal = false;

    public ?int $organizationId = null;

    public ?string $organizationName = null;

    public bool $needsApproval = false;

    /**
     * Select an organization to leave.
     */
    #[On('leave-organization')]
    public function confirm(int $organizationId, string $name): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        $organization = $user instanceof User
            ? $user->organizations()->firstWhere('id', $organizationId)
            : null;

        $this->organizationId = $organizationId;
        $this->organizationName = $name;
        $this->needsApproval = $organization !== null && $user->roleIn($organization)?->isAdmin() === true;
        $this->showModal = true;
    }

    /**
     * Cancel the current user's pending leave request.
     */
    #[On('cancel-leave-request')]
    public function cancelRequest(int $approvalId, CancelApprovalRequest $cancelRequest): void
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $user->person !== null, 403);

        $approval = app(FindApprovalRequest::class)->forRequester($user, $approvalId, ApprovalRequestType::MembershipLeave);

        abort_if($approval === null, 404);

        $cancelRequest->handle($approval, $user);

        $this->dispatch('memberships-changed');

        Flux::toast(variant: 'success', text: __('Leave request canceled.'));
    }

    /**
     * Leave the selected organization, or request approval when an admin.
     */
    public function leave(LeaveWorkspace $leaveWorkspace): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        abort_if($this->organizationId === null || ! $user instanceof User, 404);

        $organization = app(FindUserOrganization::class)->handle($user, $this->organizationId);
        $wasCurrent = $this->currentOrganization()?->is($organization) === true;
        $result = $leaveWorkspace->handle($organization, $user);

        $this->reset('showModal', 'organizationId', 'organizationName', 'needsApproval');

        if ($result === 'pending') {
            Flux::toast(variant: 'danger', text: __('You already have a pending leave request.'));

            return;
        }

        if ($result === 'last_owner') {
            Flux::toast(variant: 'danger', text: __('You cannot leave while you are the last owner.'));

            return;
        }

        if ($result === 'requested') {
            $this->dispatch('memberships-changed');

            Flux::toast(variant: 'success', text: __('Leave request sent. An owner must approve it.'));

            return;
        }

        if (! $wasCurrent) {
            $this->dispatch('memberships-changed');

            Flux::toast(variant: 'success', text: __('You left :organization.', ['organization' => $organization->name]));

            return;
        }

        session()->forget(CurrentOrganization::SESSION_KEY);

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.settings.organizations.leave');
    }
}
