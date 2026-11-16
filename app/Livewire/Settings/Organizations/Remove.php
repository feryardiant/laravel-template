<?php

declare(strict_types=1);

namespace App\Livewire\Settings\Organizations;

use App\Jobs\DeleteOrganization;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\Organization;
use App\Models\User;
use App\Support\CurrentOrganization;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class Remove extends Component
{
    use HasCurrentOrganization;

    public bool $showModal = false;

    public ?int $organizationId = null;

    public ?string $organizationName = null;

    public string $confirmation = '';

    /**
     * Select an organization to remove.
     */
    #[On('remove-organization')]
    public function confirm(int $organizationId): void
    {
        $organization = $this->removableOrganization($organizationId);

        $this->organizationId = $organization->getKey();
        $this->organizationName = $organization->name;
        $this->confirmation = '';
        $this->showModal = true;
    }

    /**
     * Queue the removal of the selected organization.
     */
    public function remove(): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        abort_if($this->organizationId === null || ! $user instanceof User, 404);

        $organization = $this->removableOrganization($this->organizationId);

        if ($this->confirmation !== $organization->name) {
            $this->addError('confirmation', __('The name does not match.'));

            return;
        }

        dispatch(new DeleteOrganization($organization, $user->getKey()));

        if ($this->currentOrganization()?->is($organization) === true) {
            session()->forget(CurrentOrganization::SESSION_KEY);
        }

        $this->reset('showModal', 'organizationId', 'organizationName', 'confirmation');
        $this->dispatch('memberships-changed');

        Flux::toast(
            variant: 'success',
            text: __('Removal queued. :name will be deleted shortly.', ['name' => $organization->name]),
        );
    }

    /**
     * Resolve an organization the current user may remove.
     */
    private function removableOrganization(int $organizationId): Organization
    {
        /** @var User|null $user */
        $user = Auth::user();

        abort_if(! $user instanceof User, 403);

        $organization = $user->organizations()->firstWhere('id', $organizationId);

        abort_if($organization === null, 404);
        abort_if($organization->isRootTenant(), 403);
        abort_unless($user->roleIn($organization)?->isOwner(), 403);
        abort_unless($organization->activeOwnerCount() === 1, 403);

        return $organization;
    }

    public function render(): View
    {
        return view('livewire.settings.organizations.remove');
    }
}
