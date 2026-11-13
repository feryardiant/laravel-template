<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Actions\Memberships\LeaveOrganization;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\User;
use App\Support\CurrentOrganization;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Organizations extends Component
{
    use HasCurrentOrganization;

    /**
     * Leave the current organization.
     */
    public function leave(LeaveOrganization $leaveOrganization): void
    {
        $organization = $this->currentOrganization();
        $user = Auth::user();

        abort_if($organization === null || ! $user instanceof User, 404);

        if (! $leaveOrganization->handle($user, $organization)) {
            Flux::toast(variant: 'danger', text: __('You cannot leave while you are the last owner.'));

            return;
        }

        session()->forget(CurrentOrganization::SESSION_KEY);

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render(): View
    {
        $organization = $this->currentOrganization();
        $user = Auth::user();
        $role = $organization !== null && $user instanceof User ? $user->roleIn($organization) : null;

        /** @disregard P1013 */
        return view('livewire.settings.organizations', [
            'organization' => $organization,
            'role' => $role,
            'isLastOwner' => $role?->isOwner() === true && $organization->activeOwnerCount() === 1,
        ])->title(__('Organization settings'));
    }
}
