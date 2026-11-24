<?php

declare(strict_types=1);

namespace App\Livewire\Contacts;

use App\Actions\Contacts\DeleteContact;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\OrganizationRelationship;
use App\Repositories\ContactRepository;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

/**
 * @property-read LengthAwarePaginator<int, OrganizationRelationship> $contacts
 * @property-read bool $canManage
 * @property-read bool $canDeleteAny
 */
class Index extends Component
{
    use HasCurrentOrganization, WithPagination;

    public bool $showDeleteModal = false;

    public bool $showBatchDeleteModal = false;

    public ?int $deletingContactId = null;

    public ?string $deletingContactName = null;

    /**
     * @var array<int, int|string>
     */
    public array $selected = [];

    public int $selectionKey = 0;

    public bool $allSelected = false;

    public bool $someSelected = false;

    public string $search = '';

    public function render(): View
    {
        /** @disregard P1013 */
        return view('livewire.contacts.index', [
            'contacts' => $this->contacts,
            'organization' => $this->currentOrganization(),
            'canManage' => $this->canManage,
            'canDeleteAny' => $this->canDeleteAny,
        ])->title(__('Contacts'));
    }

    /**
     * Reload the contact list and drop the current selection.
     */
    #[On('contact-updated')]
    public function refreshContacts(): void
    {
        $this->clearSelection();
        $this->selectionKey++;
        unset($this->contacts);
    }

    /**
     * Whether the user may add contacts to the current organization.
     */
    #[Computed]
    public function canManage(): bool
    {
        $organization = $this->currentOrganization();

        return $organization !== null && Gate::allows('createContact', $organization);
    }

    /**
     * Whether the user may delete any contact in the current organization.
     */
    #[Computed]
    public function canDeleteAny(): bool
    {
        $organization = $this->currentOrganization();

        return $organization !== null && Gate::allows('deleteAnyContact', $organization);
    }

    /**
     * @return LengthAwarePaginator<int, OrganizationRelationship>
     */
    #[Computed]
    public function contacts(): LengthAwarePaginator
    {
        $organization = $this->currentOrganization();

        if ($organization === null) {
            return new LengthAwarePaginator([], 0, 10);
        }

        return app(ContactRepository::class)->get($organization, $this->search);
    }

    /**
     * Open the contact form for a new contact.
     */
    public function createContact(): void
    {
        Gate::authorize('createContact', $this->requireOrganization());

        $this->dispatch('create-contact');
    }

    /**
     * Open the contact form for the given contact.
     */
    public function editContact(int $contactId): void
    {
        $organization = $this->requireOrganization();

        $contact = app(ContactRepository::class)->findById($organization, $contactId);

        Gate::authorize('updateContact', [$contact->stakeholder, $organization]);

        $this->dispatch('edit-contact', contactId: $contactId);
    }

    /**
     * Open the delete confirmation for the given contact.
     */
    public function confirmDelete(int $contactId, string $name): void
    {
        $this->deletingContactId = $contactId;
        $this->deletingContactName = $name;
        $this->showDeleteModal = true;
    }

    /**
     * Open the bulk delete confirmation for the selected contacts.
     */
    public function confirmDeleteSelected(): void
    {
        Gate::authorize('deleteAnyContact', $this->requireOrganization());

        $this->showBatchDeleteModal = true;
    }

    /**
     * Delete the contact awaiting confirmation.
     */
    public function deleteConfirmed(DeleteContact $deleteContact): void
    {
        $organization = $this->requireOrganization();

        abort_if($this->deletingContactId === null, 404);

        $contact = app(ContactRepository::class)->findById($organization, $this->deletingContactId);

        Gate::authorize('deleteContact', [$contact->stakeholder, $organization]);

        try {
            $deleteContact->handle($organization, $contact->getKey(), actor: Auth::user());
        } catch (Throwable $exception) {
            report($exception);

            Flux::toast(variant: 'danger', text: __('Failed to delete the contact.'));

            $this->reset('showDeleteModal', 'deletingContactId', 'deletingContactName');

            return;
        }

        $this->reset('showDeleteModal', 'deletingContactId', 'deletingContactName');
        $this->resetPage();
        $this->clearSelection();
        $this->selectionKey++;
        unset($this->contacts);

        Flux::toast(variant: 'success', text: __('Contact deleted.'));
    }

    /**
     * Delete the selected contacts that are still on the current page.
     */
    public function deleteSelectedConfirmed(DeleteContact $deleteContacts): void
    {
        $organization = $this->requireOrganization();

        Gate::authorize('deleteAnyContact', $organization);

        $pageIds = $this->contacts->pluck('id')->all();
        $ids = array_values(array_intersect(array_map('intval', $this->selected), $pageIds));

        if ($ids === []) {
            $this->reset('showBatchDeleteModal');

            return;
        }

        try {
            $count = $deleteContacts->handleBulk($organization, $ids, Auth::user());
        } catch (Throwable $exception) {
            report($exception);

            Flux::toast(variant: 'danger', text: __('Failed to delete the selected contacts.'));

            $this->reset('showBatchDeleteModal');

            return;
        }

        $this->reset('showBatchDeleteModal');
        $this->resetPage();
        $this->clearSelection();
        $this->selectionKey++;
        unset($this->contacts);

        Flux::toast(
            variant: 'success',
            text: __(':count contacts deleted successfully.', ['count' => $count]),
        );
    }

    /**
     * Clear the selection when the page changes.
     */
    public function updatedPage(): void
    {
        $this->clearSelection();
    }

    /**
     * Reset paging and selection after the search term changes.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->clearSelection();
        $this->selectionKey++;
    }

    /**
     * Recompute whether all or some contacts on the page are selected.
     */
    public function updatedSelected(): void
    {
        $pageIds = $this->selectablePageIds();
        $selected = array_map('intval', $this->selected);

        $this->allSelected = $pageIds !== [] && array_diff($pageIds, $selected) === [];
        $this->someSelected = ! $this->allSelected && array_intersect($pageIds, $selected) !== [];
    }

    /**
     * Select or deselect every deletable contact on the page.
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
     * @return array<int, int>
     */
    private function selectablePageIds(): array
    {
        if (! $this->canDeleteAny) {
            return [];
        }

        return $this->contacts->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
    }

    /**
     * Drop the current selection.
     */
    private function clearSelection(): void
    {
        $this->selected = [];
        $this->allSelected = false;
        $this->someSelected = false;
    }
}
