<?php

declare(strict_types=1);

namespace App\Livewire\Members;

use App\Actions\Memberships\ChangeMemberRole;
use App\Enums\Role;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\Person;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class Show extends Component
{
    use HasCurrentOrganization;

    public Person $person;

    public bool $showRoleModal = false;

    public ?int $roleChangeTo = null;

    public function mount(Person $person): void
    {
        $organization = $this->requireOrganization();

        abort_unless($organization->members()->whereKey($person->getKey())->exists(), 404);

        $this->person = $person->loadMissing('user');
    }

    public function openEdit(): void
    {
        $this->dispatch('edit-member', personId: $this->person->getKey());
    }

    public function confirmPromote(): void
    {
        $this->roleChangeTo = Role::Admin->value;
        $this->showRoleModal = true;
    }

    public function confirmDemote(): void
    {
        $this->roleChangeTo = Role::Member->value;
        $this->showRoleModal = true;
    }

    public function changeRoleConfirmed(ChangeMemberRole $changeMemberRole): void
    {
        $organization = $this->requireOrganization();

        $actor = Auth::user();

        if (! \in_array($this->roleChangeTo, [Role::Admin->value, Role::Member->value], true) || ! $actor instanceof User) {
            Flux::toast(variant: 'danger', text: __('Unable to change the member role.'));
            $this->reset('showRoleModal', 'roleChangeTo');

            return;
        }

        $promoted = $this->roleChangeTo === Role::Admin->value;

        $changed = $promoted
            ? $changeMemberRole->promote($organization, $this->person, $actor)
            : $changeMemberRole->demote($organization, $this->person, $actor);

        if ($changed) {
            $this->person->refresh();

            Flux::toast(
                variant: 'success',
                text: $promoted ? __('Member promoted to Admin.') : __('Member demoted to Member.'),
            );
        } else {
            Flux::toast(
                variant: 'danger',
                text: $promoted ? __('Unable to promote the member to Admin.') : __('Unable to demote the member to Member.'),
            );
        }

        $this->reset('showRoleModal', 'roleChangeTo');
    }

    #[On('member-updated')]
    public function refreshMember(): void
    {
        $this->person->refresh();
    }

    #[Computed]
    public function roleChangeIsPromotion(): bool
    {
        return $this->roleChangeTo === Role::Admin->value;
    }

    public function render(): View
    {
        $currentRole = $this->currentRole();
        $role = $this->person->roleIn($this->requireOrganization());
        $isOwner = $currentRole?->isOwner() ?? false;
        $canManage = $currentRole?->isOwner() || $currentRole?->isAdmin();

        $joinedAt = $this->currentOrganization()
            ?->members()
            ->whereKey($this->person->getKey())
            ->first()
            ?->pivot->start_date ?? $this->person->created_at;

        /** @disregard P1013 */
        return view('livewire.members.show', [
            'organization' => $this->requireOrganization(),
            'role' => $role,
            'joinedAt' => $joinedAt,
            'canManage' => $canManage && $this->person->user_id !== Auth::id()
                && (! $role?->isOwner() || $isOwner),
            'isOwner' => $isOwner,
        ])->title(__('Member profile'));
    }
}
