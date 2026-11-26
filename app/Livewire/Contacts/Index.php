<?php

declare(strict_types=1);

namespace App\Livewire\Contacts;

use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\OrganizationRelationship;
use App\Repositories\ContactRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * @property-read LengthAwarePaginator<int, OrganizationRelationship> $contacts
 * @property-read bool $canManage
 * @property-read bool $canDeleteAny
 */
class Index extends Component
{
    use HasCurrentOrganization, WithPagination;

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
    public function confirmDelete(int $contactId): void
    {
        $this->dispatch('delete-contact', contactId: $contactId);
    }

    /**
     * Open the bulk delete confirmation for the selected contacts.
     */
    public function confirmDeleteSelected(): void
    {
        $this->requireOrganization();

        $pageIds = $this->contacts->pluck('id')->all();
        $ids = array_values(array_intersect(array_map('intval', $this->selected), $pageIds));

        $this->dispatch('delete-contacts', ids: $ids);
    }

    /**
     * Refresh the list after a contact is deleted.
     */
    #[On('contact-deleted')]
    public function refreshAfterDelete(): void
    {
        $this->resetPage();
        $this->refreshContacts();
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
