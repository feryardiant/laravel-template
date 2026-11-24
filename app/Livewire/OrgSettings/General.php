<?php

declare(strict_types=1);

namespace App\Livewire\OrgSettings;

use App\Actions\Organizations\UpdateOrganization;
use App\Concerns\ProfileValidationRules;
use App\Enums\ContactDetailType;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Livewire\Concerns\ManagesAddress;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

/**
 * @property-read ?string $logoPreviewUrl
 */
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

    /**
     * The preview URL for the stored logo, when one is set.
     */
    #[Computed]
    public function logoPreviewUrl(): ?string
    {
        return $this->logoUrl ? route('uploads', ['path' => $this->logoUrl]) : null;
    }

    /**
     * Update the current organization's name.
     */
    public function update(UpdateOrganization $updateOrganization): void
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

        /** @var User|null $actor */
        $actor = Auth::user();

        abort_unless($actor instanceof User, 403);

        $logo = $this->logo instanceof UploadedFile ? $this->logo : null;
        $this->logo = null;

        try {
            $result = $updateOrganization->handle(
                organization: $organization,
                actor: $actor,
                data: [
                    'name' => $validated['name'],
                    'summary' => $this->summary,
                    'address' => $this->addressPayload(),
                    'details' => $this->changedContactDetails(),
                    'remove_logo' => $this->logoUrl === null && $this->originalLogoUrl !== null,
                ],
                expected: [
                    'name' => $this->originalName,
                    'summary' => $this->originalSummary,
                    'address' => $this->originalAddress,
                    'details' => $this->originalDetails,
                    'logo_url' => $this->originalLogoUrl,
                ],
                logo: $logo,
            );
        } catch (Throwable $exception) {
            $organization->refresh();
            $this->logoUrl = $organization->getRawOriginal('logo_url');

            throw $exception;
        }

        $organization = $result['organization'];
        $this->logoUrl = $result['logo_url'];

        $this->rememberContactDetails();
        $this->originalAddress = $this->addressPayload();

        $this->originalName = $organization->name;
        $this->originalSummary = (string) $organization->summary;
        $this->name = $this->originalName;
        $this->summary = $this->originalSummary;

        $this->originalLogoUrl = $this->logoUrl;

        if ($result['logo_removal_failed']) {
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
        $this->logoUrl = null;
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

    /**
     * Reset the detail baselines to the values now in the form.
     */
    private function rememberContactDetails(): void
    {
        $this->originalDetails = $this->contactDetailValues();
    }
}
