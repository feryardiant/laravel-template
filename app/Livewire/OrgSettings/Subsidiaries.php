<?php

declare(strict_types=1);

namespace App\Livewire\OrgSettings;

use App\Actions\Organizations\ListSubsidiaries;
use App\Enums\SubsidiaryKind;
use App\Jobs\DeleteOrganization;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\Organization;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class Subsidiaries extends Component
{
    use HasCurrentOrganization;

    public bool $showRemoveModal = false;

    public ?int $removingId = null;

    public ?string $removingName = null;

    public int $removingCount = 0;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $organization = $this->currentOrganization();

        abort_if($organization === null || ! $organization->isRootTenant(), 404);

        $role = $this->currentRole();

        abort_unless($role?->isOwner() || $role?->isAdmin(), 403);
    }

    /**
     * Select a subsidiary to remove.
     */
    public function confirmRemove(int $organizationId): void
    {
        abort_unless($this->currentRole()?->isOwner(), 403);

        $rows = $this->subsidiaries();
        $index = $rows->search(
            fn (array $row): bool => $row['organization']->getKey() === $organizationId,
        );

        abort_if($index === false, 404);

        $depth = $rows[$index]['depth'];
        $count = 1;

        for ($position = $index + 1; $position < $rows->count(); $position++) {
            if ($rows[$position]['depth'] <= $depth) {
                break;
            }

            $count++;
        }

        $this->removingId = $organizationId;
        $this->removingName = $rows[$index]['organization']->name;
        $this->removingCount = $count;
        $this->showRemoveModal = true;
    }

    /**
     * Queue the removal of the selected subsidiary and its nested subsidiaries.
     */
    public function remove(): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        abort_unless($user instanceof User && $this->currentRole()?->isOwner(), 403);

        $rows = $this->subsidiaries();
        $organization = $rows->first(
            fn (array $row): bool => $row['organization']->getKey() === $this->removingId,
        );

        abort_if($organization === null, 404);

        DeleteOrganization::dispatch($organization['organization'], $user->getKey());

        $this->reset('showRemoveModal', 'removingId', 'removingName', 'removingCount');
        unset($this->subsidiaries);

        Flux::toast(
            variant: 'success',
            text: __('Removal queued. :name will be deleted shortly.', ['name' => $organization['organization']->name]),
        );
    }

    /**
     * The root organization, when the current organization is the root tenant.
     */
    #[Computed]
    public function root(): ?Organization
    {
        $organization = $this->currentOrganization();

        return $organization?->isRootTenant() === true ? $organization : null;
    }

    /**
     * The root tenant's subsidiary tree, depth-first.
     *
     * @return Collection<int, array{organization: Organization, parent: Organization, kind: ?SubsidiaryKind, depth: int}>
     */
    #[Computed]
    public function subsidiaries(): Collection
    {
        $organization = $this->currentOrganization();
        $root = $organization?->isRootTenant() === true ? $organization : null;

        return $root === null ? collect() : app(ListSubsidiaries::class)->handle($root);
    }

    /**
     * Only an owner may create or remove subsidiaries.
     */
    #[Computed]
    public function canManage(): bool
    {
        return $this->currentRole()?->isOwner() === true;
    }

    #[On('organization-created')]
    public function refreshSubsidiaries(): void
    {
        //
    }

    public function render(): View
    {
        /** @disregard P1013 */
        return view('livewire.org-settings.subsidiaries')->title(__('Subsidiaries'));
    }
}
