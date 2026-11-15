<?php

declare(strict_types=1);

namespace App\Livewire\OrgSettings\General;

use App\Jobs\DeleteOrganization;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\User;
use App\Support\CurrentOrganization;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class RemoveOrganization extends Component
{
    use HasCurrentOrganization;

    public bool $showModal = false;

    public string $confirmation = '';

    /**
     * Open the organization removal modal.
     */
    public function confirmRemove(): void
    {
        $organization = $this->currentOrganization();

        abort_if($organization === null, 404);

        $this->authorizeOwner();

        abort_if($organization->isRootTenant(), 403);

        $this->confirmation = '';
        $this->resetValidation();
        $this->showModal = true;
    }

    /**
     * Queue the removal of the current organization.
     */
    public function remove(): void
    {
        $organization = $this->currentOrganization();

        abort_if($organization === null, 404);

        $this->authorizeOwner();

        abort_if($organization->isRootTenant(), 403);

        if ($this->confirmation !== $organization->name) {
            $this->addError('confirmation', __('The name does not match.'));

            return;
        }

        /** @var User|null $user */
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        dispatch(new DeleteOrganization($organization, $user->getKey()));

        session()->forget(CurrentOrganization::SESSION_KEY);

        Flux::toast(
            variant: 'success',
            text: __('Removal queued. :name will be deleted shortly.', ['name' => $organization->name]),
        );

        $this->redirect(route('organizations.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.org-settings.general.remove-organization', [
            'organization' => $this->currentOrganization(),
        ]);
    }
}
