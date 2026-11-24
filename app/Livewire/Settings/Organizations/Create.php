<?php

declare(strict_types=1);

namespace App\Livewire\Settings\Organizations;

use App\Actions\Organizations\CreateOrganization;
use App\Concerns\ProfileValidationRules;
use App\Enums\SubsidiaryKind;
use App\Models\Organization;
use App\Models\User;
use App\Repositories\OrganizationRepository;
use App\Support\CurrentOrganization;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * @property-read Collection<int, Organization> $parentOrganizations
 * @property-read list<SubsidiaryKind> $kinds
 */
class Create extends Component
{
    use ProfileValidationRules, WithFileUploads;

    public bool $internal = false;

    public bool $showModal = false;

    public string $name = '';

    public ?string $parentId = null;

    public ?string $kind = null;

    /**
     * @var mixed
     */
    public $logo = null;

    /**
     * Mount the component.
     */
    public function mount(bool $internal = false): void
    {
        $this->internal = $internal;
    }

    public function render(): View
    {
        return view('livewire.settings.organizations.create');
    }

    /**
     * Open the create-organization flyout.
     */
    #[On('create-organization')]
    public function open(): void
    {
        if ($this->internal) {
            $root = Organization::query()->rootTenant()->first();

            abort_if($root === null, 404);

            $this->authorize('createSubsidiary', $root);
        }

        $this->reset('name', 'parentId', 'logo', 'kind');
        $this->kind = SubsidiaryKind::Subsidiary->value;
        $this->resetValidation();
        $this->showModal = true;
    }

    /**
     * Organizations that can act as the parent.
     *
     * @return Collection<int, Organization>
     */
    #[Computed]
    public function parentOrganizations(): Collection
    {
        $user = Auth::user();

        return $user instanceof User
            ? app(OrganizationRepository::class)->getCreatable($user, $this->internal)
            : collect();
    }

    /**
     * The kinds a child organization can have.
     *
     * @return list<SubsidiaryKind>
     */
    #[Computed]
    public function kinds(): array
    {
        return SubsidiaryKind::cases();
    }

    /**
     * Create the organization and make it the current one.
     */
    public function create(CreateOrganization $createOrganization): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        $parents = $this->parentOrganizations();

        $validated = $this->validate([
            'name' => $this->nameRules(),
            'parentId' => ['nullable', 'integer', Rule::in($parents->pluck('id')->all())],
            'kind' => ['nullable', Rule::enum(SubsidiaryKind::class)],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $parent = $validated['parentId'] === null
            ? null
            : $parents->firstWhere('id', (int) $validated['parentId']);

        if ($this->internal) {
            $root = Organization::query()->rootTenant()->first();

            abort_if($root === null, 404);

            $this->authorize('createSubsidiary', $root);

            $parent ??= $root;
        }

        $kind = $parent === null
            ? null
            : SubsidiaryKind::from((string) ($validated['kind'] ?? SubsidiaryKind::Subsidiary->value));

        $organization = $createOrganization->handle(
            $user,
            $validated['name'],
            $parent,
            $this->logo instanceof UploadedFile ? $this->logo : null,
            $kind,
        );

        if (! $this->internal) {
            app(CurrentOrganization::class)->switch($user, $organization);
        }

        $this->reset('showModal', 'name', 'parentId', 'logo', 'kind');

        $this->dispatch('organization-created');

        Flux::toast(
            variant: 'success',
            text: __(':name has been created.', ['name' => $organization->name]),
            link: $this->internal
                ? null
                : [
                    'label' => __('Open organization settings'),
                    'href' => route('org-settings.general'),
                ],
        );
    }

    /**
     * Clear the pending logo.
     */
    public function clearLogo(): void
    {
        $this->logo = null;
    }
}
