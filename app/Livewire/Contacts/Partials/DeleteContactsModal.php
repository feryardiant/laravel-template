<?php

declare(strict_types=1);

namespace App\Livewire\Contacts\Partials;

use App\Actions\Contacts\DeleteContact;
use App\Livewire\Concerns\HasCurrentOrganization;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * Confirms and deletes the selected contacts.
 */
class DeleteContactsModal extends Component
{
    use HasCurrentOrganization;

    public bool $showModal = false;

    /**
     * @var array<int, int>
     */
    public array $ids = [];

    public function render(): View
    {
        return view('livewire.contacts.partials.delete-contacts-modal');
    }

    /**
     * Open the confirmation for the given contact relationships.
     *
     * @param  array<int, int>  $ids
     */
    #[On('delete-contacts')]
    public function confirm(array $ids): void
    {
        Gate::authorize('deleteAnyContact', $this->requireOrganization());

        $this->ids = array_values(array_map('intval', $ids));
        $this->showModal = true;
    }

    /**
     * Delete the selected contacts.
     */
    public function delete(DeleteContact $deleteContacts): void
    {
        $organization = $this->requireOrganization();

        Gate::authorize('deleteAnyContact', $organization);

        if ($this->ids === []) {
            $this->close();

            return;
        }

        try {
            $count = $deleteContacts->handleBulk($organization, $this->ids, Auth::user());
        } catch (Throwable $exception) {
            report($exception);

            Flux::toast(variant: 'danger', text: __('Failed to delete the selected contacts.'));

            $this->close();

            return;
        }

        $this->close();

        Flux::toast(
            variant: 'success',
            text: __(':count contacts deleted successfully.', ['count' => $count]),
        );

        $this->dispatch('contact-deleted');
    }

    /**
     * Close the confirmation.
     */
    public function close(): void
    {
        $this->reset('showModal', 'ids');
    }
}
