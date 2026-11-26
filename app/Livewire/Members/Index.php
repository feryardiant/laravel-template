<?php

declare(strict_types=1);

namespace App\Livewire\Members;

use App\Actions\Memberships\ResendMemberVerification;
use App\Enums\Feature;
use App\Enums\Role;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\Person;
use App\Repositories\MembershipRepository;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * @phpstan-type Member Person&object{pivot: \App\Models\OrganizationRelationship}
 *
 * @property-read LengthAwarePaginator<int, Member> $members
 */
class Index extends Component
{
    use HasCurrentOrganization, WithPagination;

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
            'canDeleteMembers' => Feature::MemberDeletion->enabled($organization),
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
     * Refresh the list after a member is removed.
     */
    #[On('member-removed')]
    public function refreshAfterRemoval(): void
    {
        $this->selected = [];
        $this->allSelected = false;
        $this->someSelected = false;

        $this->resetPage();

        unset($this->members);
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
        $this->dispatch('remove-member', personId: $personId);
    }

    /**
     * Open the bulk delete confirmation for the selected members.
     */
    public function confirmDeleteSelected(): void
    {
        $this->requireOrganization();

        $pageIds = $this->members->pluck('id')->all();
        $ids = array_values(array_intersect(array_map('intval', $this->selected), $pageIds));

        $this->dispatch('remove-members', ids: $ids);
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
        $this->dispatch('open-role-modal', personId: $personId, role: Role::Admin->value);
    }

    /**
     * Open the confirmation to demote the given member to Member.
     */
    public function confirmDemote(int $personId): void
    {
        $this->dispatch('open-role-modal', personId: $personId, role: Role::Member->value);
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
