<?php

declare(strict_types=1);

namespace App\Livewire\OrgSettings;

use App\Actions\FileStorage;
use App\Actions\Organizations\CancelOwnershipTransfer;
use App\Actions\Organizations\RequestOwnershipTransfer;
use App\Concerns\ProfileValidationRules;
use App\Enums\OwnershipTransferStatus;
use App\Jobs\DeleteOrganization;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\OrganizationOwnershipTransfer;
use App\Models\Person;
use App\Models\User;
use App\Support\CurrentOrganization;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class General extends Component
{
    use HasCurrentOrganization, ProfileValidationRules, WithFileUploads;

    public string $name = '';

    /**
     * @var mixed
     */
    public $logo = null;

    #[Locked]
    public ?string $logo_url = null;

    public bool $showTransferModal = false;

    public ?string $transferRecipientId = null;

    public string $transferConfirmation = '';

    public bool $showRemoveModal = false;

    public string $removeConfirmation = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $organization = $this->currentOrganization();

        abort_if($organization === null, 404);

        $this->authorizeManager();

        $this->name = $organization->name;
        $this->logo_url = $organization->getRawOriginal('logo_url');
    }

    /**
     * Update the current organization's name.
     */
    public function update(FileStorage $fileStorage): void
    {
        $organization = $this->currentOrganization();

        abort_if($organization === null, 404);

        $this->authorizeManager();

        $this->validate([
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $validated = $this->validate([
            'name' => $this->nameRules(),
        ]);

        $organization->name = $validated['name'];

        $previousLogo = $organization->getRawOriginal('logo_url');

        if ($this->logo instanceof UploadedFile) {
            $path = $fileStorage->replace($this->logo, 'logos', $previousLogo);
            $organization->logo_url = $path;
            $this->logo = null;
            $this->logo_url = $path;
        } elseif ($previousLogo !== $this->logo_url) {
            $fileStorage->delete($previousLogo);
            $organization->logo_url = $this->logo_url;
        }

        $organization->save();

        unset($this->pendingTransfer);

        Flux::toast(variant: 'success', text: __('Organization updated.'));
    }

    /**
     * Clear the pending or stored organization logo.
     */
    public function clearLogo(): void
    {
        $this->logo = null;
        $this->logo_url = null;
    }

    /**
     * Open the ownership transfer modal.
     */
    public function confirmTransfer(): void
    {
        $this->authorizeOwner();

        $this->reset('transferRecipientId', 'transferConfirmation');
        $this->resetValidation();
        $this->showTransferModal = true;
    }

    /**
     * Send an ownership transfer request to another member.
     */
    public function transfer(RequestOwnershipTransfer $transferOwnership): void
    {
        $organization = $this->currentOrganization();

        abort_if($organization === null, 404);

        $this->authorizeOwner();

        $members = $this->transferMembers();

        $validated = $this->validate([
            'transferRecipientId' => ['required', 'integer', Rule::in($members->pluck('id')->all())],
            'transferConfirmation' => ['required', 'string', Rule::in([$organization->name])],
        ]);

        $recipient = $members->firstWhere('id', (int) $validated['transferRecipientId']);

        abort_if($recipient === null, 404);

        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        $transferOwnership->handle($organization, $user, $recipient);

        $this->reset('showTransferModal', 'transferRecipientId', 'transferConfirmation');
        unset($this->pendingTransfer, $this->transferMembers);

        Flux::toast(
            variant: 'success',
            text: __('Ownership transfer request sent to :name.', ['name' => $recipient->name]),
        );
    }

    /**
     * Cancel the pending ownership transfer.
     */
    public function cancelTransfer(CancelOwnershipTransfer $cancelOwnership): void
    {
        $organization = $this->currentOrganization();

        abort_if($organization === null, 404);

        $this->authorizeOwner();

        $transfer = $this->pendingTransfer();

        abort_if($transfer === null, 404);

        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        $cancelOwnership->handle($transfer, $user);

        unset($this->pendingTransfer);

        Flux::toast(variant: 'success', text: __('Transfer request cancelled.'));
    }

    /**
     * Open the organization removal modal.
     */
    public function confirmRemove(): void
    {
        $organization = $this->currentOrganization();

        abort_if($organization === null, 404);

        $this->authorizeOwner();

        abort_if($organization->isRootTenant(), 403);

        $this->removeConfirmation = '';
        $this->resetValidation();
        $this->showRemoveModal = true;
    }

    /**
     * Queue the removal of the current organization.
     */
    public function remove(): void
    {
        $organization = $this->currentOrganization();

        abort_if($organization === null, 404);

        $this->authorizeOwner();

        abort_if($organization->isRootTenant(), 403);

        if ($this->removeConfirmation !== $organization->name) {
            $this->addError('removeConfirmation', __('The name does not match.'));

            return;
        }

        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        DeleteOrganization::dispatch($organization, $user->getKey());

        session()->forget(CurrentOrganization::SESSION_KEY);

        Flux::toast(
            variant: 'success',
            text: __('Removal queued. :name will be deleted shortly.', ['name' => $organization->name]),
        );

        $this->redirect(route('organizations.index'), navigate: true);
    }

    #[Computed]
    public function logoPreviewUrl(): ?string
    {
        return $this->logo_url ? route('uploads', ['path' => $this->logo_url]) : null;
    }

    /**
     * Whether the current user owns the organization.
     */
    #[Computed]
    public function isOwner(): bool
    {
        return $this->currentRole()?->isOwner() === true;
    }

    /**
     * Active members with a credential, excluding the owner.
     *
     * @return Collection<int, Person>
     */
    #[Computed]
    public function transferMembers(): Collection
    {
        $organization = $this->currentOrganization();
        $owner = Auth::user()?->person?->getKey();

        if ($organization === null || $owner === null) {
            return new Collection;
        }

        $today = now()->toDateString();

        return $organization->members()
            ->whereHas('user')
            ->whereKeyNot($owner)
            ->where(fn (Builder $query): Builder => $query->whereNull('organization_relationships.start_date')
                ->orWhere('organization_relationships.start_date', '<=', $today))
            ->where(fn (Builder $query): Builder => $query->whereNull('organization_relationships.finish_date')
                ->orWhere('organization_relationships.finish_date', '>=', $today))
            ->with('user')
            ->orderBy('people.name')
            ->get();
    }

    /**
     * The pending ownership transfer initiated by the current owner.
     */
    #[Computed]
    public function pendingTransfer(): ?OrganizationOwnershipTransfer
    {
        $organization = $this->currentOrganization();
        $owner = Auth::user()?->person?->getKey();

        if ($organization === null || $owner === null) {
            return null;
        }

        return OrganizationOwnershipTransfer::query()
            ->where('organization_id', $organization->getKey())
            ->where('from_person_id', $owner)
            ->where('status', OwnershipTransferStatus::Pending)
            ->where('expires_at', '>', now())
            ->first();
    }

    public function render(): View
    {
        /** @disregard P1013 */
        return view('livewire.org-settings.general', [
            'organization' => $this->currentOrganization(),
        ])->title(__('Organization settings'));
    }

    private function authorizeOwner(): void
    {
        abort_unless($this->currentRole()?->isOwner(), 403);
    }
}
