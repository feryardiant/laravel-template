<?php

declare(strict_types=1);

namespace App\Livewire\Members;

use App\Actions\FileStorage;
use App\Actions\Memberships\ChangeMemberRole;
use App\Actions\Memberships\CreateMember;
use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\Person;
use App\Models\User;
use App\Notifications\VerifyEmailForNewUser;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Throwable;

/**
 * @phpstan-type Member Person&object{pivot: \App\Models\OrganizationRelationship}
 *
 * @property-read LengthAwarePaginator<int, Member> $members
 */
class Index extends Component
{
    use HasCurrentOrganization, ProfileValidationRules, WithFileUploads, WithPagination;

    public bool $showCreateModal = false;

    public bool $showDeleteModal = false;

    public bool $showBatchDeleteModal = false;

    public bool $showRoleModal = false;

    public ?int $deletingPersonId = null;

    public ?int $roleChangePersonId = null;

    public ?int $roleChangeTo = null;

    public string $name = '';

    public string $email = '';

    public bool $createCredential = false;

    public int $role = Role::Member->value;

    /**
     * @var mixed
     */
    public $photo = null;

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

    /**
     * Add a person to the current organization.
     */
    public function createMember(CreateMember $createMember, FileStorage $fileStorage): void
    {
        $organization = $this->requireOrganization();

        $this->authorizeManager();

        $this->email = Str::lower($this->email);

        $this->validate([
            'photo' => ['nullable', 'image', 'max:2048'],
        ]);

        $validated = $this->validate([
            'name' => $this->nameRules(),
            'email' => $this->createCredential
                ? $this->emailRules()
                : ['nullable', 'string', 'email', 'max:255'],
            'role' => ['required', Rule::enum(Role::class)],
        ]);

        $role = Role::from((int) $validated['role']);
        $this->authorizeRole($role);

        $hasCredential = $this->createCredential;
        $photoUrl = $this->photo instanceof UploadedFile
            ? $fileStorage->handle($this->photo, 'photos')
            : null;

        $createMember->handle(
            $organization,
            $validated['name'],
            $role,
            $hasCredential ? $validated['email'] : null,
            $photoUrl,
        );

        $this->reset('name', 'email', 'createCredential', 'photo', 'showCreateModal');
        $this->role = Role::Member->value;
        $this->resetPage();

        Flux::toast(
            variant: 'success',
            text: $hasCredential
                ? __('Member added. A verification email has been sent.')
                : __('Member added.'),
        );
    }

    /**
     * Clear the pending member photo.
     */
    public function clearPhoto(): void
    {
        $this->photo = null;
    }

    public function confirmDelete(int $personId): void
    {
        $this->deletingPersonId = $personId;
        $this->showDeleteModal = true;
    }

    public function confirmDeleteSelected(): void
    {
        $this->showBatchDeleteModal = true;
    }

    public function deleteSelectedConfirmed(): void
    {
        $this->deleteSelected();

        $this->reset('showBatchDeleteModal');
    }

    public function openEdit(int $personId): void
    {
        $this->dispatch('edit-member', personId: $personId);
    }

    /**
     * Resend the verification email to the given member.
     */
    public function resendVerification(int $personId): void
    {
        $organization = $this->requireOrganization();

        $this->authorizeManager();

        $person = $organization->members()->whereKey($personId)->firstOrFail();

        $this->authorizeTarget($person);

        abort_if($person->user === null || $person->user->hasVerifiedEmail(), 422);

        $person->user->notify(new VerifyEmailForNewUser);

        Flux::toast(
            variant: 'success',
            text: __('Verification email resent to :email.', ['email' => $person->user->email]),
        );
    }

    public function confirmPromote(int $personId): void
    {
        $this->roleChangePersonId = $personId;
        $this->roleChangeTo = Role::Admin->value;
        $this->showRoleModal = true;
    }

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

        $person = $this->roleChangePerson();

        $actor = Auth::user();

        if ($person === null || ! $actor instanceof User) {
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
    public function deleteConfirmed(): void
    {
        $person = $this->deletingPerson();

        abort_if($person === null, 404);

        $this->removeMember($person->getKey());

        $this->reset('showDeleteModal', 'deletingPersonId');
    }

    /**
     * Remove the given person from the current organization.
     */
    public function removeMember(int $personId): void
    {
        $organization = $this->requireOrganization();

        $this->authorizeManager();

        $person = $organization->members()->whereKey($personId)->firstOrFail();

        abort_if($person->user_id === Auth::id(), 403);

        $this->authorizeTarget($person);

        $organization->members()->detach($person->getKey());
        $this->resetPage();

        Flux::toast(variant: 'success', text: __('Member removed.'));
    }

    /**
     * @return array<int, Role>
     */
    #[Computed]
    public function roles(): array
    {
        return $this->currentRole()?->isOwner()
            ? Role::cases()
            : [Role::Admin, Role::Member];
    }

    #[Computed]
    public function deletingPerson(): ?Person
    {
        return $this->deletingPersonId === null
            ? null
            : $this->currentOrganization()?->members()->whereKey($this->deletingPersonId)->first();
    }

    #[Computed]
    public function roleChangePerson(): ?Person
    {
        return $this->roleChangePersonId === null
            ? null
            : $this->currentOrganization()?->members()->whereKey($this->roleChangePersonId)->first();
    }

    #[Computed]
    public function roleChangeIsPromotion(): bool
    {
        return $this->roleChangeTo === Role::Admin->value;
    }

    #[On('member-updated')]
    public function refreshMembers(): void
    {
        //
    }

    public function updatedPage(): void
    {
        $this->selected = [];
        $this->allSelected = false;
        $this->someSelected = false;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->updatedPage();
        $this->selectionKey++;
    }

    public function updatedSelected(): void
    {
        $pageIds = $this->selectablePageIds();
        $selected = array_map('intval', $this->selected);

        $this->allSelected = $pageIds !== [] && array_diff($pageIds, $selected) === [];
        $this->someSelected = ! $this->allSelected && array_intersect($pageIds, $selected) !== [];
    }

    public function toggleSelectAll(): void
    {
        $pageIds = $this->selectablePageIds();
        $selected = array_map('intval', $this->selected);

        $this->selected = array_diff($pageIds, $selected) === [] ? [] : $pageIds;
        $this->selectionKey++;
        $this->updatedSelected();
    }

    public function sortByJoinedAt(): void
    {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->resetPage();
    }

    /**
     * Remove the selected members from the current organization.
     */
    public function deleteSelected(): void
    {
        $organization = $this->requireOrganization();

        $this->authorizeManager();

        $pageIds = $this->members->pluck('id')->all();
        $selected = array_values(array_intersect(array_map('intval', $this->selected), $pageIds));

        $persons = $organization->members()->whereKey($selected)->get();

        foreach ($persons as $person) {
            abort_if($person->user_id === Auth::id(), 403);

            $this->authorizeTarget($person);
        }

        $removed = false;

        try {
            if ($persons->isNotEmpty()) {
                DB::transaction(fn () => $organization->members()->detach($persons->pluck('id')->all()));

                $removed = true;
            }
        } catch (Throwable $exception) {
            report($exception);

            $removed = false;
        }

        if (! $removed) {
            Flux::toast(variant: 'danger', text: __('Failed to remove the selected members.'));

            return;
        }

        $this->reset('selected', 'allSelected', 'someSelected');
        $this->selectionKey++;
        $this->resetPage();
        unset($this->members);

        Flux::toast(
            variant: 'success',
            text: __(':count members removed successfully.', ['count' => $persons->count()]),
        );
    }

    public function render(): View
    {
        $currentRole = $this->currentRole();

        /** @disregard P1013 */
        return view('livewire.members.index', [
            'members' => $this->members,
            'organization' => $this->currentOrganization(),
            'currentRole' => $currentRole,
            'canManage' => $currentRole?->isOwner() || $currentRole?->isAdmin(),
        ])->title(__('Members'));
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

        $term = trim($this->search);

        return $organization->members()
            ->with('user')
            ->when($term !== '', fn (Builder $query): Builder => $query->where(function (Builder $query) use ($term): void {
                $query->whereLike('people.name', "%{$term}%")
                    ->orWhereHas('user', fn (Builder $query): Builder => $query->whereLike('users.email', "%{$term}%"));
            }))
            ->orderBy('organization_relationships.start_date', $this->sortDirection)
            ->orderBy('people.id')
            ->paginate(10);
    }

    /**
     * @return array<int, int>
     */
    private function selectablePageIds(): array
    {
        $role = $this->currentRole();

        if (! $role?->isOwner() && ! $role?->isAdmin()) {
            return [];
        }

        return $this->members
            ->filter(fn (Person $person): bool => $person->user_id !== Auth::id()
                && (! $person->pivot->role?->isOwner() || $role->isOwner()))
            ->pluck('id')
            ->all();
    }
}
