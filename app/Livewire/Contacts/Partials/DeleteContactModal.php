<?php

declare(strict_types=1);

namespace App\Livewire\Contacts\Partials;

use App\Actions\Contacts\DeleteContact;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Repositories\ContactRepository;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * Confirms and deletes one contact.
 */
class DeleteContactModal extends Component
{
    use HasCurrentOrganization;

    public bool $showModal = false;

    public ?int $contactId = null;

    public ?string $contactName = null;

    public function render(): View
    {
        return view('livewire.contacts.partials.delete-contact-modal');
    }

    /**
     * Open the confirmation for the given contact.
     */
    #[On('delete-contact')]
    public function confirm(int $contactId): void
    {
        $contact = app(ContactRepository::class)->findById($this->requireOrganization(), $contactId);

        $this->contactId = $contact->getKey();
        $this->contactName = $contact->stakeholder->name;
        $this->showModal = true;
    }

    /**
     * Delete the contact awaiting confirmation.
     */
    public function delete(DeleteContact $deleteContact): void
    {
        $organization = $this->requireOrganization();

        abort_if($this->contactId === null, 404);

        $contact = app(ContactRepository::class)->findById($organization, $this->contactId);

        Gate::authorize('deleteContact', [$contact->stakeholder, $organization]);

        try {
            $deleteContact->handle($organization, $contact->getKey(), actor: Auth::user());
        } catch (Throwable $exception) {
            report($exception);

            Flux::toast(variant: 'danger', text: __('Failed to delete the contact.'));

            $this->close();

            return;
        }

        $this->close();

        Flux::toast(variant: 'success', text: __('Contact deleted.'));

        $this->dispatch('contact-deleted');
    }

    /**
     * Close the confirmation.
     */
    public function close(): void
    {
        $this->reset('showModal', 'contactId', 'contactName');
    }
}
