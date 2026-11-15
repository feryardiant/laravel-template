<?php

declare(strict_types=1);

namespace App\Livewire\OrgSettings\Subsidiaries;

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
use Livewire\Attributes\On;
use Livewire\Component;

class RemoveSubsidiary extends Component
{
    use HasCurrentOrganization;

    public bool $showModal = false;

    public ?int $removingId = null;

    public ?string $removingName = null;

    public int $removingCount = 0;

    /**
     * Select a subsidiary to remove.
     */
    #[On('remove-subsidiary')]
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
        $this->showModal = true;
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

        dispatch(new DeleteOrganization($organization['organization'], $user->getKey()));

        $this->reset('showModal', 'removingId', 'removingName', 'removingCount');

        $this->dispatch('subsidiary-removed');

        Flux::toast(
            variant: 'success',
            text: __('Removal queued. :name will be deleted shortly.', ['name' => $organization['organization']->name]),
        );
    }

    /**
     * The root tenant's subsidiary tree, depth-first.
     *
     * @return Collection<int, array{organization: Organization, parent: Organization, kind: ?SubsidiaryKind, depth: int}>
     */
    private function subsidiaries(): Collection
    {
        $organization = $this->currentOrganization();

        return $organization?->isRootTenant() === true
            ? app(ListSubsidiaries::class)->handle($organization)
            : collect();
    }

    public function render(): View
    {
        return view('livewire.org-settings.subsidiaries.remove-subsidiary');
    }
}
