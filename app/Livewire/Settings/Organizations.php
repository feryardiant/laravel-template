<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Actions\Memberships\SetDefaultOrganization;
use App\Enums\Role;
use App\Enums\SubsidiaryKind;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\ApprovalRequest;
use App\Models\Organization;
use App\Models\User;
use App\Repositories\OrganizationRepository;
use App\Support\CurrentOrganization;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * @property-read Collection<int, array{organization: Organization, role: ?Role, isCurrent: bool, canManage: bool, isLastOwner: bool, isDefault: bool, parent: ?Organization, kind: ?SubsidiaryKind, pendingApproval: ?ApprovalRequest, pendingRequest: ?ApprovalRequest}> $memberships
 */
class Organizations extends Component
{
    use HasCurrentOrganization;

    public function render(): View
    {
        /** @disregard P1013 */
        return view('livewire.settings.organizations')->title(__('Organizations'));
    }

    /**
     * Invalidate the cached membership list after an organization change.
     */
    #[On('organization-created')]
    #[On('memberships-changed')]
    public function refreshMemberships(): void
    {
        unset($this->memberships);
    }

    /**
     * The organizations the user belongs to, with their role and options.
     *
     * @return Collection<int, array{organization: Organization, role: ?Role, isCurrent: bool, canManage: bool, isLastOwner: bool, isDefault: bool, parent: ?Organization, kind: ?SubsidiaryKind, pendingApproval: ?ApprovalRequest, pendingRequest: ?ApprovalRequest}>
     */
    #[Computed]
    public function memberships(): Collection
    {
        /** @var User $user */
        $user = Auth::user();

        return app(OrganizationRepository::class)->getUsers($user, $this->currentOrganization());
    }

    /**
     * Switch to the organization and open its settings.
     */
    public function manage(int $organizationId): void
    {
        $organization = app(OrganizationRepository::class)->find($organizationId);

        $this->authorize('update', $organization);

        abort_unless(app(CurrentOrganization::class)->switch($organization), 403);

        $this->redirect(route('org-settings.general'), navigate: true);
    }

    /**
     * Make the given organization the user's default workspace.
     */
    public function setDefault(int $organizationId, SetDefaultOrganization $setDefault): void
    {
        /** @var User $user */
        $user = Auth::user();

        $organization = app(OrganizationRepository::class)->findUser($user, $organizationId);

        abort_if($organization === null, 404);

        abort_unless($setDefault->handle($user, $organization), 404);

        unset($this->memberships);

        Flux::toast(variant: 'success', text: __('Default organization updated.'));
    }
}
