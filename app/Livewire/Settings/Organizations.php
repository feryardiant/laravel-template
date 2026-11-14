<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Actions\Memberships\LeaveOrganization;
use App\Actions\Organizations\AcceptMembershipLeave;
use App\Actions\Organizations\AcceptOwnershipTransfer;
use App\Actions\Organizations\CancelApprovalRequest;
use App\Actions\Organizations\DeclineMembershipLeave;
use App\Actions\Organizations\DeclineOwnershipTransfer;
use App\Actions\Organizations\RequestMembershipLeave;
use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Enums\SubsidiaryKind;
use App\Jobs\DeleteOrganization;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
use App\Models\User;
use App\Support\CurrentOrganization;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class Organizations extends Component
{
    use HasCurrentOrganization;

    public bool $showLeaveModal = false;

    public ?int $leavingOrganizationId = null;

    public ?string $leavingOrganizationName = null;

    public bool $leaveNeedsApproval = false;

    public bool $showRemoveModal = false;

    public ?int $removingOrganizationId = null;

    public ?string $removingOrganizationName = null;

    public string $confirmation = '';

    public bool $showApprovalModal = false;

    public ?int $approvalId = null;

    public ?string $approvalType = null;

    public ?string $approvalOrganizationName = null;

    public ?string $approvalRequesterName = null;

    /**
     * Open a request review when the signed email link sent us here.
     */
    public function mount(): void
    {
        $uuid = request()->query('approval');
        $user = Auth::user();

        if (! \is_string($uuid) || $uuid === '' || ! $user instanceof User || $user->person === null) {
            return;
        }

        $approval = ApprovalRequest::query()
            ->where('uuid', $uuid)
            ->where('approver_person_id', $user->person->getKey())
            ->where('status', ApprovalRequestStatus::Pending)
            ->where('expires_at', '>', now())
            ->first();

        if ($approval !== null) {
            $this->openApprovalModal($approval);
        }
    }

    /**
     * Open the review modal for an organization's pending request.
     */
    public function openApproval(int $organizationId): void
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $user->person !== null, 403);

        $approval = ApprovalRequest::query()
            ->where('organization_id', $organizationId)
            ->where('approver_person_id', $user->person->getKey())
            ->where('status', ApprovalRequestStatus::Pending)
            ->where('expires_at', '>', now())
            ->first();

        abort_if($approval === null, 404);

        $this->openApprovalModal($approval);
    }

    /**
     * Accept the pending request.
     */
    public function acceptApproval(
        AcceptOwnershipTransfer $acceptTransfer,
        AcceptMembershipLeave $acceptLeave,
    ): void {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        $approval = $this->pendingApprovalForUser();

        if ($approval === null) {
            $invalidMessage = $this->invalidMessage();

            $this->dismissApproval();
            Flux::toast(variant: 'danger', text: $invalidMessage);

            return;
        }

        $accepted = match ($approval->type) {
            ApprovalRequestType::OwnershipTransfer => $acceptTransfer->handle($approval, $user),
            ApprovalRequestType::MembershipLeave => $acceptLeave->handle($approval, $user),
        };

        $organizationName = $approval->organization->name;
        $invalidMessage = $this->invalidMessageFor($approval->type);

        $this->dismissApproval();

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
    public function declineApproval(
        DeclineOwnershipTransfer $declineTransfer,
        DeclineMembershipLeave $declineLeave,
    ): void {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        $approval = $this->pendingApprovalForUser();

        if ($approval === null) {
            $invalidMessage = $this->invalidMessage();

            $this->dismissApproval();
            Flux::toast(variant: 'danger', text: $invalidMessage);

            return;
        }

        $declined = match ($approval->type) {
            ApprovalRequestType::OwnershipTransfer => $declineTransfer->handle($approval, $user),
            ApprovalRequestType::MembershipLeave => $declineLeave->handle($approval, $user),
        };

        $invalidMessage = $this->invalidMessageFor($approval->type);

        $this->dismissApproval();

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
     * Cancel the current user's pending leave request.
     */
    public function cancelLeaveRequest(int $approvalId, CancelApprovalRequest $cancelRequest): void
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $user->person !== null, 403);

        $approval = ApprovalRequest::query()
            ->whereKey($approvalId)
            ->where('type', ApprovalRequestType::MembershipLeave)
            ->where('requester_person_id', $user->person->getKey())
            ->first();

        abort_if($approval === null, 404);

        $cancelRequest->handle($approval, $user);

        unset($this->memberships);

        Flux::toast(variant: 'success', text: __('Leave request cancelled.'));
    }

    /**
     * Resolve the request currently open in the modal.
     */
    private function pendingApprovalForUser(): ?ApprovalRequest
    {
        $user = Auth::user();

        if ($this->approvalId === null || ! $user instanceof User || $user->person === null) {
            return null;
        }

        return ApprovalRequest::query()
            ->whereKey($this->approvalId)
            ->where('approver_person_id', $user->person->getKey())
            ->first();
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
    private function dismissApproval(): void
    {
        $this->reset('showApprovalModal', 'approvalId', 'approvalType', 'approvalOrganizationName', 'approvalRequesterName');
        unset($this->memberships);
    }

    /**
     * Fill the approval modal from the request.
     */
    private function openApprovalModal(ApprovalRequest $approval): void
    {
        $approval->loadMissing(['organization', 'requester']);

        $this->approvalId = $approval->getKey();
        $this->approvalType = $approval->type->value;
        $this->approvalOrganizationName = $approval->organization->name;
        $this->approvalRequesterName = $approval->requester->name;
        $this->showApprovalModal = true;
    }

    /**
     * Select an organization to leave.
     */
    public function confirmLeave(int $organizationId, string $name): void
    {
        $user = Auth::user();

        $organization = $user instanceof User
            ? $user->organizations()->firstWhere('id', $organizationId)
            : null;

        $this->leavingOrganizationId = $organizationId;
        $this->leavingOrganizationName = $name;
        $this->leaveNeedsApproval = $organization !== null && $user->roleIn($organization)?->isAdmin() === true;
        $this->showLeaveModal = true;
    }

    /**
     * Leave the selected organization, or request approval when an admin.
     */
    public function leave(LeaveOrganization $leaveOrganization, RequestMembershipLeave $requestLeave): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        abort_if($this->leavingOrganizationId === null || ! $user instanceof User, 404);

        $organization = $user->organizations()->firstWhere('id', $this->leavingOrganizationId);

        abort_if($organization === null, 404);

        if ($user->roleIn($organization)?->isAdmin() === true) {
            try {
                $requestLeave->handle($organization, $user);
            } catch (InvalidArgumentException) {
                $this->reset('showLeaveModal', 'leavingOrganizationId', 'leavingOrganizationName', 'leaveNeedsApproval');

                Flux::toast(variant: 'danger', text: __('You already have a pending leave request.'));

                return;
            }

            $this->reset('showLeaveModal', 'leavingOrganizationId', 'leavingOrganizationName', 'leaveNeedsApproval');
            unset($this->memberships);

            Flux::toast(variant: 'success', text: __('Leave request sent. An owner must approve it.'));

            return;
        }

        $wasCurrent = $this->currentOrganization()?->is($organization) === true;

        if (! $leaveOrganization->handle($user, $organization)) {
            Flux::toast(variant: 'danger', text: __('You cannot leave while you are the last owner.'));

            $this->reset('showLeaveModal', 'leavingOrganizationId', 'leavingOrganizationName', 'leaveNeedsApproval');

            return;
        }

        $this->reset('showLeaveModal', 'leavingOrganizationId', 'leavingOrganizationName', 'leaveNeedsApproval');

        if (! $wasCurrent) {
            Flux::toast(variant: 'success', text: __('You left :organization.', ['organization' => $organization->name]));

            return;
        }

        session()->forget(CurrentOrganization::SESSION_KEY);

        $this->redirect(route('dashboard'), navigate: true);
    }

    /**
     * Select an organization to remove.
     */
    public function confirmRemove(int $organizationId): void
    {
        $organization = $this->removableOrganization($organizationId);

        $this->removingOrganizationId = $organization->getKey();
        $this->removingOrganizationName = $organization->name;
        $this->confirmation = '';
        $this->showRemoveModal = true;
    }

    /**
     * Queue the removal of the selected organization.
     */
    public function remove(): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        abort_if($this->removingOrganizationId === null || ! $user instanceof User, 404);

        $organization = $this->removableOrganization($this->removingOrganizationId);

        if ($this->confirmation !== $organization->name) {
            $this->addError('confirmation', __('The name does not match.'));

            return;
        }

        DeleteOrganization::dispatch($organization, $user->getKey());

        if ($this->currentOrganization()?->is($organization) === true) {
            session()->forget(CurrentOrganization::SESSION_KEY);
        }

        $this->reset('showRemoveModal', 'removingOrganizationId', 'removingOrganizationName', 'confirmation');
        unset($this->memberships);

        Flux::toast(
            variant: 'success',
            text: __('Removal queued. :name will be deleted shortly.', ['name' => $organization->name]),
        );
    }

    /**
     * Resolve an organization the current user may remove.
     */
    private function removableOrganization(int $organizationId): Organization
    {
        /** @var User|null $user */
        $user = Auth::user();

        abort_if(! $user instanceof User, 403);

        $organization = $user->organizations()->firstWhere('id', $organizationId);

        abort_if($organization === null, 404);
        abort_if($organization->isRootTenant(), 403);
        abort_unless($user->roleIn($organization)?->isOwner(), 403);
        abort_unless($organization->activeOwnerCount() === 1, 403);

        return $organization;
    }

    /**
     * Switch to the organization and open its settings.
     */
    public function manage(int $organizationId): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        $organization = Organization::query()->findOrFail($organizationId);

        $role = $user->roleIn($organization);

        abort_unless($role?->isOwner() || $role?->isAdmin(), 403);
        abort_unless(app(CurrentOrganization::class)->switch($user, $organization), 403);

        $this->redirect(route('org-settings.general'), navigate: true);
    }

    /**
     * The organizations the user belongs to, with their role and options.
     *
     * @return Collection<int, array{organization: Organization, role: ?Role, isCurrent: bool, canManage: bool, isLastOwner: bool, parent: ?Organization, kind: ?SubsidiaryKind, pendingApproval: ?ApprovalRequest, pendingRequest: ?ApprovalRequest}>
     */
    #[Computed]
    public function memberships(): Collection
    {
        /** @var User|null $user */
        $user = Auth::user();
        $current = $this->currentOrganization();

        $organizations = $user instanceof User ? $user->organizations() : collect();

        $parents = $organizations->isEmpty()
            ? collect()
            : OrganizationRelationship::query()
                ->with('organization')
                ->where('type', OrganizationRelationshipType::Subsidiary)
                ->where('stakeholder_type', (new Organization)->getMorphClass())
                ->whereIn('stakeholder_id', $organizations->pluck('id')->all())
                ->get()
                ->keyBy('stakeholder_id');

        $personId = $user?->person?->getKey();

        $approvals = $personId === null
            ? collect()
            : ApprovalRequest::query()
                ->pending()
                ->where('expires_at', '>', now())
                ->where(fn ($query) => $query
                    ->where('requester_person_id', $personId)
                    ->orWhere('approver_person_id', $personId))
                ->with('requester')
                ->get();

        $asApprover = $approvals->where('approver_person_id', $personId)->keyBy('organization_id');
        $asRequester = $approvals->where('requester_person_id', $personId)->keyBy('organization_id');

        return $organizations->map(function (Organization $organization) use ($user, $current, $parents, $asApprover, $asRequester): array {
            $role = $user?->roleIn($organization);
            $parent = $parents->get($organization->getKey());
            $approval = $asApprover->get($organization->getKey());
            $request = $asRequester->get($organization->getKey());

            return [
                'organization' => $organization,
                'role' => $role,
                'isCurrent' => $current?->is($organization) === true,
                'canManage' => $role?->isOwner() === true || $role?->isAdmin() === true,
                'isLastOwner' => $role?->isOwner() === true && $organization->activeOwnerCount() === 1,
                'parent' => $parent instanceof OrganizationRelationship ? $parent->organization : null,
                'kind' => $parent instanceof OrganizationRelationship
                    ? ($parent->kind ?? SubsidiaryKind::Subsidiary)
                    : null,
                'pendingApproval' => $approval instanceof ApprovalRequest ? $approval : null,
                'pendingRequest' => $request instanceof ApprovalRequest ? $request : null,
            ];
        });
    }

    #[On('organization-created')]
    public function refreshOrganizations(): void
    {
        //
    }

    public function render(): View
    {
        /** @disregard P1013 */
        return view('livewire.settings.organizations')->title(__('Organizations'));
    }
}
