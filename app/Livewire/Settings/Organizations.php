<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Actions\Memberships\LeaveOrganization;
use App\Actions\Organizations\AcceptOwnershipTransfer;
use App\Actions\Organizations\DeclineOwnershipTransfer;
use App\Enums\OrganizationRelationshipType;
use App\Enums\OwnershipTransferStatus;
use App\Enums\Role;
use App\Enums\SubsidiaryKind;
use App\Jobs\DeleteOrganization;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\Organization;
use App\Models\OrganizationOwnershipTransfer;
use App\Models\OrganizationRelationship;
use App\Models\User;
use App\Support\CurrentOrganization;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class Organizations extends Component
{
    use HasCurrentOrganization;

    public bool $showLeaveModal = false;

    public ?int $leavingOrganizationId = null;

    public ?string $leavingOrganizationName = null;

    public bool $showRemoveModal = false;

    public ?int $removingOrganizationId = null;

    public ?string $removingOrganizationName = null;

    public string $confirmation = '';

    public bool $showTransferModal = false;

    public ?int $transferId = null;

    public ?string $transferOrganizationName = null;

    public ?string $transferOwnerName = null;

    /**
     * Open a transfer review when the signed email link sent us here.
     */
    public function mount(): void
    {
        $uuid = request()->query('transfer');
        $user = Auth::user();

        if (! \is_string($uuid) || $uuid === '' || ! $user instanceof User || $user->person === null) {
            return;
        }

        $transfer = OrganizationOwnershipTransfer::query()
            ->where('uuid', $uuid)
            ->where('to_person_id', $user->person->getKey())
            ->where('status', OwnershipTransferStatus::Pending)
            ->where('expires_at', '>', now())
            ->first();

        if ($transfer !== null) {
            $this->openTransferModal($transfer);
        }
    }

    /**
     * Open the ownership transfer review modal for an organization.
     */
    public function openTransfer(int $organizationId): void
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $user->person !== null, 403);

        $transfer = OrganizationOwnershipTransfer::query()
            ->where('organization_id', $organizationId)
            ->where('to_person_id', $user->person->getKey())
            ->where('status', OwnershipTransferStatus::Pending)
            ->where('expires_at', '>', now())
            ->first();

        abort_if($transfer === null, 404);

        $this->openTransferModal($transfer);
    }

    /**
     * Accept the pending ownership transfer.
     */
    public function acceptTransfer(AcceptOwnershipTransfer $acceptOwnership): void
    {
        $transfer = $this->pendingTransferForUser();
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        $accepted = $acceptOwnership->handle($transfer, $user);

        $this->reset('showTransferModal', 'transferId', 'transferOrganizationName', 'transferOwnerName');
        unset($this->memberships);

        Flux::toast(
            variant: $accepted ? 'success' : 'danger',
            text: $accepted
                ? __('You are now the owner of :organization.', ['organization' => $transfer->organization->name])
                : __('This ownership transfer is no longer valid.'),
        );
    }

    /**
     * Decline the pending ownership transfer.
     */
    public function declineTransfer(DeclineOwnershipTransfer $declineOwnership): void
    {
        $transfer = $this->pendingTransferForUser();
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        $declined = $declineOwnership->handle($transfer, $user);

        $this->reset('showTransferModal', 'transferId', 'transferOrganizationName', 'transferOwnerName');
        unset($this->memberships);

        Flux::toast(
            variant: $declined ? 'success' : 'danger',
            text: $declined ? __('Ownership transfer declined.') : __('This ownership transfer is no longer valid.'),
        );
    }

    /**
     * Resolve the transfer currently open in the modal.
     */
    private function pendingTransferForUser(): OrganizationOwnershipTransfer
    {
        $user = Auth::user();

        abort_if($this->transferId === null || ! $user instanceof User || $user->person === null, 404);

        return OrganizationOwnershipTransfer::query()
            ->whereKey($this->transferId)
            ->where('to_person_id', $user->person->getKey())
            ->where('status', OwnershipTransferStatus::Pending)
            ->where('expires_at', '>', now())
            ->firstOrFail();
    }

    /**
     * Fill the transfer modal from the request.
     */
    private function openTransferModal(OrganizationOwnershipTransfer $transfer): void
    {
        $transfer->loadMissing(['organization', 'fromPerson']);

        $this->transferId = $transfer->getKey();
        $this->transferOrganizationName = $transfer->organization->name;
        $this->transferOwnerName = $transfer->fromPerson->name;
        $this->showTransferModal = true;
    }

    /**
     * Select an organization to leave.
     */
    public function confirmLeave(int $organizationId, string $name): void
    {
        $this->leavingOrganizationId = $organizationId;
        $this->leavingOrganizationName = $name;
        $this->showLeaveModal = true;
    }

    /**
     * Leave the selected organization.
     */
    public function leave(LeaveOrganization $leaveOrganization): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        abort_if($this->leavingOrganizationId === null || ! $user instanceof User, 404);

        $organization = $user->organizations()->firstWhere('id', $this->leavingOrganizationId);

        abort_if($organization === null, 404);

        $wasCurrent = $this->currentOrganization()?->is($organization) === true;

        if (! $leaveOrganization->handle($user, $organization)) {
            Flux::toast(variant: 'danger', text: __('You cannot leave while you are the last owner.'));

            $this->reset('showLeaveModal', 'leavingOrganizationId', 'leavingOrganizationName');

            return;
        }

        $this->reset('showLeaveModal', 'leavingOrganizationId', 'leavingOrganizationName');

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
     * @return Collection<int, array{organization: Organization, role: ?Role, isCurrent: bool, canManage: bool, isLastOwner: bool, parent: ?Organization, kind: ?SubsidiaryKind, pendingTransfer: ?OrganizationOwnershipTransfer}>
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

        $transfers = $user?->person === null
            ? collect()
            : OrganizationOwnershipTransfer::query()
                ->where('to_person_id', $user->person->getKey())
                ->where('status', OwnershipTransferStatus::Pending)
                ->where('expires_at', '>', now())
                ->get()
                ->keyBy('organization_id');

        return $organizations->map(function (Organization $organization) use ($user, $current, $parents, $transfers): array {
            $role = $user?->roleIn($organization);
            $parent = $parents->get($organization->getKey());
            $transfer = $transfers->get($organization->getKey());

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
                'pendingTransfer' => $transfer instanceof OrganizationOwnershipTransfer ? $transfer : null,
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
