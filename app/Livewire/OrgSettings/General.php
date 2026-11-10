<?php

declare(strict_types=1);

namespace App\Livewire\OrgSettings;

use App\Actions\Files\StoreImage;
use App\Concerns\ProfileValidationRules;
use App\Livewire\Concerns\HasCurrentOrganization;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
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
    public function update(StoreImage $storeImage): void
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
            $path = $storeImage->replace($this->logo, 'logos', $previousLogo);
            $organization->logo_url = $path;
            $this->logo = null;
            $this->logo_url = $path;
        } elseif ($previousLogo !== $this->logo_url) {
            $storeImage->delete($previousLogo);
            $organization->logo_url = $this->logo_url;
        }

        $organization->save();

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

    #[Computed]
    public function logoPreviewUrl(): ?string
    {
        return $this->logo_url ? route('uploads', ['path' => $this->logo_url]) : null;
    }

    public function render(): View
    {
        /** @disregard P1013 */
        return view('livewire.org-settings.general')->title(__('Organization settings'));
    }
}
