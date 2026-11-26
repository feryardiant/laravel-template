<?php

declare(strict_types=1);

namespace App\Livewire\Members\Partials;

use App\Actions\Memberships\RemoveMember;
use App\Enums\Feature;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\User;
use App\Repositories\MembershipRepository;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * Confirms and removes the selected members from the current organization.
 */
class RemoveMembersModal extends Component
{
    use HasCurrentOrganization;

    public bool $showModal = false;

    /**
     * @var array<int, int>
     */
    public array $ids = [];

    public function render(): View
    {
        return view('livewire.members.partials.remove-members-modal');
    }

    /**
     * Open the confirmation for the given members.
     *
     * @param  array<int, int>  $ids
     */
    #[On('remove-members')]
    public function confirm(array $ids): void
    {
        $this->ids = array_values(array_map('intval', $ids));
        $this->showModal = true;
    }

    /**
     * Remove the selected members.
     */
    public function delete(RemoveMember $removeMember): void
    {
        $organization = $this->requireOrganization();

        $this->authorize('manageMembers', $organization);

        abort_unless(Feature::MemberDeletion->enabled($organization), 403);

        /** @var User $actor */
        $actor = Auth::user();

        $persons = app(MembershipRepository::class)->getInOrganization($organization, $this->ids);

        foreach ($persons as $person) {
            $this->authorize('removeMember', [$person, $organization]);
        }

        $removed = null;

        try {
            $removed = $removeMember->handleMany($organization, $persons, $actor);
        } catch (Throwable $exception) {
            report($exception);
        }

        $this->close();

        if ($removed === null || $removed->isEmpty()) {
            Flux::toast(variant: 'danger', text: __('Failed to remove the selected members.'));

            return;
        }

        Flux::toast(
            variant: 'success',
            text: __(':count members removed successfully.', ['count' => $removed->count()]),
        );

        $this->dispatch('member-removed');
    }

    /**
     * Close the confirmation.
     */
    public function close(): void
    {
        $this->reset('showModal', 'ids');
    }
}
