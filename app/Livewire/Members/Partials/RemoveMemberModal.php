<?php

declare(strict_types=1);

namespace App\Livewire\Members\Partials;

use App\Actions\Memberships\RemoveMember;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\User;
use App\Repositories\MembershipRepository;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Confirms and removes one member from the current organization.
 */
class RemoveMemberModal extends Component
{
    use HasCurrentOrganization;

    public bool $showModal = false;

    public ?int $personId = null;

    public ?string $personName = null;

    public function render(): View
    {
        return view('livewire.members.partials.remove-member-modal');
    }

    /**
     * Open the confirmation for the given member.
     */
    #[On('remove-member')]
    public function confirm(int $personId): void
    {
        $person = app(MembershipRepository::class)->findInOrganization($this->requireOrganization(), $personId);

        $this->personId = $personId;
        $this->personName = $person?->name;
        $this->showModal = true;
    }

    /**
     * Remove the member awaiting confirmation.
     */
    public function delete(RemoveMember $removeMember): void
    {
        $organization = $this->requireOrganization();

        abort_if($this->personId === null, 404);

        $person = app(MembershipRepository::class)->findInOrganization($organization, $this->personId);

        abort_if($person === null, 404);

        $this->authorize('removeMember', [$person, $organization]);

        /** @var User $actor */
        $actor = Auth::user();

        if (! $removeMember->handle($organization, $person, $actor)) {
            Flux::toast(variant: 'danger', text: __('Failed to remove the member.'));

            $this->close();

            return;
        }

        $this->close();

        Flux::toast(variant: 'success', text: __('Member removed.'));

        $this->dispatch('member-removed');
    }

    /**
     * Close the confirmation.
     */
    public function close(): void
    {
        $this->reset('showModal', 'personId', 'personName');
    }
}
