<?php

declare(strict_types=1);

namespace App\Livewire\OrgSettings;

use App\Actions\Addresses\SaveAddress;
use App\Actions\Contacts\SaveContactDetails;
use App\Actions\FileStorage;
use App\Concerns\ProfileValidationRules;
use App\Enums\ContactDetailType;
use App\Events\Organizations\OrganizationUpdated;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Livewire\Concerns\ManagesAddress;
use App\Models\Organization;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

class General extends Component
{
    use HasCurrentOrganization, ManagesAddress, ProfileValidationRules, WithFileUploads;

    public string $name = '';

    public string $summary = '';

    public string $contact_email = '';

    public string $contact_phone = '';

    /**
     * @var mixed
     */
    public $logo = null;

    #[Locked]
    public ?string $logo_url = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $organization = $this->currentOrganization();

        abort_if($organization === null, 404);

        $this->authorizeManager();

        $this->name = $organization->name;
        $this->summary = (string) $organization->summary;
        $this->contact_email = (string) $organization->contactDetailValue(ContactDetailType::Email);
        $this->contact_phone = (string) $organization->contactDetailValue(ContactDetailType::Phone);
        $this->logo_url = $organization->getRawOriginal('logo_url');
        $this->fillAddress($organization->address()->first());
    }

    #[Computed]
    public function logoPreviewUrl(): ?string
    {
        return $this->logo_url ? route('uploads', ['path' => $this->logo_url]) : null;
    }

    /**
     * Update the current organization's name.
     */
    public function update(FileStorage $fileStorage, SaveAddress $saveAddress, SaveContactDetails $saveContactDetails): void
    {
        $organization = $this->currentOrganization();

        abort_if($organization === null, 404);

        $this->authorizeManager();

        $this->validate([
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $validated = $this->validate([
            'name' => $this->nameRules(),
            'summary' => ['nullable', 'string', 'max:255'],
            ...$this->contactDetailRules(),
            ...$this->addressRules(),
        ]);

        $previousLogo = $organization->getRawOriginal('logo_url');
        $newLogoPath = null;
        $removeLogo = false;

        if ($this->logo instanceof UploadedFile) {
            $newLogoPath = $fileStorage->handle($this->logo, 'logos');
            $this->logo = null;
            $this->logo_url = $newLogoPath;
        } elseif ($previousLogo !== $this->logo_url) {
            $removeLogo = true;
        }

        try {
            DB::transaction(function () use ($organization, $validated, $newLogoPath, $removeLogo, $previousLogo, $fileStorage, $saveAddress, $saveContactDetails): void {
                $organization->name = $validated['name'];
                $organization->summary = $validated['summary'] ?? null;

                if ($newLogoPath !== null) {
                    $organization->logo_url = $newLogoPath;
                } elseif ($removeLogo) {
                    $organization->logo_url = null;
                }

                $organization->save();

                $saveAddress->handle($organization, $this->addressPayload());

                $details = $saveContactDetails->handle(
                    $organization,
                    $validated['contact_email'] ?? null,
                    $validated['contact_phone'] ?? null,
                );

                $fields = [
                    ...array_values(array_diff(array_keys($organization->getChanges()), ['updated_at'])),
                    ...$details,
                ];

                if ($fields !== []) {
                    $actor = Auth::user();

                    event(new OrganizationUpdated($organization, $actor instanceof User ? $actor : null, $organization, [
                        'fields' => $fields,
                    ]));
                }

                if ($newLogoPath !== null) {
                    // Replacement cleanup runs after commit; a failure is logged.
                    DB::afterCommit(fn () => $fileStorage->delete($previousLogo));
                }
            });
        } catch (Throwable $exception) {
            if ($newLogoPath !== null) {
                $storedLogo = Organization::withTrashed()
                    ->whereKey($organization->getKey())
                    ->value('logo_url');

                if ($storedLogo !== $newLogoPath) {
                    $fileStorage->delete($newLogoPath);
                }
            }

            $organization->refresh();
            $this->logo_url = $organization->getRawOriginal('logo_url');

            throw $exception;
        }

        if ($removeLogo && ! $fileStorage->delete($previousLogo)) {
            $restored = Organization::query()
                ->whereKey($organization->getKey())
                ->whereNull('logo_url')
                ->update(['logo_url' => $previousLogo]);

            if ($restored === 0) {
                $organization->refresh();
                $this->logo_url = $organization->getRawOriginal('logo_url');

                Flux::toast(variant: 'danger', text: __('Unable to remove the :field. Please try again.', ['field' => __('Organization Logo')]));

                return;
            }

            $organization->refresh();

            $actor = Auth::user();

            event(new OrganizationUpdated($organization, $actor instanceof User ? $actor : null, $organization, [
                'fields' => ['logo_url'],
                'restored' => true,
            ]));

            $this->logo_url = $previousLogo;

            Flux::toast(variant: 'danger', text: __('Unable to remove the :field. Please try again.', ['field' => __('Organization Logo')]));

            return;
        }

        unset($this->pendingApproval);

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

    public function render(): View
    {
        /** @disregard P1013 */
        return view('livewire.org-settings.general', [
            'organization' => $this->currentOrganization(),
        ])->title(__('Organization settings'));
    }
}
