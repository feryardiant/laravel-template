<?php

declare(strict_types=1);

namespace App\Livewire\Settings\Organizations;

use App\Actions\Organizations\CreateOrganization;
use App\Actions\Organizations\ListSubsidiaries;
use App\Concerns\ProfileValidationRules;
use App\Enums\SubsidiaryKind;
use App\Models\Organization;
use App\Models\User;
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

class Create extends Component
{
    use ProfileValidationRules, WithFileUploads;

    public bool $internal = false;

    public bool $showModal = false;

    public string $name = '';

    public ?string $parent_id = null;

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

    /**
     * Open the create-organization flyout.
     */
    #[On('create-organization')]
    public function open(): void
    {
        if ($this->internal) {
            $user = Auth::user();
            $root = Organization::query()->rootTenant()->first();

            abort_unless(
                $user instanceof User && $root !== null && $user->roleIn($root)?->isOwner(),
                403,
            );
        }

        $this->reset('name', 'parent_id', 'logo', 'kind');
        $this->kind = SubsidiaryKind::Subsidiary->value;
        $this->resetValidation();
        $this->showModal = true;
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
            'parent_id' => ['nullable', 'integer', Rule::in($parents->pluck('id')->all())],
            'kind' => ['nullable', Rule::enum(SubsidiaryKind::class)],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $parent = $validated['parent_id'] === null
            ? null
            : $parents->firstWhere('id', (int) $validated['parent_id']);

        if ($this->internal) {
            $root = Organization::query()->rootTenant()->first();

            abort_if($root === null, 404);
            abort_unless($user->roleIn($root)?->isOwner(), 403);

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

        $this->reset('showModal', 'name', 'parent_id', 'logo', 'kind');

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

    /**
     * Organizations that can act as the parent.
     *
     * @return Collection<int, Organization>
     */
    #[Computed]
    public function parentOrganizations(): Collection
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return collect();
        }

        if ($this->internal) {
            $root = Organization::query()->rootTenant()->first();

            return $root === null
                ? collect()
                : app(ListSubsidiaries::class)->handle($root)->pluck('organization');
        }

        return $user->organizations()
            ->filter(fn (Organization $organization): bool => $user->roleIn($organization)?->isOwner() === true)
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
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

    public function render(): View
    {
        return view('livewire.settings.organizations.create');
    }
}
