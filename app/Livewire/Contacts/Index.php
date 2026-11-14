<?php

declare(strict_types=1);

namespace App\Livewire\Contacts;

use App\Actions\Contacts\DeleteContact;
use App\Actions\Contacts\FindContact;
use App\Actions\Contacts\ListContacts;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\Organization;
use App\Models\OrganizationRelationship;
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

    public function createContact(): void
    {
        Gate::authorize('createContact', Organization::class);

        $this->dispatch('create-contact');
    }

    public function editContact(int $contactId): void
    {
        $contact = app(FindContact::class)->byId($this->requireOrganization(), $contactId);

        Gate::authorize('updateContact', $contact->stakeholder);

        $this->dispatch('edit-contact', contactId: $contactId);
    }

    public function confirmDelete(int $contactId, string $name): void
    {
        $this->deletingContactId = $contactId;
        $this->deletingContactName = $name;
        $this->showDeleteModal = true;
    }

    public function confirmDeleteSelected(): void
    {
        Gate::authorize('deleteAnyContact', Organization::class);

        $this->showBatchDeleteModal = true;
    }

    public function deleteConfirmed(DeleteContact $deleteContact): void
    {
        $organization = $this->requireOrganization();

        abort_if($this->deletingContactId === null, 404);

        $contact = app(FindContact::class)->byId($organization, $this->deletingContactId);

        Gate::authorize('deleteContact', $contact->stakeholder);

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

    public function deleteSelectedConfirmed(DeleteContact $deleteContacts): void
    {
        $organization = $this->requireOrganization();

        Gate::authorize('deleteAnyContact', Organization::class);

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

    public function updatedPage(): void
    {
        $this->clearSelection();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->clearSelection();
        $this->selectionKey++;
    }

    public function updatedSelected(): void
    {
        $pageIds = $this->selectablePageIds();
        $selected = array_map('intval', $this->selected);

        $this->allSelected = $pageIds !== [] && array_diff($pageIds, $selected) === [];
        $this->someSelected = ! $this->allSelected && array_intersect($pageIds, $selected) !== [];
    }

    public function toggleSelectAll(): void
    {
        $pageIds = $this->selectablePageIds();
        $selected = array_map('intval', $this->selected);

        $this->selected = array_diff($pageIds, $selected) === [] ? [] : $pageIds;
        $this->selectionKey++;
        $this->updatedSelected();
    }

    #[On('contact-updated')]
    public function refreshContacts(): void
    {
        $this->clearSelection();
        $this->selectionKey++;
        unset($this->contacts);
    }

    #[Computed]
    public function canManage(): bool
    {
        return Gate::allows('createContact', Organization::class);
    }

    #[Computed]
    public function canDeleteAny(): bool
    {
        return Gate::allows('deleteAnyContact', Organization::class);
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

        return app(ListContacts::class)->handle($organization, $this->search);
    }

    public function render(): View
    {
        Gate::authorize('viewAnyContact', Organization::class);

        /** @disregard P1013 */
        return view('livewire.contacts.index', [
            'contacts' => $this->contacts,
            'organization' => $this->currentOrganization(),
            'canManage' => $this->canManage,
            'canDeleteAny' => $this->canDeleteAny,
        ])->title(__('Contacts'));
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

    private function clearSelection(): void
    {
        $this->selected = [];
        $this->allSelected = false;
        $this->someSelected = false;
    }
}
