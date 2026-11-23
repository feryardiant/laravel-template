<?php

declare(strict_types=1);

namespace App\Livewire\Members;

use App\Actions\Memberships\ChangeMemberRole;
use App\Actions\Memberships\FindMember;
use App\Actions\Relationships\ListRelationshipPeriods;
use App\Enums\ContactDetailType;
use App\Enums\Role;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\OrganizationRelationshipPeriod;
use App\Models\Person;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * @property-read Collection<int, OrganizationRelationshipPeriod> $history
 * @property-read bool $roleChangeIsPromotion
 */
class View extends Component
{
    use HasCurrentOrganization;

    #[Locked]
    public Person $person;

    public bool $showRoleModal = false;

    public ?int $roleChangeTo = null;

    public function mount(Person $person): void
    {
        $organization = $this->requireOrganization();

        $this->person = app(FindMember::class)->inOrganization($organization, $person->getKey(), ['user']);
    }

    public function render(): ViewContract
    {
        $organization = $this->requireOrganization();

        // mount() only runs once; a later request must not render a removed member.
        $member = app(FindMember::class)->inOrganization($organization, $this->person->getKey());

        $role = $member->roleIn($organization);
        $canManage = Gate::allows('manageMember', [$member, $organization])
            && $member->user_id !== Auth::id();

        $joinedAt = $member->pivot->start_date ?? $member->created_at;

        /** @disregard P1013 */
        return view('livewire.members.view', [
            'person' => $member,
            'organization' => $organization,
            'role' => $role,
            'joinedAt' => $joinedAt,
            'contactEmail' => $member->contactDetailValue(ContactDetailType::Email),
            'contactPhone' => $member->contactDetailValue(ContactDetailType::Phone),
            'canManage' => $canManage,
        ])->title(__('Member profile'));
    }

    #[On('member-updated')]
    public function refreshMember(): void
    {
        $this->person->refresh();
    }

    /**
     * The membership's full stint history.
     *
     * @return Collection<int, OrganizationRelationshipPeriod>
     */
    #[Computed]
    public function history(): Collection
    {
        return app(ListRelationshipPeriods::class)->forMember($this->requireOrganization(), $this->person);
    }

    #[Computed]
    public function roleChangeIsPromotion(): bool
    {
        return $this->roleChangeTo === Role::Admin->value;
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
}
