<?php

declare(strict_types=1);

namespace App\Livewire\Members;

use App\Enums\ContactDetailType;
use App\Enums\Role;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\OrganizationRelationshipPeriod;
use App\Models\Person;
use App\Repositories\MembershipRepository;
use App\Repositories\RelationshipRepository;
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
 */
class View extends Component
{
    use HasCurrentOrganization;

    #[Locked]
    public Person $person;

    public function mount(Person $person): void
    {
        $organization = $this->requireOrganization();

        $member = app(MembershipRepository::class)->findInOrganization($organization, $person->getKey(), ['user']);

        abort_if($member === null, 404);

        $this->person = $member;
    }

    public function render(): ViewContract
    {
        $organization = $this->requireOrganization();

        // mount() only runs once; a later request must not render a removed member.
        $member = $this->person->relationLoaded('pivot')
            ? $this->person
            : app(MembershipRepository::class)->findInOrganization($organization, $this->person->getKey());

        abort_if($member === null, 404);

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

    /**
     * Reload the member after an edit.
     */
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
        return app(RelationshipRepository::class)->getForMember($this->requireOrganization(), $this->person);
    }

    /**
     * Open the edit form for this member.
     */
    public function openEdit(): void
    {
        $this->dispatch('edit-member', personId: $this->person->getKey());
    }

    /**
     * Open the confirmation to promote this member to Admin.
     */
    public function confirmPromote(): void
    {
        $this->dispatch('open-role-modal', personId: $this->person->getKey(), role: Role::Admin->value);
    }

    /**
     * Open the confirmation to demote this member to Member.
     */
    public function confirmDemote(): void
    {
        $this->dispatch('open-role-modal', personId: $this->person->getKey(), role: Role::Member->value);
    }
}
