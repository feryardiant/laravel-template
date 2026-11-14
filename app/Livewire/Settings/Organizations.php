<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Actions\Memberships\LeaveOrganization;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Enums\SubsidiaryKind;
use App\Jobs\DeleteOrganization;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\Organization;
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
     * @return Collection<int, array{organization: Organization, role: ?Role, isCurrent: bool, canManage: bool, isLastOwner: bool, parent: ?Organization, kind: ?SubsidiaryKind}>
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

        return $organizations->map(function (Organization $organization) use ($user, $current, $parents): array {
            $role = $user?->roleIn($organization);
            $parent = $parents->get($organization->getKey());

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
