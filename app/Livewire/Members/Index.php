<?php

declare(strict_types=1);

namespace App\Livewire\Members;

use App\Actions\Memberships\ChangeMemberRole;
use App\Actions\Memberships\RemoveMember;
use App\Actions\Memberships\ResendMemberVerification;
use App\Enums\Role;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\Person;
use App\Models\User;
use App\Repositories\MembershipRepository;
use App\Support\Feature\MemberDeletion;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

/**
 * @phpstan-type Member Person&object{pivot: \App\Models\OrganizationRelationship}
 *
 * @property-read ?Person $deletingPerson
 * @property-read LengthAwarePaginator<int, Member> $members
 * @property-read ?Person $roleChangePerson
 * @property-read bool $roleChangeIsPromotion
 */
class Index extends Component
{
    use HasCurrentOrganization, WithPagination;

    public bool $showDeleteModal = false;

    public bool $showBatchDeleteModal = false;

    public bool $showRoleModal = false;

    public ?int $deletingPersonId = null;

    public ?int $roleChangePersonId = null;

    public ?int $roleChangeTo = null;

    /**
     * @var array<int, int|string>
     */
    public array $selected = [];

    public int $selectionKey = 0;

    public bool $allSelected = false;

    public bool $someSelected = false;

    /**
     * @var 'asc'|'desc'
     */
    public string $sortDirection = 'desc';

    public string $search = '';

    public function render(): View
    {
        $organization = $this->currentOrganization();

        /** @disregard P1013 */
        return view('livewire.members.index', [
            'members' => $this->members,
            'organization' => $organization,
            'currentRole' => $this->currentRole(),
            'canManage' => $organization !== null && Gate::allows('manageMembers', $organization),
            'canDeleteMembers' => MemberDeletion::enabled($organization),
        ])->title(__('Members'));
    }

    /**
     * Refresh the list after a member changes.
     */
    #[On('member-updated')]
    public function refreshMembers(): void
    {
        //
    }

    /**
     * The member awaiting delete confirmation.
     */
    #[Computed]
    public function deletingPerson(): ?Person
    {
        $organization = $this->currentOrganization();

        return $this->deletingPersonId === null || $organization === null
            ? null
            : app(MembershipRepository::class)->findInOrganization($organization, $this->deletingPersonId);
    }

    /**
     * The member awaiting a role change.
     */
    #[Computed]
    public function roleChangePerson(): ?Person
    {
        $organization = $this->currentOrganization();

        return $this->roleChangePersonId === null || $organization === null
            ? null
            : app(MembershipRepository::class)->findInOrganization($organization, $this->roleChangePersonId);
    }

    /**
     * Whether the pending role change promotes the member to Admin.
     */
    #[Computed]
    public function roleChangeIsPromotion(): bool
    {
        return $this->roleChangeTo === Role::Admin->value;
    }

    /**
     * @return LengthAwarePaginator<int, Member>
     */
    #[Computed]
    public function members(): LengthAwarePaginator
    {
        $organization = $this->currentOrganization();

        if ($organization === null) {
            return new LengthAwarePaginator([], 0, 10);
        }

        return app(MembershipRepository::class)->get($organization, $this->search, $this->sortDirection);
    }

    /**
     * Open the form for a new member.
     */
    public function createMember(): void
    {
        $this->authorize('manageMembers', $this->requireOrganization());

        $this->dispatch('create-member');
    }

    /**
     * Open the delete confirmation for the given member.
     */
    public function confirmDelete(int $personId): void
    {
        $this->deletingPersonId = $personId;
        $this->showDeleteModal = true;
    }

    /**
     * Open the bulk delete confirmation for the selected members.
     */
    public function confirmDeleteSelected(): void
    {
        $this->showBatchDeleteModal = true;
    }

    /**
     * Delete the selected members and close the bulk confirmation.
     */
    public function deleteSelectedConfirmed(RemoveMember $removeMember): void
    {
        $this->deleteSelected($removeMember);

        $this->reset('showBatchDeleteModal');
    }

    /**
     * Open the form for the given member.
     */
    public function openEdit(int $personId): void
    {
        $this->dispatch('edit-member', personId: $personId);
    }

    /**
     * Resend the verification email to the given member.
     */
    public function resendVerification(int $personId, ResendMemberVerification $resendVerification): void
    {
        $organization = $this->requireOrganization();

        $person = app(MembershipRepository::class)->findInOrganization($organization, $personId, ['user']);

        abort_if($person === null, 404);

        $this->authorize('manageMember', [$person, $organization]);

        $person = $resendVerification->handle($organization, $personId);

        Flux::toast(
            variant: 'success',
            text: __('Verification email resent to :email.', ['email' => $person->user?->email]),
        );
    }

    /**
     * Open the confirmation to promote the given member to Admin.
     */
    public function confirmPromote(int $personId): void
    {
        $this->roleChangePersonId = $personId;
        $this->roleChangeTo = Role::Admin->value;
        $this->showRoleModal = true;
    }

    /**
     * Open the confirmation to demote the given member to Member.
     */
    public function confirmDemote(int $personId): void
    {
        $this->roleChangePersonId = $personId;
        $this->roleChangeTo = Role::Member->value;
        $this->showRoleModal = true;
    }

    /**
     * Apply the confirmed role change.
     */
    public function changeRoleConfirmed(ChangeMemberRole $changeMemberRole): void
    {
        $organization = $this->requireOrganization();

        $person = $this->roleChangePerson;

        /** @var User $actor */
        $actor = Auth::user();

        if ($person === null) {
            Flux::toast(variant: 'danger', text: __('Unable to change the member role.'));
            $this->reset('showRoleModal', 'roleChangePersonId', 'roleChangeTo');

            return;
        }

        $promoted = $this->roleChangeTo === Role::Admin->value;

        $changed = $promoted
            ? $changeMemberRole->promote($organization, $person, $actor)
            : $changeMemberRole->demote($organization, $person, $actor);

        Flux::toast(
            variant: $changed ? 'success' : 'danger',
            text: $changed
                ? ($promoted ? __('Member promoted to Admin.') : __('Member demoted to Member.'))
                : ($promoted ? __('Unable to promote the member to Admin.') : __('Unable to demote the member to Member.')),
        );

        $this->reset('showRoleModal', 'roleChangePersonId', 'roleChangeTo');
    }

    /**
     * Remove the member awaiting confirmation.
     */
    public function deleteConfirmed(RemoveMember $removeMember): void
    {
        $person = $this->deletingPerson;

        abort_if($person === null, 404);

        $this->removeMember($person->getKey(), $removeMember);

        $this->reset('showDeleteModal', 'deletingPersonId');
    }

    /**
     * Remove the given person from the current organization.
     */
    public function removeMember(int $personId, RemoveMember $removeMember): void
    {
        $organization = $this->requireOrganization();

        $person = app(MembershipRepository::class)->findInOrganization($organization, $personId);

        abort_if($person === null, 404);

        $this->authorize('removeMember', [$person, $organization]);

        /** @var User $actor */
        $actor = Auth::user();

        if (! $removeMember->handle($organization, $person, $actor)) {
            Flux::toast(variant: 'danger', text: __('Failed to remove the member.'));

            return;
        }

        $this->resetPage();

        Flux::toast(variant: 'success', text: __('Member removed.'));
    }

    /**
     * Clear the selection when the page changes.
     */
    public function updatedPage(): void
    {
        $this->selected = [];
        $this->allSelected = false;
        $this->someSelected = false;
    }

    /**
     * Reset paging and selection after the search term changes.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->updatedPage();
        $this->selectionKey++;
    }

    /**
     * Recompute whether all or some members on the page are selected.
     */
    public function updatedSelected(): void
    {
        $pageIds = $this->selectablePageIds();
        $selected = array_map('intval', $this->selected);

        $this->allSelected = $pageIds !== [] && array_diff($pageIds, $selected) === [];
        $this->someSelected = ! $this->allSelected && array_intersect($pageIds, $selected) !== [];
    }

    /**
     * Select or deselect every removable member on the page.
     */
    public function toggleSelectAll(): void
    {
        $pageIds = $this->selectablePageIds();
        $selected = array_map('intval', $this->selected);

        $this->selected = array_diff($pageIds, $selected) === [] ? [] : $pageIds;
        $this->selectionKey++;
        $this->updatedSelected();
    }

    /**
     * Toggle the join-date sort direction.
     */
    public function sortByJoinedAt(): void
    {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->resetPage();
    }

    /**
     * Remove the selected members from the current organization.
     */
    public function deleteSelected(RemoveMember $removeMember): void
    {
        $organization = $this->requireOrganization();

        $this->authorize('manageMembers', $organization);

        abort_unless(MemberDeletion::enabled($organization), 403);

        /** @var User $actor */
        $actor = Auth::user();

        $pageIds = $this->members->pluck('id')->all();
        $selected = array_values(array_intersect(array_map('intval', $this->selected), $pageIds));

        $persons = app(MembershipRepository::class)->getInOrganization($organization, $selected);

        foreach ($persons as $person) {
            $this->authorize('removeMember', [$person, $organization]);
        }

        $removed = null;

        try {
            $removed = $removeMember->handleMany($organization, $persons, $actor);
        } catch (Throwable $exception) {
            report($exception);
        }

        if ($removed === null || $removed->isEmpty()) {
            Flux::toast(variant: 'danger', text: __('Failed to remove the selected members.'));

            return;
        }

        $this->reset('selected', 'allSelected', 'someSelected');
        $this->selectionKey++;
        $this->resetPage();
        unset($this->members);

        Flux::toast(
            variant: 'success',
            text: __(':count members removed successfully.', ['count' => $removed->count()]),
        );
    }

    /**
     * @return array<int, int>
     */
    private function selectablePageIds(): array
    {
        $organization = $this->currentOrganization();

        if ($organization === null) {
            return [];
        }

        return $this->members
            ->filter(fn (Person $person): bool => Gate::allows('removeMember', [$person, $organization]))
            ->pluck('id')
            ->all();
    }
}
