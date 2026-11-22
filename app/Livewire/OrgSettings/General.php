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

    public string $contactEmail = '';

    public string $contactPhone = '';

    /**
     * The detail values loaded when the form opened; only changes are submitted.
     *
     * @var array<string, string>
     */
    #[Locked]
    public array $originalDetails = [];

    #[Locked]
    public ?string $originalLogoUrl = null;

    #[Locked]
    public string $originalName = '';

    #[Locked]
    public string $originalSummary = '';

    /**
     * The address payload loaded when the form opened.
     *
     * @var array<string, string|null>|null
     */
    #[Locked]
    public ?array $originalAddress = null;

    /**
     * @var mixed
     */
    public $logo = null;

    #[Locked]
    public ?string $logoUrl = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $organization = $this->currentOrganization();

        abort_if($organization === null, 404);

        $this->authorize('update', $organization);

        $this->name = $organization->name;
        $this->summary = (string) $organization->summary;
        $this->contactEmail = (string) $organization->contactDetailValue(ContactDetailType::Email);
        $this->contactPhone = (string) $organization->contactDetailValue(ContactDetailType::Phone);
        $this->logoUrl = $organization->getRawOriginal('logo_url');
        $this->fillAddress($organization->address()->first());
        $this->originalDetails = $this->contactDetailValues();
        $this->originalLogoUrl = $this->logoUrl;
        $this->originalAddress = $this->addressPayload();
        $this->originalName = $this->name;
        $this->originalSummary = $this->summary;
    }

    /**
     * The detail values currently held by the form.
     *
     * @return array<string, string>
     */
    private function contactDetailValues(): array
    {
        return [
            ContactDetailType::Email->value => $this->contactEmail,
            ContactDetailType::Phone->value => $this->contactPhone,
        ];
    }

    /**
     * The detail values the editor changed, so untouched ones keep another save.
     *
     * @return array<string, string>
     */
    private function changedContactDetails(): array
    {
        return array_diff_assoc($this->contactDetailValues(), $this->originalDetails);
    }

    private function rememberContactDetails(): void
    {
        $this->originalDetails = $this->contactDetailValues();
    }

    #[Computed]
    public function logoPreviewUrl(): ?string
    {
        return $this->logoUrl ? route('uploads', ['path' => $this->logoUrl]) : null;
    }

    /**
     * Update the current organization's name.
     */
    public function update(FileStorage $fileStorage, SaveAddress $saveAddress, SaveContactDetails $saveContactDetails): void
    {
        $organization = $this->currentOrganization();

        abort_if($organization === null, 404);

        $this->authorize('update', $organization);

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
            $this->logoUrl = $newLogoPath;
        } elseif ($this->logoUrl === null && $this->originalLogoUrl !== null) {
            $removeLogo = true;
        }

        try {
            DB::transaction(function () use ($organization, $validated, $newLogoPath, $removeLogo, $previousLogo, $fileStorage, $saveAddress, $saveContactDetails): void {
                if ($this->name !== $this->originalName) {
                    $organization->name = $validated['name'];
                }

                if ($this->summary !== $this->originalSummary) {
                    $organization->summary = $validated['summary'] ?? null;
                }

                if ($newLogoPath !== null) {
                    $organization->logo_url = $newLogoPath;
                } elseif ($removeLogo) {
                    $organization->logo_url = null;
                }

                $organization->save();

                $address = $this->addressPayload();

                if ($address !== $this->originalAddress) {
                    $saveAddress->handle($organization, $address);
                }

                $details = $saveContactDetails->handle($organization, $this->changedContactDetails());

                $fields = [
                    ...array_values(array_diff(array_keys($organization->getChanges()), ['updated_at'])),
                    ...$details,
                ];

                if ($fields !== []) {
                    $actor = Auth::user();

                    event(new OrganizationUpdated($organization, $actor instanceof User ? $actor : null, $fields));
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
            $this->logoUrl = $organization->getRawOriginal('logo_url');

            throw $exception;
        }

        $this->rememberContactDetails();
        $this->originalAddress = $this->addressPayload();

        $organization->refresh();

        $this->originalName = $organization->name;
        $this->originalSummary = (string) $organization->summary;
        $this->name = $this->originalName;
        $this->summary = $this->originalSummary;

        if ($removeLogo && ! $fileStorage->delete($previousLogo)) {
            $restored = Organization::query()
                ->whereKey($organization->getKey())
                ->whereNull('logo_url')
                ->update(['logo_url' => $previousLogo]);

            if ($restored === 0) {
                $organization->refresh();
                $this->logoUrl = $organization->getRawOriginal('logo_url');
                $this->originalLogoUrl = $this->logoUrl;

                Flux::toast(variant: 'danger', text: __('Unable to remove the :field. Please try again.', ['field' => __('Organization Logo')]));

                return;
            }

            $organization->refresh();

            $actor = Auth::user();

            event(new OrganizationUpdated($organization, $actor instanceof User ? $actor : null, ['logo_url'], true));

            $this->logoUrl = $previousLogo;
            $this->originalLogoUrl = $this->logoUrl;

            Flux::toast(variant: 'danger', text: __('Unable to remove the :field. Please try again.', ['field' => __('Organization Logo')]));

            return;
        }

        $this->originalLogoUrl = $this->logoUrl;

        unset($this->pendingApproval);

        Flux::toast(variant: 'success', text: __('Organization updated.'));
    }

    /**
     * Clear the pending or stored organization logo.
     */
    public function clearLogo(): void
    {
        $this->logo = null;
        $this->logoUrl = null;
    }

    public function render(): View
    {
        $organization = $this->currentOrganization();

        abort_if($organization === null, 404);

        $this->authorize('update', $organization);

        /** @disregard P1013 */
        return view('livewire.org-settings.general', [
            'organization' => $organization,
        ])->title(__('Organization settings'));
    }
}
