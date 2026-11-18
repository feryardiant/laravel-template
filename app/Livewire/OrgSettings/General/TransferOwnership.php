<?php

declare(strict_types=1);

namespace App\Livewire\OrgSettings\General;

use App\Actions\Approvals\CancelApprovalRequest;
use App\Actions\Approvals\OwnershipTransfer;
use App\Enums\ApprovalRequestType;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\ApprovalRequest;
use App\Models\Person;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * @property-read ?ApprovalRequest $pendingApproval
 */
class TransferOwnership extends Component
{
    use HasCurrentOrganization;

    public bool $showTransferModal = false;

    public ?string $recipientId = null;

    public string $confirmation = '';

    /**
     * Active members with a credential, excluding the owner.
     *
     * @return Collection<int, Person>
     */
    #[Computed()]
    public function members(): Collection
    {
        $organization = $this->currentOrganization();
        $owner = Auth::user()?->person?->getKey();

        if ($organization === null || $owner === null) {
            return new Collection;
        }

        $today = now()->toDateString();

        return $organization->members()
            ->whereHas('user')
            ->whereKeyNot($owner)
            ->where(fn (Builder $query): Builder => $query->whereNull('organization_relationships.start_date')
                ->orWhere('organization_relationships.start_date', '<=', $today))
            ->where(fn (Builder $query): Builder => $query->whereNull('organization_relationships.finish_date')
                ->orWhere('organization_relationships.finish_date', '>=', $today))
            ->with('user')
            ->orderBy('people.name')
            ->get();
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

        return ApprovalRequest::query()
            ->pending()
            ->where('organization_id', $organization->getKey())
            ->where('expires_at', '>', now())
            ->with(['requester', 'approver'])
            ->first();
    }

    /**
     * Cancel the pending ownership transfer.
     */
    public function cancelTransfer(CancelApprovalRequest $cancelRequest): void
    {
        $organization = $this->currentOrganization();

        abort_if($organization === null, 404);

        $this->authorizeOwner();

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
        $this->authorizeOwner();

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

        $this->authorizeOwner();

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

    public function render(): View
    {
        return view('livewire.org-settings.general.transfer-ownership', [
            'organization' => $this->currentOrganization(),
        ]);
    }
}
