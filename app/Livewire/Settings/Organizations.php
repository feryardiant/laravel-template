<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Actions\Memberships\SetDefaultOrganization;
use App\Enums\OrganizationRelationshipType;
use App\Enums\Role;
use App\Enums\SubsidiaryKind;
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
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class Organizations extends Component
{
    use HasCurrentOrganization;

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
     * Make the given organization the user's default workspace.
     */
    public function setDefault(int $organizationId, SetDefaultOrganization $setDefault): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        $organization = $user->organizations()->firstWhere('id', $organizationId);

        abort_if($organization === null, 404);
        abort_unless($setDefault->handle($user, $organization), 404);

        unset($this->memberships);

        Flux::toast(variant: 'success', text: __('Default organization updated.'));
    }

    /**
     * The organizations the user belongs to, with their role and options.
     *
     * @return Collection<int, array{organization: Organization, role: ?Role, isCurrent: bool, canManage: bool, isLastOwner: bool, isDefault: bool, parent: ?Organization, kind: ?SubsidiaryKind, pendingApproval: ?ApprovalRequest, pendingRequest: ?ApprovalRequest}>
     */
    #[Computed]
    public function memberships(): Collection
    {
        /** @var User|null $user */
        $user = Auth::user();
        $current = $this->currentOrganization();
        $defaultId = $user?->defaultOrganization()?->getKey();

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

        return $organizations->map(function (Organization $organization) use ($user, $current, $defaultId, $parents, $asApprover, $asRequester): array {
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
                'isDefault' => $defaultId !== null && $defaultId === $organization->getKey(),
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
    #[On('memberships-changed')]
    public function refreshMemberships(): void
    {
        unset($this->memberships);
    }

    public function render(): View
    {
        /** @disregard P1013 */
        return view('livewire.settings.organizations')->title(__('Organizations'));
    }
}
