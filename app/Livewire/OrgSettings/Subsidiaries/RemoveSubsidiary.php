<?php

declare(strict_types=1);

namespace App\Livewire\OrgSettings\Subsidiaries;

use App\Jobs\DeleteOrganization;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\User;
use App\Repositories\OrganizationRepository;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Queues removal of a subsidiary and its nested subsidiaries.
 */
class RemoveSubsidiary extends Component
{
    use HasCurrentOrganization;

    public bool $showModal = false;

    public ?int $removingId = null;

    public ?string $removingName = null;

    public int $removingCount = 0;

    public function render(): View
    {
        return view('livewire.org-settings.subsidiaries.remove-subsidiary');
    }

    /**
     * Select a subsidiary to remove.
     */
    #[On('remove-subsidiary')]
    public function confirmRemove(int $organizationId): void
    {
        $root = $this->requireOrganization();

        $this->authorize('createSubsidiary', $root);

        $preview = app(OrganizationRepository::class)->preview($root, $organizationId);

        abort_if($preview === null, 404);

        $this->authorize('delete', $preview['organization']);

        $this->removingId = $organizationId;
        $this->removingName = $preview['organization']->name;
        $this->removingCount = $preview['count'];
        $this->showModal = true;
    }

    /**
     * Queue the removal of the selected subsidiary and its nested subsidiaries.
     */
    public function remove(): void
    {
        /** @var User $user */
        $user = Auth::user();

        $root = $this->requireOrganization();

        $this->authorize('createSubsidiary', $root);

        abort_if($this->removingId === null, 404);

        $preview = app(OrganizationRepository::class)->preview($root, $this->removingId);

        abort_if($preview === null, 404);

        $this->authorize('delete', $preview['organization']);

        dispatch(new DeleteOrganization($preview['organization'], $user->getKey()));

        $this->reset('showModal', 'removingId', 'removingName', 'removingCount');

        $this->dispatch('subsidiary-removed');

        Flux::toast(
            variant: 'success',
            text: __('Removal queued. :name will be deleted shortly.', ['name' => $preview['organization']->name]),
        );
    }
}
