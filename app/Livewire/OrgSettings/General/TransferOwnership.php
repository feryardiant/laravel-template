<?php

declare(strict_types=1);

namespace App\Livewire\OrgSettings\General;

use App\Actions\Approvals\CancelApprovalRequest;
use App\Actions\Approvals\FindApprovalRequest;
use App\Actions\Approvals\ListOwnershipTransferCandidates;
use App\Actions\Approvals\OwnershipTransfer;
use App\Enums\ApprovalRequestType;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\ApprovalRequest;
use App\Models\Person;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * @property-read Collection<int, Person> $members
 * @property-read ?ApprovalRequest $pendingApproval
 */
class TransferOwnership extends Component
{
    use HasCurrentOrganization;

    public bool $showTransferModal = false;

    public ?string $recipientId = null;

    public string $confirmation = '';

    public function render(): View
    {
        return view('livewire.org-settings.general.transfer-ownership', [
            'organization' => $this->currentOrganization(),
        ]);
    }

    /**
     * Active members with a credential, excluding the owner.
     *
     * @return Collection<int, Person>
     */
    #[Computed()]
    public function members(): Collection
    {
        $organization = $this->currentOrganization();
        $owner = Auth::user()?->person;

        if ($organization === null || $owner === null) {
            return new Collection;
        }

        return app(ListOwnershipTransferCandidates::class)->handle($organization, $owner);
    }

    /**
     * The pending approval request for the current organization.
     */
    #[Computed]
    public function pendingApproval(): ?ApprovalRequest
    {
        $organization = $this->currentOrganization();

        if ($organization === null) {
            return null;
        }

        $this->authorize('transferOwnership', $organization);

        return app(FindApprovalRequest::class)->pendingForOrganization($organization);
    }

    /**
     * Cancel the pending ownership transfer.
     */
    public function cancelTransfer(CancelApprovalRequest $cancelRequest): void
    {
        $organization = $this->currentOrganization();

        abort_if($organization === null, 404);

        $this->authorize('transferOwnership', $organization);

        $approval = $this->pendingApproval;

        abort_if($approval === null || $approval->type !== ApprovalRequestType::OwnershipTransfer, 404);

        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        $cancelRequest->handle($approval, $user);

        unset($this->pendingApproval);

        Flux::toast(variant: 'success', text: __('Transfer request canceled.'));
    }

    /**
     * Open the ownership transfer modal.
     */
    public function confirmTransfer(): void
    {
        $organization = $this->currentOrganization();

        abort_if($organization === null, 404);

        $this->authorize('transferOwnership', $organization);

        abort_if($this->pendingApproval !== null, 409);

        $this->reset('recipientId', 'confirmation');
        $this->resetValidation();
        $this->showTransferModal = true;
    }

    /**
     * Send an ownership transfer request to another member.
     */
    public function transfer(OwnershipTransfer $transferOwnership): void
    {
        $organization = $this->currentOrganization();

        abort_if($organization === null, 404);

        $this->authorize('transferOwnership', $organization);

        $members = $this->members();

        $validated = $this->validate([
            'recipientId' => ['required', 'integer', Rule::in($members->pluck('id')->all())],
            'confirmation' => ['required', 'string', Rule::in([$organization->name])],
        ]);

        $recipient = $members->firstWhere('id', (int) $validated['recipientId']);

        abort_if($recipient === null, 404);

        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        try {
            $transferOwnership->request($organization, $user, $recipient);
        } catch (InvalidArgumentException) {
            $this->reset('showTransferModal', 'recipientId', 'confirmation');

            Flux::toast(variant: 'danger', text: __('Another approval request is already pending.'));

            return;
        }

        $this->reset('showTransferModal', 'recipientId', 'confirmation');
        unset($this->pendingApproval, $this->transferMembers);

        Flux::toast(
            variant: 'success',
            text: __('Ownership transfer request sent to :name.', ['name' => $recipient->name]),
        );
    }
}
