<?php

declare(strict_types=1);

namespace App\Livewire\Settings\Organizations;

use App\Actions\Approvals\MembershipLeave;
use App\Actions\Approvals\OwnershipTransfer;
use App\Enums\ApprovalRequestType;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Repositories\ApprovalRepository;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Reviews and answers pending ownership-transfer and leave requests.
 */
class TransferApproval extends Component
{
    public bool $showModal = false;

    public ?int $approvalId = null;

    public ?string $approvalType = null;

    public ?string $organizationName = null;

    public ?string $requesterName = null;

    /**
     * Open a request review when the signed email link sent us here.
     */
    public function mount(): void
    {
        $uuid = request()->query('approval');
        /** @var User $user */
        $user = Auth::user();

        if (! \is_string($uuid) || $uuid === '') {
            return;
        }

        $approval = app(ApprovalRepository::class)->findForApproverByUuid($user, $uuid);

        if ($approval !== null) {
            $this->openModal($approval);
        }
    }

    public function render(): View
    {
        return view('livewire.settings.organizations.transfer-approval');
    }

    /**
     * Open the review modal for an organization's pending request.
     */
    #[On('respond')]
    public function respond(int $organizationId): void
    {
        /** @var User $user */
        $user = Auth::user();

        $approval = app(ApprovalRepository::class)->findOpenForApproverByOrganization($user, $organizationId);

        abort_if($approval === null, 404);

        $this->openModal($approval);
    }

    /**
     * Accept the pending request.
     */
    public function accept(
        OwnershipTransfer $transferOwnership,
        MembershipLeave $leaveMembership,
    ): void {
        /** @var User $user */
        $user = Auth::user();

        $approval = $this->pendingApprovalForUser();

        if ($approval === null) {
            $invalidMessage = $this->invalidMessage();

            $this->dismiss();
            Flux::toast(variant: 'danger', text: $invalidMessage);

            return;
        }

        $accepted = match ($approval->type) {
            ApprovalRequestType::OwnershipTransfer => $transferOwnership->accept($approval, $user),
            ApprovalRequestType::MembershipLeave => $leaveMembership->accept($approval, $user),
        };

        $organizationName = $approval->organization->name;
        $invalidMessage = $this->invalidMessageFor($approval->type);

        $this->dismiss();

        Flux::toast(
            variant: $accepted ? 'success' : 'danger',
            text: match (true) {
                ! $accepted => $invalidMessage,
                $approval->type === ApprovalRequestType::OwnershipTransfer => __('You are now the owner of :organization.', ['organization' => $organizationName]),
                default => __('Leave request approved.'),
            },
        );
    }

    /**
     * Decline the pending request.
     */
    public function decline(
        OwnershipTransfer $transferOwnership,
        MembershipLeave $leaveMembership,
    ): void {
        /** @var User $user */
        $user = Auth::user();

        $approval = $this->pendingApprovalForUser();

        if ($approval === null) {
            $invalidMessage = $this->invalidMessage();

            $this->dismiss();
            Flux::toast(variant: 'danger', text: $invalidMessage);

            return;
        }

        $declined = match ($approval->type) {
            ApprovalRequestType::OwnershipTransfer => $transferOwnership->decline($approval, $user),
            ApprovalRequestType::MembershipLeave => $leaveMembership->decline($approval, $user),
        };

        $invalidMessage = $this->invalidMessageFor($approval->type);

        $this->dismiss();

        Flux::toast(
            variant: $declined ? 'success' : 'danger',
            text: match (true) {
                ! $declined => $invalidMessage,
                $approval->type === ApprovalRequestType::OwnershipTransfer => __('Ownership transfer declined.'),
                default => __('Leave request declined.'),
            },
        );
    }

    /**
     * Resolve the request currently open in the modal.
     */
    private function pendingApprovalForUser(): ?ApprovalRequest
    {
        /** @var User $user */
        $user = Auth::user();

        if ($this->approvalId === null) {
            return null;
        }

        return app(ApprovalRepository::class)->findForApprover($user, $this->approvalId);
    }

    /**
     * A message matching the request type currently in the modal.
     */
    private function invalidMessage(): string
    {
        return $this->approvalType === ApprovalRequestType::OwnershipTransfer->value
            ? __('This ownership transfer is no longer valid.')
            : __('This request is no longer valid.');
    }

    /**
     * A message matching the given request type.
     */
    private function invalidMessageFor(ApprovalRequestType $type): string
    {
        return $type === ApprovalRequestType::OwnershipTransfer
            ? __('This ownership transfer is no longer valid.')
            : __('This request is no longer valid.');
    }

    /**
     * Close the approval modal and drop its cached data.
     */
    private function dismiss(): void
    {
        $this->reset('showModal', 'approvalId', 'approvalType', 'organizationName', 'requesterName');

        $this->dispatch('memberships-changed');
    }

    /**
     * Fill the approval modal from the request.
     */
    private function openModal(ApprovalRequest $approval): void
    {
        $approval->loadMissing(['organization', 'requester']);

        $this->approvalId = $approval->getKey();
        $this->approvalType = $approval->type->value;
        $this->organizationName = $approval->organization->name;
        $this->requesterName = $approval->requester->name;
        $this->showModal = true;
    }
}
