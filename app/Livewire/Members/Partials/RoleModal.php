<?php

declare(strict_types=1);

namespace App\Livewire\Members\Partials;

use App\Actions\Memberships\ChangeMemberRole;
use App\Enums\Role;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\User;
use App\Repositories\MembershipRepository;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Confirms a member role change in the current organization.
 *
 * @property-read bool $isPromotion
 */
class RoleModal extends Component
{
    use HasCurrentOrganization;

    public bool $showModal = false;

    public ?int $personId = null;

    public ?string $personName = null;

    public ?int $roleTo = null;

    public function render(): View
    {
        return view('livewire.members.partials.role-modal');
    }

    /**
     * Open the confirmation for the given member and target role.
     */
    #[On('open-role-modal')]
    public function confirm(int $personId, int $role): void
    {
        if (! \in_array($role, [Role::Admin->value, Role::Member->value], true)) {
            return;
        }

        $person = app(MembershipRepository::class)->findInOrganization($this->requireOrganization(), $personId);

        if ($person === null) {
            return;
        }

        $this->personId = $person->getKey();
        $this->personName = $person->name;
        $this->roleTo = $role;
        $this->showModal = true;
    }

    /**
     * Whether the pending change promotes the member to Admin.
     */
    #[Computed]
    public function isPromotion(): bool
    {
        return $this->roleTo === Role::Admin->value;
    }

    /**
     * Apply the confirmed role change.
     */
    public function change(ChangeMemberRole $changeMemberRole): void
    {
        $organization = $this->requireOrganization();

        if ($this->personId === null || ! \in_array($this->roleTo, [Role::Admin->value, Role::Member->value], true)) {
            Flux::toast(variant: 'danger', text: __('Unable to change the member role.'));

            $this->close();

            return;
        }

        $person = app(MembershipRepository::class)->findInOrganization($organization, $this->personId);

        if ($person === null) {
            Flux::toast(variant: 'danger', text: __('Unable to change the member role.'));

            $this->close();

            return;
        }

        /** @var User $actor */
        $actor = Auth::user();

        $promoted = $this->isPromotion;

        $changed = $promoted
            ? $changeMemberRole->promote($organization, $person, $actor)
            : $changeMemberRole->demote($organization, $person, $actor);

        Flux::toast(
            variant: $changed ? 'success' : 'danger',
            text: $changed
                ? ($promoted ? __('Member promoted to Admin.') : __('Member demoted to Member.'))
                : ($promoted ? __('Unable to promote the member to Admin.') : __('Unable to demote the member to Member.')),
        );

        $this->close();

        if ($changed) {
            $this->dispatch('member-updated');
        }
    }

    /**
     * Close the confirmation.
     */
    public function close(): void
    {
        $this->reset('showModal', 'personId', 'personName', 'roleTo');
    }
}
